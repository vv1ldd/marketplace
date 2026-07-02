<?php

namespace App\Services;

use App\Models\CanonicalProductIdentity;
use App\Models\CanonicalProductIdentityOverride;
use App\Models\CanonicalProductIdentitySource;
use App\Models\Product;
use App\Models\ProviderProduct;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CanonicalProductIdentityIndexService
{
    /**
     * @var array<string, string>
     */
    private array $reservedSlugs = [];

    public function __construct(
        private readonly CanonicalProductIdentityService $identity,
        private readonly ProviderNetworkCatalogService $network,
        private readonly MeanlyFirstPartyStorefrontService $storefront,
    ) {}

    /**
     * @return array<string, int|bool>
     */
    public function rebuild(?int $limit = null, bool $dryRun = false): array
    {
        $stats = [
            'missing_tables' => false,
            'dry_run' => $dryRun,
            'identities_touched' => 0,
            'provider_sources' => 0,
            'seller_sources' => 0,
            'skipped_no_fingerprint' => 0,
            'low_confidence_sources' => 0,
        ];

        /** @var array<string, array<string, mixed>> $groups */
        $groups = [];
        $this->collectProviderSources($groups, $stats, $limit);
        $this->collectSellerSources($groups, $stats, $limit);

        $stats['identity_groups'] = count($groups);

        if ($dryRun) {
            $stats['identities_touched'] = count($groups);

            return $stats;
        }

        if (! $this->tablesExist()) {
            $stats['missing_tables'] = true;

            return $stats;
        }

        $this->reservedSlugs = [];
        $previousIdentityIds = [];

        DB::transaction(function () use ($groups, &$stats, &$previousIdentityIds): void {
            ksort($groups);

            foreach ($groups as $fingerprint => $group) {
                $representativeIdentity = $this->representativeIdentity(collect($group['identities']));
                $identityModel = CanonicalProductIdentity::query()->firstOrNew([
                    'fingerprint' => $fingerprint,
                ]);

                $identityModel->fill([
                    'identity_slug' => $this->persistedSlug($representativeIdentity, $fingerprint),
                    'canonical_category' => $representativeIdentity['canonical_category'] ?? null,
                    'discovery_intent' => $representativeIdentity['discovery_intent'] ?? null,
                    'brand' => $representativeIdentity['brand'] ?? null,
                    'product_family' => $representativeIdentity['product_family'] ?? null,
                    'face_value' => $representativeIdentity['face_value'] ?? null,
                    'face_value_currency' => $representativeIdentity['face_value_currency'] ?? null,
                    'region' => $representativeIdentity['region'] ?? null,
                    'platform' => $representativeIdentity['platform'] ?? null,
                    'confidence' => $representativeIdentity['confidence'] ?? null,
                    'signals' => $representativeIdentity['signals'] ?? [],
                    'provider_candidates_count' => count($group['provider_ids']),
                    'seller_offers_count' => count($group['product_ids']),
                    'best_offer_product_id' => $this->bestOfferProductId($group['product_ids']),
                    'last_seen_at' => now(),
                ]);
                $identityModel->save();
                $this->linkOverrideToIdentity($identityModel);
                $stats['identities_touched']++;

                foreach ($group['sources'] as $source) {
                    $existingSource = CanonicalProductIdentitySource::query()
                        ->where('source_type', $source['source_type'])
                        ->where('source_id', $source['source_id'])
                        ->first();

                    if (
                        $existingSource !== null
                        && (int) $existingSource->canonical_product_identity_id !== (int) $identityModel->id
                    ) {
                        $previousIdentityIds[] = (int) $existingSource->canonical_product_identity_id;
                    }

                    CanonicalProductIdentitySource::query()->updateOrCreate(
                        [
                            'source_type' => $source['source_type'],
                            'source_id' => $source['source_id'],
                        ],
                        [
                            'canonical_product_identity_id' => $identityModel->id,
                            'source_sku' => $source['source_sku'],
                            'confidence' => $source['confidence'],
                            'signals' => $source['signals'],
                            'last_seen_at' => now(),
                        ],
                    );
                }
            }

            collect($previousIdentityIds)
                ->unique()
                ->each(fn (int $identityId) => $this->refreshCountsFromSources($identityId));
        });

        return $stats;
    }

    /**
     * @param  array<string, array<string, mixed>>  $groups
     * @param  array<string, int|bool>  $stats
     */
    private function collectProviderSources(array &$groups, array &$stats, ?int $limit): void
    {
        $query = $this->network->candidatesQuery()
            ->with(['brand', 'region', 'provider'])
            ->orderBy('id');

        if ($limit !== null) {
            foreach ($query->limit(max(1, $limit))->get() as $product) {
                $this->addProviderSource($groups, $stats, $product);
            }

            return;
        }

        $query->chunkById(500, function (Collection $products) use (&$groups, &$stats): void {
            foreach ($products as $product) {
                $this->addProviderSource($groups, $stats, $product);
            }
        });
    }

    /**
     * @param  array<string, array<string, mixed>>  $groups
     * @param  array<string, int|bool>  $stats
     */
    private function collectSellerSources(array &$groups, array &$stats, ?int $limit): void
    {
        $query = $this->storefront->marketplaceProductsQuery()
            ->with(['brand', 'provider', 'shop.legalEntity', 'salesChannels'])
            ->orderBy('id');

        if ($limit !== null) {
            foreach ($query->limit(max(1, $limit))->get() as $product) {
                $this->addSellerSource($groups, $stats, $product);
            }

            return;
        }

        $query->chunkById(500, function (Collection $products) use (&$groups, &$stats): void {
            foreach ($products as $product) {
                $this->addSellerSource($groups, $stats, $product);
            }
        });
    }

    /**
     * @param  array<string, array<string, mixed>>  $groups
     * @param  array<string, int|bool>  $stats
     */
    private function addProviderSource(array &$groups, array &$stats, ProviderProduct $product): void
    {
        $this->addSource(
            groups: $groups,
            stats: $stats,
            identity: $this->identity->forProviderProduct($product),
            sourceType: CanonicalProductIdentitySource::SOURCE_PROVIDER_PRODUCT,
            sourceId: (int) $product->id,
            sourceSku: $this->sourceSku($product->sku ?: $product->market_sku),
        );
    }

    /**
     * @param  array<string, array<string, mixed>>  $groups
     * @param  array<string, int|bool>  $stats
     */
    private function addSellerSource(array &$groups, array &$stats, Product $product): void
    {
        $this->addSource(
            groups: $groups,
            stats: $stats,
            identity: $this->identity->forProduct($product),
            sourceType: CanonicalProductIdentitySource::SOURCE_PRODUCT,
            sourceId: (int) $product->id,
            sourceSku: $this->sourceSku($product->sku),
        );
    }

    /**
     * @param  array<string, array<string, mixed>>  $groups
     * @param  array<string, int|bool>  $stats
     * @param  array<string, mixed>  $identity
     */
    private function addSource(
        array &$groups,
        array &$stats,
        array $identity,
        string $sourceType,
        int $sourceId,
        ?string $sourceSku,
    ): void {
        $fingerprint = trim((string) ($identity['fingerprint'] ?? ''));
        if ($fingerprint === '') {
            $stats['skipped_no_fingerprint']++;

            return;
        }

        if (($identity['confidence'] ?? null) === 'low') {
            $stats['low_confidence_sources']++;
        }

        $groups[$fingerprint] ??= [
            'identities' => [],
            'sources' => [],
            'provider_ids' => [],
            'product_ids' => [],
        ];

        $sourceKey = $sourceType.':'.$sourceId;
        $groups[$fingerprint]['identities'][] = $identity;
        $groups[$fingerprint]['sources'][$sourceKey] = [
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'source_sku' => $sourceSku,
            'confidence' => $identity['confidence'] ?? null,
            'signals' => $identity['signals'] ?? [],
        ];

        if ($sourceType === CanonicalProductIdentitySource::SOURCE_PROVIDER_PRODUCT) {
            $groups[$fingerprint]['provider_ids'][$sourceId] = $sourceId;
            $stats['provider_sources']++;

            return;
        }

        $groups[$fingerprint]['product_ids'][$sourceId] = $sourceId;
        $stats['seller_sources']++;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $identities
     * @return array<string, mixed>
     */
    private function representativeIdentity(Collection $identities): array
    {
        return $identities
            ->sortBy(fn (array $identity) => [
                'high' => 0,
                'medium' => 1,
                'low' => 2,
            ][$identity['confidence'] ?? 'low'] ?? 3)
            ->first();
    }

    /**
     * @param  array<int, int>  $productIds
     */
    private function bestOfferProductId(array $productIds): ?int
    {
        if ($productIds === []) {
            return null;
        }

        $productId = Product::query()
            ->whereKey(array_values($productIds))
            ->where('is_active', true)
            ->whereHas('shop', fn ($query) => $query->where('is_active', true))
            ->orderByRaw('CASE WHEN price_rub > 0 THEN 0 ELSE 1 END')
            ->orderBy('price_rub')
            ->value('id');

        return $productId !== null ? (int) $productId : null;
    }

    /**
     * @param  array<string, mixed>  $identity
     */
    private function persistedSlug(array $identity, string $fingerprint): string
    {
        $base = trim((string) ($identity['identity_slug'] ?? ''));
        $base = $base !== '' ? $base : 'product-'.substr($fingerprint, -8);
        $base = Str::limit($base, 150, '');

        $candidate = $base;
        $suffix = substr(Str::after($fingerprint, 'cp_'), 0, 8) ?: substr($fingerprint, -8);
        $attempt = 0;

        while ($this->slugBelongsToAnotherFingerprint($candidate, $fingerprint)) {
            $attempt++;
            $candidateSuffix = $attempt === 1 ? $suffix : $suffix.'-'.$attempt;
            $candidate = Str::limit($base, 159 - strlen($candidateSuffix), '').'-'.$candidateSuffix;
        }

        $this->reservedSlugs[$candidate] = $fingerprint;

        return $candidate;
    }

    private function slugBelongsToAnotherFingerprint(string $slug, string $fingerprint): bool
    {
        if (isset($this->reservedSlugs[$slug]) && $this->reservedSlugs[$slug] !== $fingerprint) {
            return true;
        }

        return CanonicalProductIdentity::query()
            ->where('identity_slug', $slug)
            ->where('fingerprint', '!=', $fingerprint)
            ->exists();
    }

    private function refreshCountsFromSources(int $identityId): void
    {
        $identity = CanonicalProductIdentity::query()->find($identityId);
        if (! $identity) {
            return;
        }

        $sources = $identity->sources()->get(['source_type', 'source_id']);
        $productIds = $sources
            ->where('source_type', CanonicalProductIdentitySource::SOURCE_PRODUCT)
            ->pluck('source_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $identity->forceFill([
            'provider_candidates_count' => $sources->where('source_type', CanonicalProductIdentitySource::SOURCE_PROVIDER_PRODUCT)->unique('source_id')->count(),
            'seller_offers_count' => count($productIds),
            'best_offer_product_id' => $this->bestOfferProductId($productIds),
        ])->save();
    }

    private function tablesExist(): bool
    {
        return Schema::hasTable('canonical_product_identities')
            && Schema::hasTable('canonical_product_identity_sources');
    }

    private function linkOverrideToIdentity(CanonicalProductIdentity $identity): void
    {
        if (! Schema::hasTable('canonical_product_identity_overrides')) {
            return;
        }

        CanonicalProductIdentityOverride::query()
            ->where('fingerprint', $identity->fingerprint)
            ->where(function ($query) use ($identity): void {
                $query->whereNull('canonical_product_identity_id')
                    ->orWhere('canonical_product_identity_id', '!=', $identity->id);
            })
            ->update(['canonical_product_identity_id' => $identity->id]);
    }

    private function sourceSku(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? Str::limit($value, 255, '') : null;
    }
}
