<?php

namespace App\Services;

use App\Models\CanonicalProductIdentity;
use App\Models\CanonicalProductIdentitySource;
use App\Models\Product;
use App\Models\ProviderProduct;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CanonicalProductPageService
{
    private const SLUG_LOOKUP_LIMIT = 2000;
    private const FALLBACK_LOOKUP_LIMIT = 1000;
    private const TOKEN_LOOKUP_LIMIT = 1000;
    private const PAGE_CANDIDATE_LIMIT = 20;
    private const OFFER_CANDIDATE_LIMIT = 20;
    private const SITEMAP_CANDIDATE_LIMIT = 200;

    public function __construct(
        private readonly ProviderNetworkCatalogService $network,
        private readonly CanonicalProductIdentityService $identity,
        private readonly CanonicalCategoryResolver $categoryResolver,
        private readonly SellerOfferRankingService $offerRanking,
        private readonly ProductIntentResolutionService $intentResolver,
        private readonly MeanlyFirstPartyStorefrontService $storefront,
        private readonly ProductIndexingPolicyService $indexingPolicy,
        private readonly CanonicalProductIdentityCurationService $curation,
    ) {}

    /**
     * Resolve a canonical product page from the deterministic identity slug.
     *
     * Persisted identity rows are preferred when available. Bounded provider
     * scans remain the fallback so older environments keep resolving pages.
     *
     * @return array<string, mixed>|null
     */
    public function resolveBySlug(string $identitySlug, ?string $intent = null, ?int $selectedOfferProductId = null): ?array
    {
        $identitySlug = $this->normalizeIdentitySlug($identitySlug);
        if ($identitySlug === '') {
            return null;
        }

        $persistedIdentity = $this->persistedIdentityBySlug($identitySlug);
        if ($persistedIdentity !== null) {
            $matches = $this->providerCandidatesForPersistedIdentity($persistedIdentity);
            return $this->factsForCandidates($matches, $intent, $persistedIdentity, $selectedOfferProductId);
        }

        $matches = $this->matchingCandidates(identitySlug: $identitySlug);
        if ($matches->isEmpty()) {
            return null;
        }

        return $this->factsForCandidates($matches, $intent, null, $selectedOfferProductId);
    }

    /**
     * Internal resolver for callers that already know the computed fingerprint.
     *
     * @return array<string, mixed>|null
     */
    public function resolveByFingerprint(string $fingerprint, ?string $intent = null): ?array
    {
        $fingerprint = trim($fingerprint);
        if ($fingerprint === '') {
            return null;
        }

        $persistedIdentity = $this->persistedIdentityByFingerprint($fingerprint);
        if ($persistedIdentity !== null) {
            $matches = $this->providerCandidatesForPersistedIdentity($persistedIdentity);
            return $this->factsForCandidates($matches, $intent, $persistedIdentity);
        }

        $matches = $this->matchingCandidates(fingerprint: $fingerprint);
        if ($matches->isEmpty()) {
            return null;
        }

        return $this->factsForCandidates($matches, $intent);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function sitemapEntries(int $candidateLimit = self::SITEMAP_CANDIDATE_LIMIT, bool $includeLlmOnly = false): Collection
    {
        $persistedEntries = $this->persistedSitemapEntries($candidateLimit, $includeLlmOnly);
        if ($persistedEntries !== null && $persistedEntries->isNotEmpty()) {
            return $persistedEntries;
        }

        $products = $this->candidateDiscoveryQuery(null)
            ->latest('updated_at')
            ->limit(max(1, $candidateLimit))
            ->get();
        $productsById = $products->keyBy('id');

        return $this->identity->groupProviderProducts($products)
            ->map(function (array $group) use ($productsById, $includeLlmOnly) {
                $candidates = collect($group['candidate_ids'] ?? [])
                    ->map(fn ($id) => $productsById->get($id))
                    ->filter(fn ($product) => $product instanceof ProviderProduct)
                    ->values();
                $policy = $this->indexingPolicy->forCanonicalProduct(
                    data_get($group, 'canonical_identity', $group),
                    null,
                    [
                        'provider_seo_quality' => $this->providerSeoQuality($candidates),
                        'provider_candidates_count' => $candidates->count(),
                    ],
                );

                $group['indexing_policy'] = $policy;
                $group['include_in_sitemap'] = $this->includePolicyInSitemap($policy, $includeLlmOnly);

                return $group;
            })
            ->filter(fn (array $group) => (bool) ($group['include_in_sitemap'] ?? false))
            ->map(function (array $group) use ($productsById) {
                $lastModified = collect($group['candidate_ids'] ?? [])
                    ->map(fn ($id) => $productsById->get($id)?->updated_at)
                    ->filter()
                    ->max();

                return [
                    'loc' => route('meanly.canonical-products.show', $group['identity_slug']),
                    'lastmod' => optional($lastModified)->toAtomString(),
                    'changefreq' => 'weekly',
                    'priority' => $group['confidence'] === 'high' ? '0.58' : '0.5',
                ];
            })
            ->values();
    }

    /**
     * @param  Collection<int, ProviderProduct>  $products
     * @return array<string, mixed>
     */
    private function factsForCandidates(
        Collection $products,
        ?string $intent,
        ?CanonicalProductIdentity $persistedIdentity = null,
        ?int $selectedOfferProductId = null,
    ): array
    {
        $products = $products
            ->unique('id')
            ->values();
        $canonicalIdentity = $this->canonicalIdentityForFacts($products, $persistedIdentity);
        $identitySlug = (string) $canonicalIdentity['identity_slug'];
        $url = route('meanly.canonical-products.show', $identitySlug);
        $machineReadableAt = route('llms.catalog.canonical-products.show', $identitySlug);
        $sellerOffers = $this->canonicalSellerOfferUrls(
            $this->aggregatedSellerOffers($products, $persistedIdentity),
            $identitySlug,
            $intent,
        );
        $providerSeoQuality = $this->providerSeoQuality($products);
        $pageCandidate = [
            'id' => $canonicalIdentity['fingerprint'],
            'url' => $url,
            'name' => $this->title($canonicalIdentity, $products),
            'canonical_category' => $canonicalIdentity['canonical_category'],
            'canonical_category_label' => $this->categoryResolver->label((string) $canonicalIdentity['canonical_category']),
            'canonical_identity' => $canonicalIdentity,
            'machine_readable_at' => $machineReadableAt,
            'seller_offers_machine_readable_at' => $machineReadableAt,
        ];
        $intentResolution = $this->intentResolver->resolveCanonicalProduct($pageCandidate, $intent, $sellerOffers);
        $intentResolution = $this->selectOfferForCanonicalPage($intentResolution, $sellerOffers, $selectedOfferProductId);
        $indexing = $this->indexingPolicy->forCanonicalProduct(
            $canonicalIdentity,
            $intentResolution['selected_offer'] ?? null,
            [
                'provider_seo_quality' => $providerSeoQuality,
                'provider_candidates_count' => $products->count(),
            ],
            $persistedIdentity,
        );

        $intentResolution['url'] = $this->intentUrl($identitySlug, (string) $intentResolution['intent']);
        $intentResolution['machine_readable_at'] = route('llms.catalog.canonical-products.intents.show', [
            'identitySlug' => $identitySlug,
            'intent' => $intentResolution['intent'],
        ]);
        $intentResolution['candidate']['url'] = $intentResolution['url'];
        $intentResolution['candidate']['machine_readable_at'] = $intentResolution['machine_readable_at'];
        $intentResolution['indexing_policy'] = $indexing;
        $intentResolution['indexing'] = $indexing;

        return [
            'type' => 'CanonicalProductPage',
            'canonical_identity' => $canonicalIdentity,
            'url' => $url,
            'machine_readable_at' => $machineReadableAt,
            'intent_url' => $intentResolution['url'],
            'name' => $pageCandidate['name'],
            'description' => $this->description($canonicalIdentity, $products, $sellerOffers),
            'canonical_category' => $canonicalIdentity['canonical_category'],
            'canonical_category_label' => $pageCandidate['canonical_category_label'],
            'brand' => $canonicalIdentity['brand'],
            'region' => $canonicalIdentity['region'] ?: 'global',
            'face_value' => $canonicalIdentity['face_value'],
            'face_value_currency' => $canonicalIdentity['face_value_currency'],
            'provider_candidates' => [
                'count' => $products->count(),
                'source_count' => $products->pluck('provider_id')->filter()->unique()->count(),
                'listed_count' => min($products->count(), self::PAGE_CANDIDATE_LIMIT),
                'lookup_limit' => self::SLUG_LOOKUP_LIMIT,
                'persisted_count' => $persistedIdentity?->provider_candidates_count,
                'seo_quality' => $providerSeoQuality,
                'candidates' => $products
                    ->take(self::PAGE_CANDIDATE_LIMIT)
                    ->map(fn (ProviderProduct $product) => $this->providerCandidateSummary($product, $canonicalIdentity))
                    ->values(),
            ],
            'seller_offers' => [
                'count' => $sellerOffers->count(),
                'persisted_count' => $persistedIdentity?->seller_offers_count,
                'persisted_best_offer_product_id' => $persistedIdentity?->best_offer_product_id,
                'best_offer' => $sellerOffers->first(),
                'offers' => $sellerOffers,
                'ranking_method' => 'aggregated_price_stock_sales_seller_reliability_v1',
                'deduplication' => 'seller_product_id_or_sku',
            ],
            'intent_resolution' => $intentResolution,
            'indexing_policy' => $indexing,
            'indexing' => $indexing,
        ];
    }

    /**
     * @return Collection<int, ProviderProduct>
     */
    private function matchingCandidates(?string $identitySlug = null, ?string $fingerprint = null): Collection
    {
        $category = $identitySlug !== null ? $this->categoryFromIdentitySlug($identitySlug) : null;
        $matches = $this->filterCandidatesByIdentity(
            $this->candidateDiscoveryQuery($category)
                ->limit(self::SLUG_LOOKUP_LIMIT)
                ->get(),
            $identitySlug,
            $fingerprint,
        );

        if ($matches->isEmpty() && $category !== null) {
            $matches = $this->filterCandidatesByIdentity(
                $this->candidateDiscoveryQuery(null)
                    ->limit(self::FALLBACK_LOOKUP_LIMIT)
                    ->get(),
                $identitySlug,
                $fingerprint,
            );
        }

        if ($matches->isEmpty() && $identitySlug !== null) {
            $matches = $this->filterCandidatesByIdentity(
                $this->tokenSearchQuery($identitySlug, $category)
                    ->limit(self::TOKEN_LOOKUP_LIMIT)
                    ->get(),
                $identitySlug,
                $fingerprint,
            );
        }

        return $matches->unique('id')->values();
    }

    private function persistedIdentityBySlug(string $identitySlug): ?CanonicalProductIdentity
    {
        if (! $this->identityTablesExist()) {
            return null;
        }

        return CanonicalProductIdentity::query()
            ->where('identity_slug', $identitySlug)
            ->first();
    }

    private function persistedIdentityByFingerprint(string $fingerprint): ?CanonicalProductIdentity
    {
        if (! $this->identityTablesExist()) {
            return null;
        }

        return CanonicalProductIdentity::query()
            ->where('fingerprint', $fingerprint)
            ->first();
    }

    /**
     * @return Collection<int, ProviderProduct>
     */
    private function providerCandidatesForPersistedIdentity(CanonicalProductIdentity $identity): Collection
    {
        $sourceIds = $identity->sources()
            ->where('source_type', CanonicalProductIdentitySource::SOURCE_PROVIDER_PRODUCT)
            ->pluck('source_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($sourceIds->isEmpty()) {
            return collect();
        }

        return $this->candidateDiscoveryQuery($identity->canonical_category)
            ->whereKey($sourceIds->all())
            ->limit(self::SLUG_LOOKUP_LIMIT)
            ->get()
            ->unique('id')
            ->values();
    }

    /**
     * @return Collection<int, Product>
     */
    private function sellerProductsForPersistedIdentity(CanonicalProductIdentity $identity): Collection
    {
        $sourceIds = $identity->sources()
            ->where('source_type', CanonicalProductIdentitySource::SOURCE_PRODUCT)
            ->pluck('source_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($sourceIds->isEmpty()) {
            return collect();
        }

        return $this->storefront->marketplaceProductsQuery()
            ->with(['brand', 'provider', 'shop.legalEntity', 'salesChannels'])
            ->whereKey($sourceIds->all())
            ->get()
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>|null
     */
    private function persistedSitemapEntries(int $candidateLimit, bool $includeLlmOnly): ?Collection
    {
        if (! $this->identityTablesExist()) {
            return null;
        }

        return CanonicalProductIdentity::query()
            ->latest('last_seen_at')
            ->limit(max(1, $candidateLimit))
            ->get()
            ->map(function (CanonicalProductIdentity $identity) {
                $canonicalIdentity = $this->curation->applyApprovedOverrides($identity->toArray(), $identity);
                $selectedOffer = $identity->best_offer_product_id !== null
                    ? ['indexing' => ['indexable' => true]]
                    : null;
                $policy = $this->indexingPolicy->forCanonicalProduct(
                    $canonicalIdentity,
                    $selectedOffer,
                    ['provider_candidates_count' => $identity->provider_candidates_count],
                );

                return [$identity, $canonicalIdentity, $policy];
            })
            ->filter(fn (array $row) => $this->includePolicyInSitemap($row[2], $includeLlmOnly))
            ->map(fn (array $row) => [
                'loc' => route('meanly.canonical-products.show', $row[0]->identity_slug),
                'lastmod' => optional($row[0]->last_seen_at ?: $row[0]->updated_at)->toAtomString(),
                'changefreq' => 'weekly',
                'priority' => ($row[1]['confidence'] ?? null) === 'high' ? '0.58' : '0.5',
            ])
            ->values();
    }

    /**
     * @return Builder<ProviderProduct>
     */
    private function candidateDiscoveryQuery(?string $category): Builder
    {
        return $this->network->candidatesQuery($category)
            ->with('provider')
            ->orderByDesc('brand_id')
            ->latest('updated_at');
    }

    /**
     * @return Builder<ProviderProduct>
     */
    private function tokenSearchQuery(string $identitySlug, ?string $category): Builder
    {
        $tokens = $this->searchTokens($identitySlug);
        $query = $this->candidateDiscoveryQuery($category);

        if ($tokens->isEmpty()) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($tokens) {
            foreach ($tokens->take(4) as $token) {
                $query->orWhere('name', 'like', '%'.$this->escapeLike($token).'%');
            }
        });
    }

    /**
     * @param  Collection<int, ProviderProduct>  $products
     * @return Collection<int, ProviderProduct>
     */
    private function filterCandidatesByIdentity(Collection $products, ?string $identitySlug, ?string $fingerprint): Collection
    {
        return $products
            ->filter(function (ProviderProduct $product) use ($identitySlug, $fingerprint) {
                $identity = $this->identity->forProviderProduct($product);

                if ($fingerprint !== null && $identity['fingerprint'] === $fingerprint) {
                    return true;
                }

                return $identitySlug !== null && $identity['identity_slug'] === $identitySlug;
            })
            ->values();
    }

    /**
     * @param  Collection<int, ProviderProduct>  $products
     * @return Collection<int, array<string, mixed>>
     */
    private function aggregatedSellerOffers(Collection $products, ?CanonicalProductIdentity $persistedIdentity = null): Collection
    {
        $offers = $products
            ->take(self::OFFER_CANDIDATE_LIMIT)
            ->flatMap(function (ProviderProduct $product) {
                return $this->offerRanking->rankedOffersForProviderProduct($product)
                    ->map(function (array $offer) use ($product) {
                        $offer['matched_provider_candidate'] = $this->providerCandidateSummary($product);

                        return $offer;
                    });
            });

        if ($persistedIdentity !== null) {
            $persistedOffers = $this->sellerProductsForPersistedIdentity($persistedIdentity);
            if ($persistedOffers->isNotEmpty()) {
                $offers = $offers->concat(
                    $this->offerRanking->rankedOffersForProducts($persistedOffers)
                        ->map(function (array $offer) {
                            $offer['matched_provider_candidates'] ??= collect();

                            return $offer;
                        })
                );
            }
        }

        return $offers
            ->groupBy(fn (array $offer) => $this->offerDeduplicationKey($offer))
            ->map(function (Collection $duplicates) {
                $best = $duplicates
                    ->sortByDesc(fn (array $offer) => (int) data_get($offer, 'ranking.score', 0))
                    ->first();
                $best['matched_provider_candidates'] = $duplicates
                    ->pluck('matched_provider_candidate')
                    ->filter()
                    ->unique('id')
                    ->values();

                return $best;
            })
            ->sortByDesc(fn (array $offer) => (int) data_get($offer, 'ranking.score', 0))
            ->values()
            ->map(function (array $offer, int $index) {
                $offer['ranking']['position'] = $index + 1;

                return $offer;
            });
    }

    private function canonicalSellerOfferUrls(Collection $offers, string $identitySlug, ?string $intent): Collection
    {
        return $offers
            ->map(function (array $offer) use ($identitySlug, $intent) {
                $query = ['offer' => (int) ($offer['product_id'] ?? 0)];
                if (is_string($intent) && trim($intent) !== '') {
                    $query['intent'] = trim($intent);
                }

                $offer['url'] = route('meanly.canonical-products.show', [
                    'identitySlug' => $identitySlug,
                ] + array_filter($query));

                return $offer;
            })
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $sellerOffers
     * @return array<string, mixed>
     */
    private function selectOfferForCanonicalPage(array $intentResolution, Collection $sellerOffers, ?int $selectedOfferProductId): array
    {
        if (! $selectedOfferProductId) {
            return $intentResolution;
        }

        $selectedOffer = $sellerOffers->first(
            fn (array $offer) => (int) ($offer['product_id'] ?? 0) === $selectedOfferProductId
        );

        if (! is_array($selectedOffer)) {
            return $intentResolution;
        }

        $intentResolution['selected_offer'] = $selectedOffer;
        $intentResolution['alternatives'] = $sellerOffers
            ->reject(fn (array $offer) => (int) ($offer['product_id'] ?? 0) === $selectedOfferProductId)
            ->take(8)
            ->values();
        $intentResolution['decision']['reason'] = 'Selected the seller offer requested by the canonical product page link.';
        $intentResolution['indexing']['reason'] = 'canonical_selected_seller_offer';

        return $intentResolution;
    }

    private function offerDeduplicationKey(array $offer): string
    {
        $productId = (int) ($offer['product_id'] ?? 0);
        if ($productId > 0) {
            return 'product:'.$productId;
        }

        return 'sku:'.Str::lower((string) ($offer['sku'] ?? 'unknown'));
    }

    /**
     * @param  Collection<int, ProviderProduct>  $products
     * @return array<string, mixed>
     */
    private function representativeIdentity(Collection $products): array
    {
        return $products
            ->map(fn (ProviderProduct $product) => $this->identity->forProviderProduct($product))
            ->sortBy(fn (array $identity) => ['high' => 0, 'medium' => 1, 'low' => 2][$identity['confidence']] ?? 3)
            ->first();
    }

    /**
     * @param  Collection<int, ProviderProduct>  $products
     * @return array<string, mixed>
     */
    private function canonicalIdentityForFacts(Collection $products, ?CanonicalProductIdentity $persistedIdentity): array
    {
        if ($persistedIdentity !== null) {
            $computedIdentity = $products->isNotEmpty() ? $this->representativeIdentity($products) : [];
            $canonicalIdentity = array_merge($computedIdentity, [
                'database_id' => $persistedIdentity->id,
                'fingerprint' => $persistedIdentity->fingerprint,
                'identity_slug' => $persistedIdentity->identity_slug,
                'canonical_category' => $persistedIdentity->canonical_category ?: data_get($computedIdentity, 'canonical_category'),
                'brand' => $persistedIdentity->brand ?: data_get($computedIdentity, 'brand'),
                'product_family' => $persistedIdentity->product_family ?: data_get($computedIdentity, 'product_family'),
                'face_value' => $persistedIdentity->face_value !== null ? (float) $persistedIdentity->face_value : data_get($computedIdentity, 'face_value'),
                'face_value_currency' => $persistedIdentity->face_value_currency ?: data_get($computedIdentity, 'face_value_currency'),
                'region' => $persistedIdentity->region ?: data_get($computedIdentity, 'region'),
                'platform' => $persistedIdentity->platform ?: data_get($computedIdentity, 'platform'),
                'confidence' => $persistedIdentity->confidence ?: data_get($computedIdentity, 'confidence'),
                'signals' => $persistedIdentity->signals ?: data_get($computedIdentity, 'signals'),
                'provider_candidates_count' => $persistedIdentity->provider_candidates_count,
                'seller_offers_count' => $persistedIdentity->seller_offers_count,
                'best_offer_product_id' => $persistedIdentity->best_offer_product_id,
            ]);

            return $this->curation->applyApprovedOverrides($canonicalIdentity, $persistedIdentity);
        }

        return $this->representativeIdentity($products);
    }

    /**
     * @return array<string, mixed>
     */
    private function providerCandidateSummary(ProviderProduct $product, ?array $canonicalIdentity = null): array
    {
        $canonicalCategory = $product->canonical_category ?: $this->categoryResolver->forProviderProduct($product);
        $slug = $this->network->publicSlug($product);

        return [
            'id' => $product->id,
            'name' => $product->name,
            'provider_id' => $product->provider_id,
            'provider' => $product->provider?->name ?? $product->provider?->type ?? ('provider-'.$product->provider_id),
            'url' => route('meanly.network.products.show', $slug),
            'machine_readable_at' => route('llms.network.products.show', $slug),
            'canonical_category' => $canonicalCategory,
            'canonical_product_url' => $canonicalIdentity !== null ? route('meanly.canonical-products.show', $canonicalIdentity['identity_slug']) : null,
            'estimated_provider_price' => [
                'amount' => (float) ($product->retail_price ?: $product->purchase_price ?: $product->min_price ?: 0),
                'currency' => strtoupper((string) ($product->currency ?: 'USD')),
            ],
        ];
    }

    /**
     * @param  Collection<int, ProviderProduct>  $products
     */
    private function title(array $identity, Collection $products): string
    {
        $parts = collect([
            $identity['brand'],
            $identity['product_family'] && $identity['product_family'] !== Str::lower((string) $identity['brand'])
                ? Str::title((string) $identity['product_family'])
                : null,
            $identity['face_value'] ? $this->formatAmount((float) $identity['face_value']).' '.$identity['face_value_currency'] : null,
            $identity['region'] && $identity['region'] !== 'global' ? $identity['region'] : null,
        ])->filter()->values();

        if ($parts->isNotEmpty()) {
            return $parts->implode(' ');
        }

        return (string) ($products->first()?->name ?? 'Digital product');
    }

    /**
     * @param  Collection<int, ProviderProduct>  $products
     * @param  Collection<int, array<string, mixed>>  $sellerOffers
     */
    private function description(array $identity, Collection $products, Collection $sellerOffers): string
    {
        $label = $this->categoryResolver->label((string) $identity['canonical_category']);
        $offerText = $sellerOffers->isNotEmpty()
            ? $sellerOffers->count().' seller offer(s) are available now.'
            : 'Checkout will appear when a seller connects this product.';

        return $this->title($identity, $products).' is a digital product in the Meanly '.$label.' catalog. '.$offerText;
    }

    /**
     * @param  Collection<int, ProviderProduct>  $products
     */
    private function providerSeoQuality(Collection $products): string
    {
        $qualities = $products
            ->map(fn (ProviderProduct $product) => $this->network->quality($product))
            ->values();

        if ($qualities->contains('ready')) {
            return 'ready';
        }

        if ($qualities->contains('thin')) {
            return 'thin';
        }

        return 'noindex_candidate';
    }

    /**
     * @param  array<string, mixed>  $policy
     */
    private function includePolicyInSitemap(array $policy, bool $includeLlmOnly): bool
    {
        if ((bool) ($policy['indexable'] ?? false)) {
            return true;
        }

        return $includeLlmOnly && ($policy['surface'] ?? null) === 'llm_only';
    }

    private function intentUrl(string $identitySlug, string $intent): string
    {
        return route('meanly.canonical-products.show', [
            'identitySlug' => $identitySlug,
            'intent' => $intent,
        ]);
    }

    private function categoryFromIdentitySlug(string $identitySlug): ?string
    {
        return collect((array) config('catalog_taxonomy.categories', []))
            ->keys()
            ->sortByDesc(fn (string $category) => strlen($this->slugPart($category)))
            ->first(fn (string $category) => Str::endsWith($identitySlug, $this->slugPart($category)));
    }

    /**
     * @return Collection<int, string>
     */
    private function searchTokens(string $identitySlug): Collection
    {
        $categorySlugs = collect((array) config('catalog_taxonomy.categories', []))
            ->keys()
            ->flatMap(fn (string $category) => explode('-', $this->slugPart($category)))
            ->all();
        $ignored = array_merge($categorySlugs, [
            'gift', 'cards', 'card', 'product', 'global', 'usd', 'eur', 'gbp', 'rub', 'rur', 'try',
            'tl', 'sar', 'aed', 'inr', 'brl', 'mxn', 'jpy', 'pln',
        ]);

        return collect(explode('-', $identitySlug))
            ->map(fn (string $token) => trim($token))
            ->filter(fn (string $token) => strlen($token) >= 3 && ! is_numeric($token) && ! in_array($token, $ignored, true))
            ->unique()
            ->values();
    }

    private function normalizeIdentitySlug(string $identitySlug): string
    {
        return $this->slugPart($identitySlug);
    }

    private function identityTablesExist(): bool
    {
        return Schema::hasTable('canonical_product_identities')
            && Schema::hasTable('canonical_product_identity_sources');
    }

    private function slugPart(string $value): string
    {
        return Str::of($value)
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9]+/', '-')
            ->trim('-')
            ->toString();
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '\\%_');
    }

    private function formatAmount(float $amount): string
    {
        return rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.');
    }
}
