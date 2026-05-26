<?php

namespace App\Services;

use App\Http\Services\YmService;
use App\Models\Product;
use App\Models\ProductSalesChannel;
use App\Models\Shop;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class MeanlyCatalogReconciliationService
{
    public function __construct(
        private readonly MeanlyFirstPartyStorefrontService $storefront,
    ) {}

    /**
     * @param array<int, array<string, mixed>>|null $yandexOffers
     * @return array<string, mixed>
     */
    public function reconcile(?Shop $shop = null, ?array $yandexOffers = null, bool $createMissing = false): array
    {
        $shop ??= $this->storefront->shop();
        $errors = [];
        $localProducts = Product::query()
            ->where('shop_id', $shop->id)
            ->get();

        $localBySku = $localProducts->keyBy(fn (Product $product) => (string) $product->sku);
        $offers = $this->normalizeYandexOffers($yandexOffers ?? $this->fetchYandexOffers($shop, $errors));
        $offersBySku = $offers->keyBy('offer_id');

        $this->ensureDefaultChannels($shop, $localProducts);

        $missingLocal = $offers
            ->reject(fn (array $offer) => $localBySku->has($offer['offer_id']))
            ->values();

        if ($createMissing) {
            $missingLocal->each(fn (array $offer) => $this->createDraftProductFromYandexOffer($shop, $offer));
        }

        $missingYandex = $localProducts
            ->reject(fn (Product $product) => $offersBySku->has((string) $product->sku))
            ->map(fn (Product $product) => [
                'product_id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
            ])
            ->values();

        $priceMismatches = $localProducts
            ->filter(function (Product $product) use ($offersBySku) {
                $offer = $offersBySku->get((string) $product->sku);
                if (! $offer || $offer['price_rub'] === null) {
                    return false;
                }

                return abs(((float) $product->price_rub / 100) - (float) $offer['price_rub']) >= 1;
            })
            ->map(fn (Product $product) => [
                'product_id' => $product->id,
                'sku' => $product->sku,
                'local_price_rub' => round((float) $product->price_rub / 100, 2),
                'yandex_price_rub' => round((float) $offersBySku->get((string) $product->sku)['price_rub'], 2),
            ])
            ->values();

        $summary = [
            'shop_id' => $shop->id,
            'local_products' => $localProducts->count(),
            'yandex_offers' => $offers->count(),
            'missing_local_count' => $missingLocal->count(),
            'missing_yandex_count' => $missingYandex->count(),
            'price_mismatch_count' => $priceMismatches->count(),
            'created_drafts' => $createMissing ? $missingLocal->count() : 0,
            'errors' => $errors,
            'missing_local' => $missingLocal->take(25)->values()->all(),
            'missing_yandex' => $missingYandex->take(25)->values()->all(),
            'price_mismatches' => $priceMismatches->take(25)->values()->all(),
        ];

        $this->recordReconciliation($shop, $summary);

        return $summary;
    }

    /**
     * @param Collection<int, Product> $products
     */
    private function ensureDefaultChannels(Shop $shop, Collection $products): void
    {
        $channels = [$this->storefront->storefrontChannel()];
        if ($shop->isYandexMarketActive()) {
            $channels[] = $this->storefront->yandexChannel();
        }

        foreach ($products as $product) {
            foreach ($channels as $channel) {
                ProductSalesChannel::query()->updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'shop_id' => $shop->id,
                        'channel' => $channel,
                    ],
                    [
                        'is_enabled' => true,
                        'last_error' => null,
                    ]
                );
            }
        }
    }

    /**
     * @param array<int, array<string, mixed>> $errors
     * @return array<int, array<string, mixed>>
     */
    private function fetchYandexOffers(Shop $shop, array &$errors): array
    {
        try {
            $service = app()->makeWith(YmService::class, ['shop' => $shop]);
            $offers = [];
            $pageToken = null;

            do {
                $response = $service->getOfferMappings($pageToken);
                $offers = array_merge($offers, $response['offerMappings'] ?? $response['offers'] ?? []);
                $pageToken = $response['paging']['nextPageToken'] ?? null;
            } while ($pageToken);

            return $offers;
        } catch (\Throwable $e) {
            $errors[] = [
                'source' => 'yandex_market',
                'message' => $e->getMessage(),
            ];
            Log::warning('Meanly Yandex reconciliation failed to fetch offers', [
                'shop_id' => $shop->id,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @param array<int, array<string, mixed>> $offers
     * @return Collection<int, array<string, mixed>>
     */
    private function normalizeYandexOffers(array $offers): Collection
    {
        return collect($offers)
            ->map(function (array $row) {
                $offer = data_get($row, 'offer', $row);
                $offerId = data_get($offer, 'offerId')
                    ?? data_get($offer, 'offer_id')
                    ?? data_get($row, 'offerId')
                    ?? data_get($row, 'shopSku')
                    ?? data_get($row, 'mapping.marketSku');

                if (! $offerId) {
                    return null;
                }

                $price = data_get($offer, 'basicPrice.value')
                    ?? data_get($offer, 'basic_price.value')
                    ?? data_get($row, 'price.value')
                    ?? data_get($row, 'price');

                return [
                    'offer_id' => (string) $offerId,
                    'name' => (string) (data_get($offer, 'name') ?? data_get($row, 'name') ?? $offerId),
                    'price_rub' => $price !== null ? (float) $price : null,
                    'raw_status' => data_get($row, 'mapping.state') ?? data_get($row, 'status'),
                ];
            })
            ->filter()
            ->unique('offer_id')
            ->values();
    }

    /**
     * @param array<string, mixed> $offer
     */
    private function createDraftProductFromYandexOffer(Shop $shop, array $offer): void
    {
        $product = Product::query()
            ->where('shop_id', $shop->id)
            ->where('sku', $offer['offer_id'])
            ->first();

        if ($product) {
            return;
        }

        $canonicalCategory = app(CanonicalCategoryResolver::class)->fromPayload($offer, [
            $offer['name'] ?? null,
        ]);

        $product = Product::create([
            'shop_id' => $shop->id,
            'sku' => $offer['offer_id'],
            'name' => $offer['name'],
            'price_rub' => $offer['price_rub'] !== null ? (int) round(((float) $offer['price_rub']) * 100) : 0,
            'type' => 'giftcard',
            'category' => app(CanonicalCategoryResolver::class)->label($canonicalCategory),
            'canonical_category' => $canonicalCategory,
            'is_active' => false,
            'data' => [
                'source' => 'yandex_market_reconciliation',
                'raw_status' => $offer['raw_status'] ?? null,
            ],
        ]);

        ProductSalesChannel::query()->updateOrCreate(
            [
                'product_id' => $product->id,
                'shop_id' => $shop->id,
                'channel' => $this->storefront->yandexChannel(),
            ],
            ['is_enabled' => false]
        );
    }

    /**
     * @param array<string, mixed> $summary
     */
    private function recordReconciliation(Shop $shop, array $summary): void
    {
        try {
            app(LedgerService::class)->record($shop, 'MEANLY_CATALOG_RECONCILED', $shop, $summary, $shop->legalEntity);
            if ($shop->legalEntity) {
                app(TokenMeteringService::class)->meter($shop->legalEntity, 'catalog_sync', $shop, 1, $shop, [
                    'idempotency_key' => 'meanly-catalog-sync:'.$shop->id.':'.now()->toDateString(),
                    'missing_local_count' => $summary['missing_local_count'],
                    'missing_yandex_count' => $summary['missing_yandex_count'],
                    'price_mismatch_count' => $summary['price_mismatch_count'],
                ]);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
