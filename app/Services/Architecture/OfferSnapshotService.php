<?php

namespace App\Services\Architecture;

use App\DTO\Architecture\OfferSnapshotData;
use App\Models\Architecture\OfferSnapshot;
use App\Models\CanonicalProductIdentity;
use App\Models\CanonicalProductIdentitySource;
use App\Models\Product;
use App\Models\Provider;
use App\Models\ProviderProduct;
use App\Models\WildflowCatalog;
use App\Services\CanonicalProductIdentityService;
use App\Services\MeanlyFirstPartyStorefrontService;
use App\Services\PricingProjectionService;
use App\Services\SellerOfferRankingService;
use App\Services\StorefrontFulfillmentService;
use App\Services\VaultTransitService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OfferSnapshotService implements OfferSnapshotServiceInterface
{
    public function __construct(
        private readonly CanonicalProductIdentityService $identityService,
        private readonly SellerOfferRankingService $offerRanking,
        private readonly PricingProjectionService $pricingProjection,
        private readonly MeanlyFirstPartyStorefrontService $storefront,
        private readonly VaultTransitService $vault,
    ) {}

    public function createFromRuntimeContext(
        CanonicalProductIdentity $entitlement,
        int $productId,
        int $providerId,
        string $sku,
        ?string $selectedBy = 'checkout',
    ): OfferSnapshotData {
        $product = Product::query()->with(['shop', 'provider', 'brand'])->findOrFail($productId);
        $provider = Provider::query()->findOrFail($providerId);

        if ((string) $product->sku !== $sku) {
            throw ValidationException::withMessages([
                'sku' => 'Product SKU does not match checkout SKU.',
            ]);
        }

        $context = $this->resolveProviderContext($product);
        $providerProduct = $context['provider_product'];
        if (! $providerProduct) {
            throw ValidationException::withMessages([
                'provider_product' => 'Cannot pin offer snapshot without a resolved provider product.',
            ]);
        }

        $rankedOffer = $this->rankedOfferForProduct($product, $providerProduct);
        $price = $this->pricingProjection->publicPriceForProduct($product);
        $buyerPriceCents = (int) ($product->price_rub ?? 0);
        $purchasePriceCents = (int) (
            $product->purchase_price_rub
            ?: round(((float) ($product->purchase_price ?? 0)) * 100)
            ?: round(((float) ($context['provider_product']?->purchase_price ?? 0)) * 100)
        );

        $payload = [
            'selected_by' => $selectedBy,
            'catalog_sku' => $context['catalog_sku'],
            'service_sku' => $context['service_sku'],
            'ranking' => data_get($rankedOffer, 'ranking'),
            'price' => $price,
            'entitlement' => [
                'id' => $entitlement->id,
                'fingerprint' => $entitlement->fingerprint,
                'identity_slug' => $entitlement->identity_slug,
            ],
            'provider' => [
                'id' => $provider->id,
                'type' => $provider->type,
            ],
            'provider_product_id' => $context['provider_product']?->id,
            'offer_facts' => $rankedOffer,
            'pinned_at' => now()->toJSON(),
        ];

        $snapshot = OfferSnapshot::query()->create([
            'id' => (string) Str::uuid(),
            'snapshot_uuid' => (string) Str::uuid(),
            'canonical_product_identity_id' => $entitlement->id,
            'entitlement_fingerprint' => (string) $entitlement->fingerprint,
            'shop_id' => (int) $product->shop_id,
            'product_id' => (int) $product->id,
            'sku' => (string) $product->sku,
            'provider_id' => (int) $provider->id,
            'provider_product_id' => (int) $providerProduct->id,
            'provider_sku' => (string) $context['service_sku'],
            'offer_kind' => $this->offerKind($product),
            'buyer_price_cents' => max(0, $buyerPriceCents),
            'buyer_currency' => strtoupper((string) ($price['currency'] ?? 'RUB')),
            'purchase_price_cents' => max(0, $purchasePriceCents),
            'storage_price_cents' => max(0, $buyerPriceCents),
            'fulfillment_mode' => StorefrontFulfillmentService::FULFILLMENT_INSTANT,
            'stock_count' => data_get($rankedOffer, 'ranking.metrics.stock_count'),
            'ranking_score' => data_get($rankedOffer, 'ranking.score'),
            'full_payload_json' => $payload,
            'valid_from' => now(),
            'valid_until' => null,
            'created_at' => now(),
        ]);

        ArchitectureMetrics::recordSnapshotCreated();

        return OfferSnapshotData::fromModel($snapshot);
    }

    public function createFromProduct(Product $product, ?string $selectedBy = 'checkout'): OfferSnapshotData
    {
        $product->loadMissing(['shop', 'provider', 'brand']);
        $entitlement = $this->resolveEntitlementForProduct($product);
        $context = $this->resolveProviderContext($product);
        $provider = $context['provider'];

        if (! $provider) {
            throw ValidationException::withMessages([
                'provider' => 'Cannot pin offer snapshot without a resolved provider.',
            ]);
        }

        return $this->createFromRuntimeContext(
            entitlement: $entitlement,
            productId: (int) $product->id,
            providerId: (int) $provider->id,
            sku: (string) $product->sku,
            selectedBy: $selectedBy,
        );
    }

    public function getOrFail(string $snapshotId): OfferSnapshotData
    {
        $snapshot = OfferSnapshot::query()->findOrFail($snapshotId);

        return OfferSnapshotData::fromModel($snapshot);
    }

    public function serviceSkuFromSnapshot(OfferSnapshotData $snapshot): string
    {
        return (string) (
            data_get($snapshot->fullPayloadJson, 'service_sku')
            ?: $snapshot->providerSku
        );
    }

    public function catalogSkuFromSnapshot(OfferSnapshotData $snapshot): string
    {
        return (string) (
            data_get($snapshot->fullPayloadJson, 'catalog_sku')
            ?: $snapshot->sku
        );
    }

    private function resolveEntitlementForProduct(Product $product): CanonicalProductIdentity
    {
        $fromSource = CanonicalProductIdentitySource::query()
            ->where('source_type', CanonicalProductIdentitySource::SOURCE_PRODUCT)
            ->where('source_id', $product->id)
            ->first();

        if ($fromSource) {
            return CanonicalProductIdentity::query()->findOrFail($fromSource->canonical_product_identity_id);
        }

        $fromBestOffer = CanonicalProductIdentity::query()
            ->where('best_offer_product_id', $product->id)
            ->first();

        if ($fromBestOffer) {
            return $fromBestOffer;
        }

        $identityPayload = $this->identityService->forProduct($product);
        $fingerprint = (string) ($identityPayload['fingerprint'] ?? '');

        $existing = CanonicalProductIdentity::query()
            ->where('fingerprint', $fingerprint)
            ->first();

        if ($existing) {
            return $existing;
        }

        return CanonicalProductIdentity::query()->create([
            'fingerprint' => $fingerprint,
            'identity_slug' => (string) Str::slug((string) ($identityPayload['title'] ?? $product->sku)).'-'.$product->id,
            'canonical_category' => (string) ($identityPayload['canonical_category'] ?? 'gift_cards'),
            'brand' => data_get($identityPayload, 'brand.value'),
            'product_family' => data_get($identityPayload, 'brand.value'),
            'face_value' => data_get($identityPayload, 'face_value.amount'),
            'face_value_currency' => data_get($identityPayload, 'face_value.currency'),
            'region' => data_get($identityPayload, 'region.value'),
            'platform' => $identityPayload['platform'] ?? null,
            'confidence' => 'runtime',
            'signals' => $identityPayload,
            'provider_candidates_count' => 0,
            'seller_offers_count' => 1,
            'best_offer_product_id' => $product->id,
            'last_seen_at' => now(),
        ]);
    }

    /**
     * @return array{
     *     catalog_sku: string,
     *     service_sku: string,
     *     catalog: ?WildflowCatalog,
     *     provider_product: ?ProviderProduct,
     *     provider: ?Provider
     * }
     */
    private function resolveProviderContext(Product $product): array
    {
        $catalogSku = (string) ($product->wildflow_catalog_sku ?: $product->sku);
        $catalog = WildflowCatalog::query()->where('sku', $catalogSku)->first();
        $providerProduct = $this->providerProductForProduct($product, $catalogSku, $catalog);
        $provider = $this->providerFor($product, $providerProduct, $catalogSku);
        $serviceSku = $this->serviceSku($catalog, $providerProduct, $catalogSku);

        return [
            'catalog_sku' => $catalogSku,
            'service_sku' => $serviceSku,
            'catalog' => $catalog,
            'provider_product' => $providerProduct,
            'provider' => $provider,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function rankedOfferForProduct(Product $product, ?ProviderProduct $providerProduct): ?array
    {
        if ($providerProduct) {
            return $this->offerRanking
                ->rankedOffersForProviderProduct($providerProduct)
                ->first(fn (array $offer) => (int) ($offer['product_id'] ?? 0) === (int) $product->id)
                ?? $this->offerRanking->rankedOffersForProviderProduct($providerProduct)->first();
        }

        return $this->offerRanking
            ->rankedOffersForProducts(collect([$product]))
            ->first();
    }

    private function offerKind(Product $product): string
    {
        try {
            if ((int) $product->shop_id === (int) $this->storefront->shop()->id) {
                return OfferSnapshot::KIND_FIRST_PARTY;
            }
        } catch (\Throwable) {
            // Shop resolution may fail in isolated tests.
        }

        if ($product->provider_id) {
            return OfferSnapshot::KIND_PROVIDER_SUPPLY;
        }

        return OfferSnapshot::KIND_SELLER_LISTING;
    }

    private function providerProductForProduct(Product $product, string $catalogSku, ?WildflowCatalog $catalog = null): ?ProviderProduct
    {
        $providerProductId = data_get($product->data, 'provider_product_id')
            ?? data_get($product->data, 'source_provider_product_id')
            ?? data_get($product->params, 'provider_product_id')
            ?? data_get($product->params, 'source_provider_product_id');

        if ($providerProductId) {
            $direct = ProviderProduct::query()->whereKey($providerProductId)->first();
            if ($direct) {
                return $direct;
            }
        }

        $candidateSkus = collect([
            $catalogSku,
            $product->wildflow_catalog_sku,
            $product->sku,
            $catalog?->service_sku,
            data_get($product->params, 'wf_provider_sku'),
        ])
            ->map(fn ($sku) => trim((string) $sku))
            ->filter()
            ->unique()
            ->values();

        if ($candidateSkus->isEmpty()) {
            return null;
        }

        $blindIndexes = $candidateSkus
            ->map(fn (string $sku) => $this->vault->computeBlindIndex($sku))
            ->all();

        $query = ProviderProduct::query()
            ->where(function ($q) use ($blindIndexes) {
                $q->whereIn('market_sku_bidx', $blindIndexes)
                    ->orWhereIn('sku_bidx', $blindIndexes);
            });

        if ($product->provider_id) {
            $query->orderByRaw('case when provider_id = '.(int) $product->provider_id.' then 0 else 1 end');
        }

        return $query->first();
    }

    private function providerFor(Product $product, ?ProviderProduct $providerProduct, string $catalogSku): ?Provider
    {
        if ($product->provider) {
            return $product->provider;
        }

        if ($providerProduct?->provider) {
            return $providerProduct->provider;
        }

        $catalog = WildflowCatalog::query()->where('sku', $catalogSku)->first();
        if ($catalog?->provider) {
            return $catalog->provider;
        }

        return Provider::query()
            ->whereIn('type', ['wildflow-sandbox', 'wildflow'])
            ->where('is_active', true)
            ->orderByRaw("case when type = 'wildflow-sandbox' then 0 else 1 end")
            ->first();
    }

    private function serviceSku(?WildflowCatalog $catalog, ?ProviderProduct $providerProduct, string $catalogSku): string
    {
        $serviceSku = data_get($catalog?->data, 'service_sku')
            ?? data_get($catalog?->data, 'data.sku')
            ?? data_get($catalog?->data, 'data.product.sku')
            ?? data_get($catalog?->data, 'product.sku')
            ?? $catalog?->service_sku
            ?? data_get($providerProduct?->data, 'service_sku')
            ?? data_get($providerProduct?->data, 'data.sku')
            ?? data_get($providerProduct?->data, 'data.product.sku')
            ?? data_get($providerProduct?->data, 'product.sku')
            ?? $providerProduct?->sku
            ?? $catalogSku;

        return (string) $serviceSku;
    }
}
