<?php

namespace App\Services;

use App\Models\CanonicalProductIdentity;
use App\Models\Currency;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CanonicalStorefrontHomepageService
{
    private const IDENTITY_SCAN_LIMIT = 5000;
    private const BROWSE_PER_PAGE = 24;
    private const LLM_CATEGORY_LIMIT = 100;
    private const FEATURED_LIMIT = 8;
    private const PROVIDER_NETWORK_LIMIT = 12;
    private const CATEGORY_LIMIT = 10;
    private const CATEGORY_FACET_LIMIT = 50;
    private const HOMEPAGE_FEATURED_SCAN_LIMIT = 32;
    private const HOMEPAGE_NETWORK_SCAN_LIMIT = 48;
    private const HOMEPAGE_CACHE_SECONDS = 300;

    private ?bool $identityTablesExist = null;

    private ?bool $overrideTableExists = null;

    /**
     * @var array<string, array{label: string, description: string}>
     */
    private const PRODUCT_KIND_META = [
        'gift-cards' => [
            'label' => 'Gift cards',
            'description' => 'Подарочные карты и коды пополнения с выбором региона и номинала.',
        ],
        'subscriptions' => [
            'label' => 'Subscriptions',
            'description' => 'Подписки и продления доступа с выбором региона, срока и номинала.',
        ],
        'points' => [
            'label' => 'Points',
            'description' => 'Игровые очки и внутриигровая валюта с выбором номинала.',
        ],
        'top-ups' => [
            'label' => 'Top-ups',
            'description' => 'Пополнения балансов, кошельков и игровых аккаунтов.',
        ],
        'software-licenses' => [
            'label' => 'Software licenses',
            'description' => 'Лицензии и ключи активации цифрового ПО.',
        ],
        'vouchers' => [
            'label' => 'Vouchers',
            'description' => 'Цифровые ваучеры и сертификаты.',
        ],
    ];

    /**
     * @var array<string, array<int, string>>
     */
    private const SEARCH_SYNONYMS = [
        'ps' => ['playstation', 'psn', 'sony'],
        'psn' => ['playstation', 'sony'],
        'ps4' => ['playstation', 'sony'],
        'ps5' => ['playstation', 'sony'],
        'play' => ['playstation'],
        'playstation' => ['ps', 'psn', 'sony'],
        'плейстейшн' => ['playstation', 'psn', 'sony'],
        'плейстейшен' => ['playstation', 'psn', 'sony'],
        'плейстешн' => ['playstation', 'psn', 'sony'],
        'сони' => ['sony', 'playstation'],
        'сша' => ['us', 'usa', 'united states', 'unitedstates'],
        'usa' => ['us', 'united states', 'unitedstates', 'сша'],
        'us' => ['usa', 'united states', 'unitedstates', 'сша'],
        'ssha' => ['сша', 'us', 'usa', 'united states', 'unitedstates'],
        'cif' => ['сша', 'us', 'usa', 'united states', 'unitedstates'],
        'america' => ['us', 'usa', 'united states', 'unitedstates', 'сша'],
        'америка' => ['us', 'usa', 'united states', 'unitedstates', 'сша'],
        'штаты' => ['us', 'usa', 'united states', 'unitedstates', 'сша'],
        'states' => ['us', 'usa', 'united states', 'unitedstates'],
        'unitedstates' => ['us', 'usa', 'united states', 'сша'],
        'pleysteyshn' => ['playstation', 'psn', 'sony'],
        'pleystation' => ['playstation', 'psn', 'sony'],
        'tr' => ['turkey', 'turkiye', 'türkiye', 'турция'],
        'turkey' => ['tr', 'turkiye', 'türkiye', 'турция'],
        'turkiye' => ['tr', 'turkey', 'türkiye', 'турция'],
        'турция' => ['tr', 'turkey', 'turkiye', 'türkiye'],
        'подписка' => ['subscription', 'premium', 'plus'],
        'подписку' => ['subscription', 'premium', 'plus'],
        'подписки' => ['subscription', 'premium', 'plus'],
        'карта' => ['card', 'gift card'],
        'гифт' => ['gift', 'gift card'],
        'код' => ['code', 'key'],
    ];

    /**
     * @var array<int, string>
     */
    private const FUZZY_BRAND_TERMS = [
        'playstation',
        'spotify',
        'steam',
        'xbox',
        'nintendo',
        'roblox',
        'netflix',
        'amazon',
        'apple',
        'google',
        'razer',
        'valorant',
        'pubg',
        'blizzard',
        'battle',
    ];

    /**
     * @var array<string, string>
     */
    private const CATEGORY_SORT_OPTION_KEYS = [
        'relevance' => 'runtime.home.recommended',
        'price_asc' => 'runtime.home.price_asc',
        'price_desc' => 'runtime.home.price_desc',
        'face_value_asc' => 'runtime.home.face_value_asc',
        'face_value_desc' => 'runtime.home.face_value_desc',
        'offers' => 'runtime.home.in_stock',
        'brand' => 'runtime.home.brand_az',
        'newest' => 'runtime.home.newest',
    ];


    /**
     * @return array<string, string>
     */
    private function categorySortOptions(): array
    {
        return collect(self::CATEGORY_SORT_OPTION_KEYS)
            ->map(fn (string $key): string => __($key))
            ->all();
    }

    public function __construct(
        private readonly ProductIndexingPolicyService $indexingPolicy,
        private readonly CanonicalProductIdentityCurationService $curation,
        private readonly CanonicalCategoryResolver $categoryResolver,
        private readonly MeanlyFirstPartyStorefrontService $storefront,
        private readonly PricingProjectionService $pricingProjection,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function homepage(Request $request): array
    {
        $query = trim((string) ($request->query('q') ?? $request->query('intent') ?? ''));

        if ($query === '') {
            $featuredProducts = $this->rememberHomepageBlock(
                'featured_products',
                fn (): Collection => $this->homepageFeaturedProducts(),
            );
            $providerNetworkProducts = $this->rememberHomepageBlock(
                'provider_network_products',
                fn (): Collection => $this->homepageProviderNetworkProducts(),
            );
            $browseProducts = $this->emptyBrowseProducts($request);
            $categories = $this->rememberHomepageBlock(
                'categories',
                fn (): Collection => $this->publicCategorySummaries(self::CATEGORY_LIMIT),
            );
            $brands = $this->rememberHomepageBlock(
                'brands',
                fn (): Collection => $this->publicBrandSummaries(),
            );
            $productGroups = $this->rememberHomepageBlock(
                'product_groups',
                fn (): Collection => $this->publicProductGroupSummaries(limit: 12),
            );
        } else {
            $cards = $this->groupCardsForInterface($this->storefrontReadyCards($query));
            $page = max(1, (int) $request->query('page', 1));
            $browseProducts = new LengthAwarePaginator(
                $cards->forPage($page, self::BROWSE_PER_PAGE)->values(),
                $cards->count(),
                self::BROWSE_PER_PAGE,
                $page,
                [
                    'path' => $request->url(),
                    'query' => $request->query(),
                ],
            );
            $categories = $this->categorySummariesFromCards($cards);
            $brands = $this->brandSummaries($cards);
            $featuredProducts = $cards
                ->filter(fn (array $card) => $card['has_selected_offer'])
                ->take(self::FEATURED_LIMIT)
                ->values();
            $providerNetworkProducts = $cards
                ->reject(fn (array $card) => $card['has_selected_offer'])
                ->take(self::PROVIDER_NETWORK_LIMIT)
                ->values();
            $productGroups = collect();
        }

        return [
            'query' => $query,
            'quick_chips' => $this->quickChips(),
            'featured_products' => $featuredProducts,
            'provider_network_products' => $providerNetworkProducts,
            'product_groups' => $productGroups,
            'categories' => $categories,
            'brands' => $brands,
            'browse_products' => $browseProducts->withQueryString(),
            'stats' => $this->rememberHomepageBlock('stats', fn (): array => $this->stats()),
        ];
    }

    /**
     * @return array{query: string, browse_products: LengthAwarePaginator<int, array<string, mixed>>}
     */
    public function searchPage(Request $request): array
    {
        $query = trim((string) ($request->query('q') ?? $request->query('intent') ?? ''));
        $cards = $query === '' ? collect() : $this->groupCardsForInterface($this->storefrontReadyCards($query));
        $page = max(1, (int) $request->query('page', 1));
        $browseProducts = new LengthAwarePaginator(
            $cards->forPage($page, self::BROWSE_PER_PAGE)->values(),
            $cards->count(),
            self::BROWSE_PER_PAGE,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ],
        );

        return [
            'query' => $query,
            'browse_products' => $browseProducts->withQueryString(),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>|LengthAwarePaginator<int, array<string, mixed>>  $products
     * @return array<string, mixed>
     */
    public function itemListJsonLd(Collection|LengthAwarePaginator $products, string $name): array
    {
        $items = $products instanceof LengthAwarePaginator
            ? collect($products->items())
            : $products;

        return [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => $name,
            'itemListElement' => $items
                ->values()
                ->take(50)
                ->map(fn (array $product, int $index) => [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'url' => $product['url'],
                    'name' => $product['name'],
                ])
                ->all(),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function storefrontReadyCards(?string $query = null, ?int $limit = null): Collection
    {
        if (! $this->identityTablesExist()) {
            return collect();
        }

        $query = trim((string) $query);
        $limit = $limit ?? ($query === '' ? null : self::IDENTITY_SCAN_LIMIT);

        $cards = $this->cardsFromIdentityQuery($this->identityQuery($query === '' ? '' : $query, $limit));

        if ($query === '') {
            return $cards;
        }

        // Run the fuzzy search scoring and ordering on the database-matched cards.
        // This is incredibly fast (processing tens of matched cards) compared to
        // loading and scanning all 5,000 approved products in PHP memory.
        return $this->filterCardsByQuery($cards, $query);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function categoryCards(string $category, ?int $limit = null): Collection
    {
        if (! $this->identityTablesExist()) {
            return collect();
        }

        return $this->cardsFromIdentityQuery($this->identityQuery('', $limit ?? self::LLM_CATEGORY_LIMIT, $category));
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function catalogPage(Request $request, int $perPage = self::BROWSE_PER_PAGE): LengthAwarePaginator
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min($perPage, 72));
        $filters = $this->catalogRequestFilters($request);
        $query = $this->identityQuery((string) ($filters['query'] ?? ''), null, $filters['category']);

        $this->applyCategoryFilters($query, $filters);
        $this->applyCategorySort($query, (string) $filters['sort']);

        $total = (clone $query)->getCountForPagination();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);

        $cards = (clone $query)
            ->forPage($page, $perPage)
            ->get()
            ->map(fn (CanonicalProductIdentity $identity) => $this->cardForIdentity($identity))
            ->filter()
            ->values();

        return (new LengthAwarePaginator(
            $cards,
            $total,
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ],
        ))->withQueryString();
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function categoryPage(string $category, Request $request, int $perPage = self::BROWSE_PER_PAGE): LengthAwarePaginator
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min($perPage, 72));
        $filters = $this->categoryRequestFilters($category, $request);

        if ($this->shouldUseProductGroupsForCategory($filters)) {
            return $this->categoryProductGroupPage($category, $request, $filters, $perPage);
        }

        $query = $this->identityQuery('', null, $category);

        $this->applyCategoryFilters($query, $filters);
        $this->applyCategorySort($query, (string) $filters['sort']);

        $total = (clone $query)->getCountForPagination();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);

        $cards = (clone $query)
            ->forPage($page, $perPage)
            ->get()
            ->map(fn (CanonicalProductIdentity $identity) => $this->cardForIdentity($identity))
            ->filter()
            ->values();

        return (new LengthAwarePaginator(
            $cards,
            $total,
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ],
        ))->withQueryString();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function productGroupPage(string $category, string $brandSlug, string $kindSlug, Request $request, int $perPage = self::BROWSE_PER_PAGE): ?array
    {
        if (! $this->identityTablesExist() || ! array_key_exists($category, (array) config('catalog_taxonomy.categories', []))) {
            return null;
        }

        $brand = $this->resolveCategoryBrand($category, $brandSlug);

        if ($brand === null || ! array_key_exists($kindSlug, self::PRODUCT_KIND_META)) {
            return null;
        }

        $allCards = $this->identityQuery('', null, $category)
            ->reorder()
            ->where('brand', $brand)
            ->orderByRaw('case when best_offer_product_id is null then 1 else 0 end')
            ->orderByRaw("case confidence when 'high' then 0 when 'medium' then 1 else 2 end")
            ->orderByRaw('case when face_value is null then 1 else 0 end')
            ->orderBy('face_value')
            ->orderBy('face_value_currency')
            ->orderBy('region')
            ->get()
            ->map(fn (CanonicalProductIdentity $identity) => $this->cardForIdentity($identity))
            ->filter()
            ->filter(fn (array $card): bool => $this->productKindForCard($card)['slug'] === $kindSlug)
            ->values();

        if ($allCards->isEmpty()) {
            return null;
        }

        $filters = $this->productGroupRequestFilters($request);
        $filteredCards = $this->filterProductGroupCards($allCards, $filters);
        $selectionReady = $filteredCards->isNotEmpty()
            && (($filters['region'] !== null && $filters['face_value'] !== null) || $filteredCards->count() === 1);
        $selectedProduct = $selectionReady
            ? $filteredCards
                ->sortBy([
                    ['has_selected_offer', 'desc'],
                    ['seller_offer_count', 'desc'],
                    ['provider_count', 'desc'],
                ])
                ->first()
            : null;
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min($perPage, 72));
        $total = $filteredCards->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);
        $kind = self::PRODUCT_KIND_META[$kindSlug];
        $meta = (array) config("catalog_taxonomy.categories.{$category}", []);
        $nominalFacetCards = ($filters['region'] ?? null) !== null
            ? $allCards
                ->filter(fn (array $card): bool => Str::lower((string) ($card['region'] ?? '')) === Str::lower((string) $filters['region']))
                ->values()
            : $allCards;
        $priceRange = $this->productGroupPriceRange($filteredCards->isNotEmpty() ? $filteredCards : $nominalFacetCards);
        $paginator = new LengthAwarePaginator(
            $filteredCards->forPage($page, $perPage)->values(),
            $total,
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ],
        );

        return [
            'group' => [
                'category' => $category,
                'category_label' => $this->categoryResolver->label($category),
                'brand' => $brand,
                'brand_slug' => Str::slug($brand),
                'kind' => $kindSlug,
                'kind_label' => $kind['label'],
                'title' => trim($brand.' '.$kind['label']),
                'description' => $kind['description'],
                'variant_count' => $allCards->count(),
                'region_count' => $this->productGroupRegionFacets($allCards)->count(),
                'nominal_count' => $this->productGroupNominalFacets($allCards)->count(),
                'selection_ready' => $selectionReady,
                'selected_product' => $selectedProduct,
                'selected_offer' => $selectedProduct['selected_offer'] ?? null,
                'price_range' => $priceRange,
                'variants' => $this->productGroupVariantOptions($allCards),
                'canonical_url' => route('meanly.catalog.groups.show', [
                    'category' => $category,
                    'brandSlug' => Str::slug($brand),
                    'kindSlug' => $kindSlug,
                ]),
            ],
            'meta' => [
                'label_ru' => trim($brand.' '.$kind['label']),
                'label_en' => trim($brand.' '.$kind['label']),
                'description_ru' => trim($kind['description'].' '.(string) ($meta['description_ru'] ?? '')),
            ],
            'products' => $paginator->withQueryString(),
            'facets' => [
                'regions' => $this->productGroupRegionFacets($allCards),
                'nominals' => $this->productGroupNominalFacets($nominalFacetCards),
                'selected' => $filters + [
                    'has_filters' => $filters['region'] !== null || $filters['face_value'] !== null || $filters['currency'] !== null,
                    'brand' => $brand,
                    'family' => null,
                ],
                'sort_options' => $this->categorySortOptions(),
            ],
        ];
    }

    /**
     * @return array{region: ?string, face_value: ?string, currency: ?string, nominal_key: string, sort: string}
     */
    private function productGroupRequestFilters(Request $request): array
    {
        $region = $this->normalizeRegion($this->scalarQueryValue($request, 'region'));
        $currency = $this->normalizeCurrency($this->scalarQueryValue($request, 'currency'));
        $rawFaceValue = $this->scalarQueryValue($request, 'face_value');
        $rawNominal = $this->scalarQueryValue($request, 'nominal');

        if (($rawFaceValue === null || $rawFaceValue === '') && $rawNominal !== null && $rawNominal !== '') {
            [$rawFaceValue, $nominalCurrency] = $this->parseNominalFilter($rawNominal);
            $currency = $currency ?? $nominalCurrency;
        }

        $faceValue = $this->normalizeFaceValue($rawFaceValue);

        return [
            'region' => $region,
            'face_value' => $faceValue,
            'currency' => $currency,
            'nominal_key' => $this->nominalKey($faceValue, $currency),
            'sort' => $this->normalizeCategorySort($this->scalarQueryValue($request, 'sort')),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $cards
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function filterProductGroupCards(Collection $cards, array $filters): Collection
    {
        if (($filters['region'] ?? null) !== null) {
            $cards = $cards
                ->filter(fn (array $card): bool => Str::lower((string) ($card['region'] ?? '')) === Str::lower((string) $filters['region']))
                ->values();
        }

        if (($filters['face_value'] ?? null) !== null) {
            $cards = $cards
                ->filter(fn (array $card): bool => $this->formatAmount((float) ($card['face_value'] ?? 0)) === (string) $filters['face_value'])
                ->values();
        }

        if (($filters['currency'] ?? null) !== null) {
            $cards = $cards
                ->filter(fn (array $card): bool => Str::upper((string) ($card['face_value_currency'] ?? '')) === (string) $filters['currency'])
                ->values();
        }

        return $this->sortProductGroupCards($cards, (string) ($filters['sort'] ?? 'relevance'));
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $cards
     * @return Collection<int, array<string, mixed>>
     */
    private function sortProductGroupCards(Collection $cards, string $sort): Collection
    {
        return match ($sort) {
            'price_asc' => $cards
                ->sortBy(fn (array $card): string => sprintf(
                    '%d:%012.4f:%012.4f',
                    data_get($card, 'selected_offer.price.amount') === null ? 1 : 0,
                    (float) data_get($card, 'selected_offer.price.amount', PHP_FLOAT_MAX),
                    (float) ($card['face_value'] ?? PHP_FLOAT_MAX),
                ))
                ->values(),
            'price_desc' => $cards
                ->sortByDesc(fn (array $card): float => (float) data_get($card, 'selected_offer.price.amount', 0))
                ->values(),
            'face_value_asc' => $cards
                ->sortBy(fn (array $card): string => sprintf(
                    '%d:%012.4f:%s:%s',
                    ($card['face_value'] ?? null) === null ? 1 : 0,
                    (float) ($card['face_value'] ?? PHP_FLOAT_MAX),
                    (string) ($card['face_value_currency'] ?? ''),
                    (string) ($card['region'] ?? ''),
                ))
                ->values(),
            'face_value_desc' => $cards
                ->sortByDesc(fn (array $card): float => (float) ($card['face_value'] ?? 0))
                ->values(),
            'newest' => $cards
                ->sortByDesc(fn (array $card): int => (int) ($card['last_seen_timestamp'] ?? 0))
                ->values(),
            'offers' => $cards
                ->sortBy([
                    ['has_selected_offer', 'desc'],
                    ['seller_offer_count', 'desc'],
                    ['provider_count', 'desc'],
                ])
                ->values(),
            default => $cards->values(),
        };
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $cards
     * @return Collection<int, array<string, mixed>>
     */
    private function productGroupRegionFacets(Collection $cards): Collection
    {
        return $cards
            ->map(fn (array $card): string => trim((string) ($card['region'] ?? '')))
            ->filter(fn (string $region): bool => $region !== '' && Str::lower($region) !== 'global')
            ->groupBy(fn (string $region): string => Str::lower($region))
            ->map(function (Collection $regions): array {
                $region = (string) $regions->first();

                return [
                    'name' => $region,
                    'value' => $region,
                    'slug' => Str::slug($region),
                    'label' => Str::upper($region),
                    'count' => $regions->count(),
                ];
            })
            ->sortByDesc('count')
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $cards
     * @return Collection<int, array<string, mixed>>
     */
    private function productGroupNominalFacets(Collection $cards): Collection
    {
        return $cards
            ->map(function (array $card): ?array {
                $faceValue = $card['face_value'] ?? null;
                $currency = $this->normalizeCurrency((string) ($card['face_value_currency'] ?? ''));

                if (! is_numeric($faceValue) || (float) $faceValue <= 0) {
                    return null;
                }

                $formatted = $this->formatAmount((float) $faceValue);

                return [
                    'face_value' => $formatted,
                    'currency' => $currency,
                    'key' => $this->nominalKey($formatted, $currency),
                    'value' => trim($formatted.' '.$currency),
                    'label' => trim($formatted.' '.$currency),
                ];
            })
            ->filter()
            ->groupBy('key')
            ->map(function (Collection $nominals): array {
                $first = $nominals->first();

                return $first + [
                    'count' => $nominals->count(),
                ];
            })
            ->sortBy([
                ['currency', 'asc'],
                ['face_value', 'asc'],
            ])
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $cards
     * @return array<string, mixed>|null
     */
    private function productGroupPriceRange(Collection $cards): ?array
    {
        $nominals = $cards
            ->map(function (array $card): ?array {
                $faceValue = $card['face_value'] ?? null;
                $currency = $this->normalizeCurrency((string) ($card['face_value_currency'] ?? ''));

                if (! is_numeric($faceValue) || (float) $faceValue <= 0 || $currency === null) {
                    return null;
                }

                $rate = $this->currencyRateToRub($currency);

                if ($rate === null) {
                    return null;
                }

                $rubAmount = round((float) $faceValue * $rate, 2);
                $displayPrice = $this->pricingProjection->publicPriceForStorageAmount($rubAmount);

                return [
                    'amount' => $displayPrice['amount'],
                    'currency' => $displayPrice['currency'],
                    'label' => $displayPrice['label'],
                    'storage_amount' => $rubAmount,
                    'storage_currency' => 'RUB',
                ];
            })
            ->filter()
            ->sortBy([
                ['amount', 'asc'],
                ['currency', 'asc'],
            ])
            ->values();

        if ($nominals->isEmpty()) {
            return null;
        }

        $first = $nominals->first();

        return [
            'min' => $first['amount'],
            'max' => (float) $nominals->max('amount'),
            'currency' => $first['currency'],
            'count' => $nominals->count(),
            'label' => __('runtime.home.from_label', ['label' => $first['label']]),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $cards
     * @return Collection<int, array<string, mixed>>
     */
    private function productGroupVariantOptions(Collection $cards): Collection
    {
        return $cards
            ->map(function (array $card): ?array {
                $faceValue = $card['face_value'] ?? null;
                $currency = $this->normalizeCurrency((string) ($card['face_value_currency'] ?? ''));
                $region = trim((string) ($card['region'] ?? ''));

                if ($region === '' || Str::lower($region) === 'global' || ! is_numeric($faceValue) || (float) $faceValue <= 0) {
                    return null;
                }

                $formatted = $this->formatAmount((float) $faceValue);
                $offer = data_get($card, 'selected_offer');
                $nominalRubAmount = ($rate = $this->currencyRateToRub($currency)) !== null
                    ? round((float) $faceValue * $rate, 2)
                    : null;

                return [
                    'slug' => (string) ($card['slug'] ?? ''),
                    'name' => (string) ($card['name'] ?? ''),
                    'region' => $region,
                    'region_key' => Str::lower($region),
                    'region_label' => Str::upper($region),
                    'nominal_key' => $this->nominalKey($formatted, $currency),
                    'nominal_value' => trim($formatted.' '.$currency),
                    'nominal_label' => trim($formatted.' '.$currency),
                    'face_value' => $formatted,
                    'currency' => $currency,
                    'nominal_rub_price' => $nominalRubAmount,
                    'has_offer' => $offer !== null,
                    'offer' => $offer,
                    'price' => [
                        'amount' => is_numeric(data_get($offer, 'price.amount')) ? (float) data_get($offer, 'price.amount') : null,
                        'currency' => (string) data_get($offer, 'price.currency', 'RUB'),
                    ],
                    'seller' => [
                        'name' => (string) data_get($offer, 'seller.name', 'Meanly seller'),
                    ],
                ];
            })
            ->filter()
            ->groupBy(fn (array $variant): string => $variant['region_key'].'|'.$variant['nominal_key'])
            ->map(function (Collection $matches): array {
                $selected = $matches
                    ->sortBy([
                        ['has_offer', 'desc'],
                        ['price.amount', 'asc'],
                        ['name', 'asc'],
                    ])
                    ->first();

                return $selected + [
                    'match_count' => $matches->count(),
                ];
            })
            ->sortBy([
                ['region_label', 'asc'],
                ['currency', 'asc'],
                ['face_value', 'asc'],
            ])
            ->values();
    }

    private function currencyRateToRub(string $currency): ?float
    {
        $currency = Str::upper(trim($currency));

        if ($currency === '') {
            return null;
        }

        if ($currency === 'RUB') {
            return 1.0;
        }

        return Cache::remember("marketplace.currency-rate-to-rub.{$currency}", 300, function () use ($currency): ?float {
            $model = Currency::where('code', $currency)->first();
            $rate = $model?->effective_rate;

            return is_numeric($rate) && (float) $rate > 0
                ? (float) $rate
                : null;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function catalogFacets(Request $request): array
    {
        $filters = $this->catalogRequestFilters($request);

        if (! $this->identityTablesExist()) {
            return [
                'categories' => collect(),
                'brands' => collect(),
                'regions' => collect(),
                'nominals' => collect(),
                'selected' => $filters,
                'sort_options' => $this->categorySortOptions(),
            ];
        }

        $categorySummaries = $this->publicCategorySummaries();

        $brandQuery = $this->identityQuery((string) ($filters['query'] ?? ''), null, $filters['category'])->reorder();
        $this->applyCategoryFilters($brandQuery, $filters, ['brand']);

        $brands = $brandQuery
            ->whereNotNull('brand')
            ->where('brand', '<>', '')
            ->select('brand')
            ->selectRaw('count(*) as product_count')
            ->groupBy('brand')
            ->orderByDesc('product_count')
            ->orderBy('brand')
            ->limit(self::CATEGORY_FACET_LIMIT)
            ->get()
            ->map(fn (CanonicalProductIdentity $row): array => [
                'name' => (string) $row->brand,
                'value' => (string) $row->brand,
                'slug' => Str::slug((string) $row->brand),
                'count' => (int) $row->getAttribute('product_count'),
            ]);

        $nominalQuery = $this->identityQuery((string) ($filters['query'] ?? ''), null, $filters['category'])->reorder();
        $this->applyCategoryFilters($nominalQuery, $filters, ['face_value', 'currency']);

        $nominals = $nominalQuery
            ->whereNotNull('face_value')
            ->where('face_value', '>', 0)
            ->select('face_value', 'face_value_currency')
            ->selectRaw('count(*) as product_count')
            ->groupBy('face_value', 'face_value_currency')
            ->orderByDesc('product_count')
            ->orderBy('face_value_currency')
            ->orderBy('face_value')
            ->limit(self::CATEGORY_FACET_LIMIT)
            ->get()
            ->map(function (CanonicalProductIdentity $row): array {
                $faceValue = $this->formatAmount((float) $row->face_value);
                $currency = Str::upper(trim((string) $row->face_value_currency));

                return [
                    'face_value' => $faceValue,
                    'currency' => $currency,
                    'key' => $this->nominalKey($faceValue, $currency),
                    'value' => trim($faceValue.' '.$currency),
                    'label' => trim($faceValue.' '.$currency),
                    'count' => (int) $row->getAttribute('product_count'),
                ];
            });

        $regionQuery = $this->identityQuery((string) ($filters['query'] ?? ''), null, $filters['category'])->reorder();
        $this->applyCategoryFilters($regionQuery, $filters, ['region']);

        $regions = $regionQuery
            ->whereNotNull('region')
            ->where('region', '<>', '')
            ->whereRaw('lower(region) <> ?', ['global'])
            ->select('region')
            ->selectRaw('count(*) as product_count')
            ->groupBy('region')
            ->orderByDesc('product_count')
            ->orderBy('region')
            ->limit(self::CATEGORY_FACET_LIMIT)
            ->get()
            ->map(fn (CanonicalProductIdentity $row): array => [
                'name' => (string) $row->region,
                'value' => (string) $row->region,
                'slug' => Str::slug((string) $row->region),
                'label' => Str::upper((string) $row->region),
                'count' => (int) $row->getAttribute('product_count'),
            ]);

        return [
            'categories' => $categorySummaries,
            'brands' => $brands,
            'regions' => $regions,
            'nominals' => $nominals,
            'selected' => $filters,
            'sort_options' => $this->categorySortOptions(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function categoryFacets(string $category, Request $request): array
    {
        $filters = $this->categoryRequestFilters($category, $request);

        if (! $this->identityTablesExist()) {
            return [
                'brands' => collect(),
                'nominals' => collect(),
                'selected' => $filters,
                'sort_options' => $this->categorySortOptions(),
            ];
        }

        $brandQuery = $this->identityQuery('', null, $category)->reorder();
        $this->applyCategoryFilters($brandQuery, $filters, ['brand']);

        $brands = $brandQuery
            ->whereNotNull('brand')
            ->where('brand', '<>', '')
            ->select('brand')
            ->selectRaw('count(*) as product_count')
            ->groupBy('brand')
            ->orderByDesc('product_count')
            ->orderBy('brand')
            ->limit(self::CATEGORY_FACET_LIMIT)
            ->get()
            ->map(fn (CanonicalProductIdentity $row): array => [
                'name' => (string) $row->brand,
                'value' => (string) $row->brand,
                'slug' => Str::slug((string) $row->brand),
                'count' => (int) $row->getAttribute('product_count'),
            ]);

        if (
            $filters['brand'] !== null
            && $brands->doesntContain(fn (array $brand): bool => $brand['name'] === $filters['brand'])
        ) {
            $brands->prepend([
                'name' => (string) $filters['brand'],
                'value' => (string) $filters['brand'],
                'slug' => Str::slug((string) $filters['brand']),
                'count' => null,
            ]);
        }

        $nominalQuery = $this->identityQuery('', null, $category)->reorder();
        $this->applyCategoryFilters($nominalQuery, $filters, ['face_value', 'currency']);

        $nominals = $nominalQuery
            ->whereNotNull('face_value')
            ->where('face_value', '>', 0)
            ->select('face_value', 'face_value_currency')
            ->selectRaw('count(*) as product_count')
            ->groupBy('face_value', 'face_value_currency')
            ->orderByDesc('product_count')
            ->orderBy('face_value_currency')
            ->orderBy('face_value')
            ->limit(self::CATEGORY_FACET_LIMIT)
            ->get()
            ->map(function (CanonicalProductIdentity $row): array {
                $faceValue = $this->formatAmount((float) $row->face_value);
                $currency = Str::upper(trim((string) $row->face_value_currency));

                return [
                    'face_value' => $faceValue,
                    'currency' => $currency,
                    'key' => $this->nominalKey($faceValue, $currency),
                    'value' => trim($faceValue.' '.$currency),
                    'label' => trim($faceValue.' '.$currency),
                    'count' => (int) $row->getAttribute('product_count'),
                ];
            });

        if (
            $filters['face_value'] !== null
            && $nominals->doesntContain(fn (array $nominal): bool => $nominal['key'] === $filters['nominal_key'])
        ) {
            $faceValue = (string) $filters['face_value'];
            $currency = (string) ($filters['currency'] ?? '');

            $nominals->prepend([
                'face_value' => $faceValue,
                'currency' => $currency,
                'key' => $this->nominalKey($faceValue, $currency),
                'value' => trim($faceValue.' '.$currency),
                'label' => trim($faceValue.' '.$currency),
                'count' => null,
            ]);
        }

        return [
            'brands' => $brands->values(),
            'nominals' => $nominals->values(),
            'selected' => $filters,
            'sort_options' => $this->categorySortOptions(),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function publicCategorySummaries(?int $limit = null): Collection
    {
        if (! $this->identityTablesExist()) {
            return collect();
        }

        $categories = (array) config('catalog_taxonomy.categories', []);

        if ($categories === []) {
            return collect();
        }

        $query = CanonicalProductIdentity::query()
            ->whereIn('canonical_category', array_keys($categories));

        $this->applyPublicIdentityFilters($query);

        return $query
            ->selectRaw('canonical_category, count(*) as product_count, sum(seller_offers_count) as seller_offer_count, sum(provider_candidates_count) as provider_count')
            ->groupBy('canonical_category')
            ->get()
            ->map(function (CanonicalProductIdentity $row): array {
                $category = (string) $row->canonical_category;
                $meta = (array) config("catalog_taxonomy.categories.{$category}", []);
                $count = (int) $row->getAttribute('product_count');

                return [
                    'slug' => $category,
                    'name' => $meta['label_ru'] ?? $this->categoryResolver->label($category),
                    'label_ru' => $meta['label_ru'] ?? $this->categoryResolver->label($category),
                    'label_en' => $meta['label_en'] ?? $category,
                    'description_ru' => $meta['description_ru'] ?? null,
                    'schema_org' => $meta['schema_org'] ?? 'Product',
                    'google_product_category' => $meta['google_product_category'] ?? null,
                    'count' => $count,
                    'product_count' => $count,
                    'seller_offer_count' => (int) $row->getAttribute('seller_offer_count'),
                    'provider_count' => (int) $row->getAttribute('provider_count'),
                    'url' => route('meanly.catalog.categories.show', $category),
                    'machine_readable_url' => route('llms.categories.show', $category),
                ];
            })
            ->sortByDesc('count')
            ->when($limit !== null, fn (Collection $summaries) => $summaries->take($limit))
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function publicBrandSummaries(int $limit = 12): Collection
    {
        if (! $this->identityTablesExist()) {
            return collect();
        }

        $query = CanonicalProductIdentity::query()
            ->whereNotNull('brand')
            ->where('brand', '<>', '');

        $this->applyPublicIdentityFilters($query);

        return $query
            ->select('brand')
            ->selectRaw('count(*) as product_count, sum(seller_offers_count) as seller_offer_count')
            ->groupBy('brand')
            ->orderByDesc('product_count')
            ->orderBy('brand')
            ->limit($limit)
            ->get()
            ->map(fn (CanonicalProductIdentity $row): array => [
                'name' => (string) $row->brand,
                'count' => (int) $row->getAttribute('product_count'),
                'seller_offer_count' => (int) $row->getAttribute('seller_offer_count'),
                'url' => route('meanly.catalog.brands.show', Str::slug((string) $row->brand)),
            ])
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function publicProductGroupSummaries(?string $category = null, ?string $brand = null, ?int $limit = null): Collection
    {
        if (! $this->identityTablesExist()) {
            return collect();
        }

        $query = CanonicalProductIdentity::query()
            ->select([
                'id',
                'identity_slug',
                'canonical_category',
                'brand',
                'product_family',
                'face_value',
                'face_value_currency',
                'region',
                'provider_candidates_count',
                'seller_offers_count',
                'best_offer_product_id',
                'last_seen_at',
                'updated_at',
            ])
            ->whereNotNull('brand')
            ->where('brand', '<>', '');

        $this->applyPublicIdentityFilters($query);

        if ($category !== null) {
            $query->where('canonical_category', $category);
        }

        if ($brand !== null) {
            $query->where('brand', $brand);
        }

        $groups = $query
            ->get()
            ->groupBy(function (CanonicalProductIdentity $identity): string {
                $card = $this->identitySummaryCard($identity);
                $kind = $this->productKindForCard($card);

                return implode('|', [
                    (string) $identity->canonical_category,
                    Str::lower((string) $identity->brand),
                    $kind['slug'],
                ]);
            })
            ->map(fn (Collection $group): array => $this->productGroupSummaryCard($group))
            ->sortBy([
                ['has_selected_offer', 'desc'],
                ['seller_offer_count', 'desc'],
                ['provider_count', 'desc'],
                ['variant_group.variant_count', 'desc'],
                ['name', 'asc'],
            ])
            ->values();

        return $limit !== null ? $groups->take($limit)->values() : $groups;
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function categoryProductGroupPage(string $category, Request $request, array $filters, int $perPage): LengthAwarePaginator
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min($perPage, 72));
        $groups = $this->publicProductGroupSummaries($category, $filters['brand'] ?? null);
        $total = $groups->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);

        return (new LengthAwarePaginator(
            $groups->forPage($page, $perPage)->values(),
            $total,
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ],
        ))->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function shouldUseProductGroupsForCategory(array $filters): bool
    {
        return ($filters['region'] ?? null) === null
            && ($filters['face_value'] ?? null) === null
            && ($filters['currency'] ?? null) === null
            && ($filters['family'] ?? null) === null;
    }

    /**
     * @return array<string, mixed>
     */
    private function identitySummaryCard(CanonicalProductIdentity $identity): array
    {
        return [
            'id' => $identity->id,
            'slug' => $identity->identity_slug,
            'url' => route('meanly.canonical-products.show', $identity->identity_slug),
            'name' => $identity->identity_slug,
            'category' => (string) $identity->canonical_category,
            'category_label' => $this->categoryResolver->label((string) $identity->canonical_category),
            'brand' => $identity->brand,
            'product_family' => $identity->product_family,
            'face_value' => $identity->face_value,
            'face_value_currency' => $identity->face_value_currency,
            'region' => $identity->region ?: 'global',
            'provider_count' => (int) $identity->provider_candidates_count,
            'seller_offer_count' => (int) $identity->seller_offers_count,
            'has_selected_offer' => $identity->best_offer_product_id !== null,
            'last_seen_timestamp' => optional($identity->last_seen_at ?: $identity->updated_at)->getTimestamp() ?? 0,
        ];
    }

    /**
     * @param  Collection<int, CanonicalProductIdentity>  $group
     * @return array<string, mixed>
     */
    private function productGroupSummaryCard(Collection $group): array
    {
        $representative = $this->identitySummaryCard(
            $group
                ->sortBy([
                    ['best_offer_product_id', 'desc'],
                    ['seller_offers_count', 'desc'],
                    ['provider_candidates_count', 'desc'],
                    ['last_seen_at', 'desc'],
                ])
                ->first()
        );
        $kind = $this->productKindForCard($representative);
        $regions = $group
            ->pluck('region')
            ->map(fn ($region): string => trim((string) $region))
            ->filter(fn (string $region): bool => $region !== '' && Str::lower($region) !== 'global')
            ->unique(fn (string $region): string => Str::lower($region))
            ->values();
        $nominals = $group
            ->map(function (CanonicalProductIdentity $identity): ?string {
                if (! is_numeric($identity->face_value) || (float) $identity->face_value <= 0) {
                    return null;
                }

                return trim($this->formatAmount((float) $identity->face_value).' '.Str::upper((string) $identity->face_value_currency));
            })
            ->filter()
            ->unique()
            ->values();
        $canonicalUrls = $group
            ->map(fn (CanonicalProductIdentity $identity): string => route('meanly.canonical-products.show', $identity->identity_slug))
            ->values();

        return array_merge($representative, [
            'url' => $this->interfaceGroupUrl($representative),
            'name' => trim((string) $representative['brand'].' '.$kind['label']),
            'face_value' => null,
            'face_value_currency' => null,
            'region' => $regions->isNotEmpty() ? 'multiple' : ($representative['region'] ?? 'global'),
            'seller_offer_count' => (int) $group->sum('seller_offers_count'),
            'provider_count' => (int) $group->sum('provider_candidates_count'),
            'has_selected_offer' => $group->contains(fn (CanonicalProductIdentity $identity): bool => $identity->best_offer_product_id !== null),
            'selected_offer' => null,
            'status_label' => __('runtime.home.variant_group'),
            'cta_label' => __('runtime.home.choose_region_nominal'),
            'variant_group' => [
                'is_grouped' => true,
                'variant_count' => $group->count(),
                'region_count' => $regions->count(),
                'nominal_count' => $nominals->count(),
                'regions' => $regions->take(6)->all(),
                'nominals' => $nominals->take(6)->all(),
                'canonical_urls' => $canonicalUrls->all(),
            ],
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function emptyBrowseProducts(Request $request): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            collect(),
            0,
            self::BROWSE_PER_PAGE,
            max(1, (int) $request->query('page', 1)),
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ],
        );
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function homepageFeaturedProducts(): Collection
    {
        if (! $this->identityTablesExist()) {
            return collect();
        }

        return $this->cardsFromIdentityQuery(
            $this->identityQuery('', self::HOMEPAGE_FEATURED_SCAN_LIMIT)
                ->whereNotNull('best_offer_product_id')
        )
            ->filter(fn (array $card): bool => $card['has_selected_offer'])
            ->take(self::FEATURED_LIMIT)
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function homepageProviderNetworkProducts(): Collection
    {
        if (! $this->identityTablesExist()) {
            return collect();
        }

        return $this->cardsFromIdentityQuery(
            $this->identityQuery('', self::HOMEPAGE_NETWORK_SCAN_LIMIT)
                ->whereNull('best_offer_product_id')
                ->where('provider_candidates_count', '>', 0)
        )
            ->reject(fn (array $card): bool => $card['has_selected_offer'])
            ->take(self::PROVIDER_NETWORK_LIMIT)
            ->values();
    }

    /**
     * Collapse near-identical regional/nominal variants for human-facing lists.
     *
     * Canonical product URLs remain intact; this only changes browsable cards so
     * families like PlayStation or Tinder do not occupy dozens of adjacent rows.
     *
     * @param  Collection<int, array<string, mixed>>  $cards
     * @return Collection<int, array<string, mixed>>
     */
    private function groupCardsForInterface(Collection $cards): Collection
    {
        return $cards
            ->groupBy(fn (array $card): string => $this->interfaceGroupKey($card))
            ->map(fn (Collection $group): array => $this->interfaceGroupCard($group))
            ->sortBy([
                ['search_score', 'desc'],
                ['has_selected_offer', 'desc'],
                ['seller_offer_count', 'desc'],
                ['provider_count', 'desc'],
                ['last_seen_timestamp', 'desc'],
            ])
            ->values();
    }

    /**
     * Collapse canonical variants into human-facing product groups for assistant surfaces.
     *
     * @param  Collection<int, array<string, mixed>>  $cards
     * @return Collection<int, array<string, mixed>>
     */
    public function groupedCardsForInterface(Collection $cards): Collection
    {
        return $this->groupCardsForInterface($cards);
    }

    /**
     * @param  array<string, mixed>  $card
     */
    private function interfaceGroupKey(array $card): string
    {
        $brand = $this->normalizeGroupPart($card['brand'] ?? null);
        $kind = $this->productKindForCard($card)['slug'];

        if ($brand === '') {
            return 'single:'.(string) ($card['slug'] ?? $card['id'] ?? Str::random(8));
        }

        return implode('|', [
            $this->normalizeGroupPart($card['category'] ?? null),
            $brand,
            $kind,
        ]);
    }

    private function normalizeGroupPart(mixed $value): string
    {
        $value = Str::lower(trim((string) $value));
        $value = preg_replace('/\s+/', ' ', $value) ?? '';

        return trim($value);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $group
     * @return array<string, mixed>
     */
    private function interfaceGroupCard(Collection $group): array
    {
        if ($group->count() <= 1) {
            $single = $group->first();

            return $single + [
                'variant_group' => [
                    'is_grouped' => false,
                    'variant_count' => 1,
                    'region_count' => 0,
                    'nominal_count' => 0,
                    'regions' => [],
                    'nominals' => [],
                    'canonical_urls' => [$single['url'] ?? null],
                ],
            ];
        }

        $representative = $group
            ->sortBy([
                ['has_selected_offer', 'desc'],
                ['seller_offer_count', 'desc'],
                ['provider_count', 'desc'],
                ['last_seen_timestamp', 'desc'],
            ])
            ->first();

        $regions = $group
            ->pluck('region')
            ->map(fn ($region): string => trim((string) $region))
            ->filter(fn (string $region): bool => $region !== '' && Str::lower($region) !== 'global')
            ->unique(fn (string $region): string => Str::lower($region))
            ->values();

        $nominals = $group
            ->map(function (array $card): ?string {
                $faceValue = $card['face_value'] ?? null;
                $currency = trim((string) ($card['face_value_currency'] ?? ''));

                if (! is_numeric($faceValue) || (float) $faceValue <= 0) {
                    return null;
                }

                return trim($this->formatAmount((float) $faceValue).' '.Str::upper($currency));
            })
            ->filter()
            ->unique()
            ->values();

        $offerCard = $group->first(fn (array $card): bool => (bool) ($card['has_selected_offer'] ?? false));
        $sellerOfferCount = (int) $group->sum(fn (array $card): int => (int) ($card['seller_offer_count'] ?? 0));
        $providerCount = (int) $group->sum(fn (array $card): int => (int) ($card['provider_count'] ?? 0));
        $groupUrl = $this->interfaceGroupUrl($representative);

        return array_merge($representative, [
            'url' => $groupUrl,
            'name' => $this->interfaceGroupName($representative),
            'face_value' => null,
            'face_value_currency' => null,
            'region' => $regions->isNotEmpty() ? 'multiple' : ($representative['region'] ?? 'global'),
            'seller_offer_count' => $sellerOfferCount,
            'provider_count' => $providerCount,
            'has_selected_offer' => $offerCard !== null,
            'selected_offer' => $offerCard['selected_offer'] ?? null,
            'status_label' => $offerCard !== null ? __('runtime.home.offers_available') : __('runtime.home.supply_variants'),
            'cta_label' => __('runtime.home.view_variants'),
            'search_score' => (int) $group->max(fn (array $card): int => (int) ($card['search_score'] ?? 0)),
            'variant_group' => [
                'is_grouped' => true,
                'variant_count' => $group->count(),
                'region_count' => $regions->count(),
                'nominal_count' => $nominals->count(),
                'regions' => $regions->take(6)->all(),
                'nominals' => $nominals->take(6)->all(),
                'canonical_urls' => $group->pluck('url')->filter()->values()->all(),
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $card
     */
    private function interfaceGroupName(array $card): string
    {
        $brand = trim((string) ($card['brand'] ?? ''));
        $kind = $this->productKindForCard($card);

        if ($brand === '') {
            return (string) ($card['name'] ?? 'Digital product group');
        }

        return trim($brand.' '.$kind['label']);
    }

    /**
     * @param  array<string, mixed>  $card
     */
    private function interfaceGroupUrl(array $card): string
    {
        $category = (string) ($card['category'] ?? '');
        $brand = trim((string) ($card['brand'] ?? ''));
        $kind = $this->productKindForCard($card);

        if ($category !== '' && $brand !== '') {
            return route('meanly.catalog.groups.show', [
                'category' => $category,
                'brandSlug' => Str::slug($brand),
                'kindSlug' => $kind['slug'],
            ]);
        }

        return (string) ($card['url'] ?? route('meanly.catalog.index'));
    }

    /**
     * @param  array<string, mixed>  $card
     * @return array{slug: string, label: string, description: string}
     */
    private function productKindForCard(array $card): array
    {
        $category = (string) ($card['category'] ?? '');
        $brand = Str::lower((string) ($card['brand'] ?? ''));
        $family = Str::lower((string) ($card['product_family'] ?? ''));
        $name = Str::lower((string) ($card['name'] ?? ''));
        $haystack = trim($brand.' '.$family.' '.$name.' '.$category);

        $slug = match (true) {
            $category === 'subscriptions',
                str_contains($haystack, 'subscription'),
                str_contains($haystack, 'подпис'),
                str_contains($haystack, '1month'),
                str_contains($haystack, 'month plus'),
                str_contains($haystack, 'month gold'),
                str_contains($haystack, 'month platinum') => 'subscriptions',
            str_contains($haystack, 'points'),
                str_contains($haystack, 'fifa points'),
                str_contains($haystack, 'ultimate team points') => 'points',
            $category === 'game_wallet_topups',
                $category === 'telecom_topups',
                str_contains($haystack, 'topup'),
                str_contains($haystack, 'top-up'),
                str_contains($haystack, 'diamonds'),
                str_contains($haystack, 'coins') => 'top-ups',
            $category === 'software_licenses' => 'software-licenses',
            $category === 'travel_entertainment_vouchers',
                $category === 'local_vouchers' => 'vouchers',
            default => 'gift-cards',
        };

        return ['slug' => $slug] + self::PRODUCT_KIND_META[$slug];
    }

    private function rememberHomepageBlock(string $key, callable $resolver): mixed
    {
        if (app()->environment('testing')) {
            return $resolver();
        }

        return Cache::remember(
            "storefront:homepage:{$key}",
            self::HOMEPAGE_CACHE_SECONDS,
            $resolver,
        );
    }

    /**
     * @param  Builder<CanonicalProductIdentity>  $query
     * @return Collection<int, array<string, mixed>>
     */
    private function cardsFromIdentityQuery(Builder $query): Collection
    {
        return $query
            ->get()
            ->map(fn (CanonicalProductIdentity $identity) => $this->cardForIdentity($identity))
            ->filter()
            ->pipe(fn (Collection $cards) => $this->sortCards($cards))
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $cards
     * @return Collection<int, array<string, mixed>>
     */
    private function filterCardsByQuery(Collection $cards, string $query): Collection
    {
        $needle = $this->normalizeSearchText($query);
        $needleVariants = collect($this->searchTextVariants($query))
            ->map(fn (string $variant): string => $this->normalizeSearchText($variant))
            ->filter()
            ->unique()
            ->values();
        $tokenGroups = $this->searchTokenGroups($query);

        return $cards
            ->map(function (array $card) use ($needle, $needleVariants, $tokenGroups): array {
                $haystack = $this->cardSearchText($card);
                $score = $this->cardSearchScore($haystack, $needle, $needleVariants, $tokenGroups);

                return $card + [
                    'search_score' => $score,
                    'search_match_label' => $score >= 80 ? __('runtime.home.exact_match') : __('runtime.home.content_match'),
                ];
            })
            ->filter(fn (array $card): bool => (int) ($card['search_score'] ?? 0) > 0)
            ->sortByDesc(fn (array $card): int => (int) $card['search_score'])
            ->values();
    }

    /**
     * @return Builder<CanonicalProductIdentity>
     */
    private function identityQuery(string $query, ?int $limit, ?string $category = null): Builder
    {
        $builder = CanonicalProductIdentity::query()
            ->select([
                'id',
                'fingerprint',
                'identity_slug',
                'canonical_category',
                'brand',
                'product_family',
                'face_value',
                'face_value_currency',
                'region',
                'platform',
                'confidence',
                'signals',
                'provider_candidates_count',
                'seller_offers_count',
                'best_offer_product_id',
                'last_seen_at',
                'updated_at',
            ])
            ->with([
                'bestOfferProduct' => function ($query): void {
                    $this->applyVisibleBestOfferFilters($query);
                },
            ])
            ->orderByRaw('case when best_offer_product_id is null then 1 else 0 end')
            ->orderByRaw("case confidence when 'high' then 0 when 'medium' then 1 else 2 end")
            ->orderByDesc('seller_offers_count')
            ->orderByDesc('provider_candidates_count')
            ->orderByDesc('last_seen_at');

        $this->applyPublicIdentityFilters($builder);

        if ($category !== null) {
            $builder->where('canonical_category', $category);
        }

        if ($this->overrideTableExists()) {
            $builder->with('override');
        }

        if ($query !== '') {
            $tokenGroups = $this->searchTokenGroups($query)
                ->take(6)
                ->values();

            $builder->where(function ($builder) use ($tokenGroups): void {
                foreach ($tokenGroups as $tokenVariants) {
                    $builder->where(function ($builder) use ($tokenVariants): void {
                        foreach ($tokenVariants->take(12) as $token) {
                            $like = '%'.$this->escapeLike($token).'%';
                            $matchingCategories = $this->categoryKeysMatchingToken($token);

                            $builder
                                ->orWhere('identity_slug', 'like', $like)
                                ->orWhere('brand', 'like', $like)
                                ->orWhere('product_family', 'like', $like)
                                ->orWhere('face_value_currency', 'like', $like)
                                ->orWhere('region', 'like', $like)
                                ->orWhere('platform', 'like', $like)
                                ->orWhere('canonical_category', 'like', $like)
                                ->orWhereHas('bestOfferProduct', function ($query) use ($like): void {
                                    $query
                                        ->where('name', 'like', $like)
                                        ->orWhere('sku', 'like', $like)
                                        ->orWhereHas('shop', fn ($shopQuery) => $shopQuery->where('name', 'like', $like));
                                });

                            if ($matchingCategories !== []) {
                                $builder->orWhereIn('canonical_category', $matchingCategories);
                            }

                            if (is_numeric($token)) {
                                $builder->orWhere('face_value', (float) $token);
                            }
                        }
                    });
                }
            });
        }

        if ($limit !== null) {
            $builder->limit($limit);
        }

        return $builder;
    }

    /**
     * @return array<string, mixed>
     */
    private function catalogRequestFilters(Request $request): array
    {
        $category = $this->normalizeCatalogCategory($this->scalarQueryValue($request, 'category'));
        $rawBrand = $this->scalarQueryValue($request, 'brand');
        $brand = $rawBrand !== null && $rawBrand !== ''
            ? ($category !== null ? $this->resolveCategoryBrand($category, $rawBrand) : $rawBrand)
            : null;
        $family = $this->scalarQueryValue($request, 'family');
        $region = $this->normalizeRegion($this->scalarQueryValue($request, 'region'));

        $currency = $this->normalizeCurrency($this->scalarQueryValue($request, 'currency'));
        $rawFaceValue = $this->scalarQueryValue($request, 'face_value');
        $rawNominal = $this->scalarQueryValue($request, 'nominal');

        if (($rawFaceValue === null || $rawFaceValue === '') && $rawNominal !== null && $rawNominal !== '') {
            [$rawFaceValue, $nominalCurrency] = $this->parseNominalFilter($rawNominal);
            $currency = $currency ?? $nominalCurrency;
        }

        $faceValue = $this->normalizeFaceValue($rawFaceValue);
        $query = trim((string) ($request->query('q') ?? ''));
        $sort = $this->normalizeCategorySort($this->scalarQueryValue($request, 'sort'));

        return [
            'query' => $query,
            'category' => $category,
            'brand' => $brand,
            'brand_slug' => $brand !== null ? Str::slug($brand) : null,
            'family' => $family,
            'region' => $region,
            'region_slug' => $region !== null ? Str::slug($region) : null,
            'face_value' => $faceValue,
            'currency' => $currency,
            'nominal_key' => $this->nominalKey($faceValue, $currency),
            'sort' => $sort,
            'has_filters' => $query !== '' || $category !== null || $brand !== null || $family !== null || $region !== null || $faceValue !== null || $currency !== null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function categoryRequestFilters(string $category, Request $request): array
    {
        $rawBrand = $this->scalarQueryValue($request, 'brand');
        $brand = $rawBrand !== null && $rawBrand !== ''
            ? $this->resolveCategoryBrand($category, $rawBrand)
            : null;
        $family = $this->scalarQueryValue($request, 'family');

        $currency = $this->normalizeCurrency($this->scalarQueryValue($request, 'currency'));
        $rawFaceValue = $this->scalarQueryValue($request, 'face_value');
        $rawNominal = $this->scalarQueryValue($request, 'nominal');

        if (($rawFaceValue === null || $rawFaceValue === '') && $rawNominal !== null && $rawNominal !== '') {
            [$rawFaceValue, $nominalCurrency] = $this->parseNominalFilter($rawNominal);
            $currency = $currency ?? $nominalCurrency;
        }

        $faceValue = $this->normalizeFaceValue($rawFaceValue);
        $sort = $this->normalizeCategorySort($this->scalarQueryValue($request, 'sort'));

        return [
            'brand' => $brand,
            'brand_slug' => $brand !== null ? Str::slug($brand) : null,
            'family' => $family,
            'face_value' => $faceValue,
            'currency' => $currency,
            'nominal_key' => $this->nominalKey($faceValue, $currency),
            'sort' => $sort,
            'has_filters' => $brand !== null || $family !== null || $faceValue !== null || $currency !== null,
        ];
    }

    /**
     * @param  Builder<CanonicalProductIdentity>  $builder
     * @param  array<string, mixed>  $filters
     * @param  array<int, string>  $except
     */
    private function applyCategoryFilters(Builder $builder, array $filters, array $except = []): void
    {
        if (! in_array('brand', $except, true) && $filters['brand'] !== null) {
            $builder->where('brand', $filters['brand']);
        }

        if (! in_array('family', $except, true) && ($filters['family'] ?? null) !== null) {
            $builder->whereRaw('lower(product_family) = ?', [Str::lower((string) $filters['family'])]);
        }

        if (! in_array('region', $except, true) && ($filters['region'] ?? null) !== null) {
            $builder->whereRaw('lower(region) = ?', [Str::lower((string) $filters['region'])]);
        }

        if (! in_array('face_value', $except, true) && $filters['face_value'] !== null) {
            $builder->where('face_value', (float) $filters['face_value']);
        }

        if (! in_array('currency', $except, true) && $filters['currency'] !== null) {
            $builder->whereRaw('upper(face_value_currency) = ?', [$filters['currency']]);
        }
    }

    /**
     * @param  Builder<CanonicalProductIdentity>  $builder
     */
    private function applyCategorySort(Builder $builder, string $sort): void
    {
        if ($sort === 'relevance') {
            return;
        }

        $builder->reorder();

        match ($sort) {
            'price_asc' => $builder
                ->orderByRaw('case when best_offer_product_id is null then 1 else 0 end')
                ->orderBy($this->bestOfferPriceSubquery())
                ->orderByRaw('case when face_value is null then 1 else 0 end')
                ->orderBy('face_value')
                ->orderBy('face_value_currency')
                ->orderBy('brand')
                ->orderBy('product_family'),
            'price_desc' => $builder
                ->orderByRaw('case when best_offer_product_id is null then 1 else 0 end')
                ->orderByDesc($this->bestOfferPriceSubquery())
                ->orderByRaw('case when face_value is null then 1 else 0 end')
                ->orderByDesc('face_value')
                ->orderBy('face_value_currency')
                ->orderBy('brand')
                ->orderBy('product_family'),
            'brand' => $builder
                ->orderByRaw("case when brand is null or brand = '' then 1 else 0 end")
                ->orderBy('brand')
                ->orderBy('product_family')
                ->orderByRaw('case when face_value is null then 1 else 0 end')
                ->orderBy('face_value')
                ->orderBy('face_value_currency')
                ->orderByDesc('last_seen_at'),
            'face_value_asc' => $builder
                ->orderByRaw('case when face_value is null then 1 else 0 end')
                ->orderBy('face_value')
                ->orderBy('face_value_currency')
                ->orderBy('brand')
                ->orderByDesc('seller_offers_count'),
            'face_value_desc' => $builder
                ->orderByRaw('case when face_value is null then 1 else 0 end')
                ->orderByDesc('face_value')
                ->orderBy('face_value_currency')
                ->orderBy('brand')
                ->orderByDesc('seller_offers_count'),
            'offers' => $builder
                ->orderByRaw('case when best_offer_product_id is null then 1 else 0 end')
                ->orderByDesc('seller_offers_count')
                ->orderByDesc('provider_candidates_count')
                ->orderByDesc('last_seen_at'),
            'newest' => $builder
                ->orderByDesc('last_seen_at')
                ->orderByDesc('updated_at')
                ->orderByRaw('case when best_offer_product_id is null then 1 else 0 end')
                ->orderByDesc('seller_offers_count'),
            default => null,
        };
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\Product>
     */
    private function bestOfferPriceSubquery(): Builder
    {
        return Product::query()
            ->select('price_rub')
            ->whereColumn('products.id', 'canonical_product_identities.best_offer_product_id')
            ->limit(1);
    }

    private function resolveCategoryBrand(string $category, string $value): ?string
    {
        $value = trim($value);

        if ($value === '' || ! $this->identityTablesExist()) {
            return null;
        }

        $lowerValue = Str::lower($value);
        $directQuery = $this->identityQuery('', null, $category)->reorder();
        $directMatch = $directQuery
            ->whereNotNull('brand')
            ->whereRaw('lower(brand) = ?', [$lowerValue])
            ->value('brand');

        if (is_string($directMatch) && trim($directMatch) !== '') {
            return $directMatch;
        }

        $slug = Str::slug($value);

        if ($slug !== '') {
            $brandQuery = $this->identityQuery('', null, $category)->reorder();
            $slugMatch = $brandQuery
                ->whereNotNull('brand')
                ->where('brand', '<>', '')
                ->distinct()
                ->limit(self::IDENTITY_SCAN_LIMIT)
                ->pluck('brand')
                ->first(fn ($brand): bool => Str::slug((string) $brand) === $slug);

            if (is_string($slugMatch) && trim($slugMatch) !== '') {
                return $slugMatch;
            }
        }

        return $value;
    }

    private function normalizeCatalogCategory(?string $category): ?string
    {
        if ($category === null || $category === '') {
            return null;
        }

        return array_key_exists($category, (array) config('catalog_taxonomy.categories', []))
            ? $category
            : null;
    }

    private function normalizeRegion(?string $region): ?string
    {
        if ($region === null || $region === '') {
            return null;
        }

        return trim($region);
    }

    private function scalarQueryValue(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function parseNominalFilter(string $value): array
    {
        $value = trim(str_replace('|', ' ', $value));

        if (preg_match('/^([0-9]+(?:[.,][0-9]+)?)\s*([A-Za-z]{2,16})?$/', $value, $matches) !== 1) {
            return [$value, null];
        }

        return [
            $matches[1],
            isset($matches[2]) ? $this->normalizeCurrency($matches[2]) : null,
        ];
    }

    private function normalizeFaceValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = str_replace(',', '.', trim($value));

        if (! is_numeric($value)) {
            return null;
        }

        return $this->formatAmount((float) $value);
    }

    private function normalizeCurrency(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $currency = Str::upper(preg_replace('/[^A-Za-z0-9]/', '', $value) ?? '');

        return $currency === '' ? null : $currency;
    }

    private function normalizeCategorySort(?string $sort): string
    {
        $sort = match ($sort) {
            'available' => 'offers',
            'default', 'best' => 'relevance',
            'name' => 'brand',
            'price', 'price_low' => 'price_asc',
            'price_high' => 'price_desc',
            default => $sort ?? 'relevance',
        };

        return array_key_exists($sort, self::CATEGORY_SORT_OPTION_KEYS) ? $sort : 'relevance';
    }

    private function nominalKey(?string $faceValue, ?string $currency): string
    {
        if ($faceValue === null) {
            return '';
        }

        return $faceValue.'|'.($currency ?? '');
    }

    /**
     * @param  Builder<CanonicalProductIdentity>  $builder
     */
    private function applyPublicIdentityFilters(Builder $builder): void
    {
        // For maximum SEO exposure, we allow all canonical product identities
        // in the database to be publicly visible on the storefront and in the catalog!
    }

    private function applyVisibleBestOfferFilters($query): void
    {
        $query
            ->with(['shop.legalEntity'])
            ->where('is_active', true)
            ->whereHas('shop', fn ($query) => $query->where('is_active', true))
            ->whereHas('salesChannels', function ($query): void {
                $query->where('channel', $this->storefront->storefrontChannel())
                    ->whereColumn('product_sales_channels.shop_id', 'products.shop_id')
                    ->where('is_enabled', true);
            });
    }

    /**
     * @return array<string, mixed>|null
     */
    private function cardForIdentity(CanonicalProductIdentity $identity): ?array
    {
        $canonicalIdentity = $this->curation->applyApprovedOverrides($identity->toArray(), $identity);
        $offer = $this->visibleBestOffer($identity->bestOfferProduct);
        $selectedOfferForPolicy = $offer !== null
            ? [
                'availability' => $offer['availability'],
                'ranking' => ['score' => 50],
                'indexing' => ['indexable' => true],
            ]
            : null;
        $policy = $this->indexingPolicy->forCanonicalProduct(
            $canonicalIdentity,
            $selectedOfferForPolicy,
            ['provider_candidates_count' => $identity->provider_candidates_count],
            $identity,
        );

        if (($policy['surface'] ?? null) !== 'public_index' || ! (bool) ($policy['indexable'] ?? false)) {
            return null;
        }

        $category = (string) ($canonicalIdentity['canonical_category'] ?: config('catalog_taxonomy.default', 'gift_cards'));
        $slug = (string) $canonicalIdentity['identity_slug'];
        $name = $this->displayName($canonicalIdentity, $identity);
        $url = route('meanly.canonical-products.show', $slug);

        return [
            'id' => $identity->id,
            'slug' => $slug,
            'url' => $url,
            'machine_readable_at' => route('llms.catalog.canonical-products.show', $slug),
            'name' => $name,
            'category' => $category,
            'category_label' => $this->categoryResolver->label($category),
            'brand' => $canonicalIdentity['brand'] ?? null,
            'product_family' => $canonicalIdentity['product_family'] ?? null,
            'face_value' => $canonicalIdentity['face_value'] ?? null,
            'face_value_currency' => $canonicalIdentity['face_value_currency'] ?? null,
            'region' => $canonicalIdentity['region'] ?: 'global',
            'confidence' => $canonicalIdentity['confidence'] ?? $identity->confidence,
            'provider_count' => (int) $identity->provider_candidates_count,
            'seller_offer_count' => (int) $identity->seller_offers_count,
            'has_selected_offer' => $offer !== null,
            'selected_offer' => $offer,
            'policy' => $policy,
            'status_label' => $offer !== null ? __('runtime.home.best_offer') : __('runtime.home.provider_network'),
            'cta_label' => $offer !== null ? __('runtime.chat.buy') : __('catalog.index.open'),
            'last_seen_at' => optional($identity->last_seen_at ?: $identity->updated_at)->toAtomString(),
            'last_seen_timestamp' => optional($identity->last_seen_at ?: $identity->updated_at)->getTimestamp() ?? 0,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function visibleBestOffer(?Product $product): ?array
    {
        if ($product === null) {
            return null;
        }

        $price = $this->pricingProjection->publicPriceForProduct($product);
        $availability = $product->shop?->auto_purchase_enabled || $product->auto_replenish_enabled
            ? 'auto_purchase'
            : 'available_to_order';

        return [
            'product_id' => $product->id,
            'sku' => $product->sku,
            'url' => route('meanly.storefront.products.show', $product->slug),
            'name' => $product->name,
            'seller' => [
                'name' => $product->shop?->name ?? 'Meanly seller',
                'legal_entity' => $product->shop?->legalEntity?->short_name ?: $product->shop?->legalEntity?->name,
            ],
            'price' => $price,
            'availability' => $availability,
        ];
    }

    /**
     * @param  array<string, mixed>  $identity
     */
    private function displayName(array $identity, CanonicalProductIdentity $model): string
    {
        $brand = trim((string) ($identity['brand'] ?? ''));
        $family = trim((string) ($identity['product_family'] ?? ''));
        $faceValue = $identity['face_value'] ?? null;
        $currency = trim((string) ($identity['face_value_currency'] ?? ''));
        $region = trim((string) ($identity['region'] ?? ''));

        $parts = collect([
            $brand !== '' ? $brand : null,
            $family !== '' && Str::lower($family) !== Str::lower($brand) ? Str::title($family) : null,
            is_numeric($faceValue) ? $this->formatAmount((float) $faceValue).' '.$currency : null,
            $region !== '' && $region !== 'global' ? Str::upper($region) : null,
        ])->filter()->values();

        if ($parts->isNotEmpty()) {
            return $parts->implode(' ');
        }

        return Str::headline(str_replace('-', ' ', $model->identity_slug));
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $cards
     * @return Collection<int, array<string, mixed>>
     */
    private function categorySummariesFromCards(Collection $cards, ?int $limit = self::CATEGORY_LIMIT): Collection
    {
        $summaries = $cards
            ->groupBy('category')
            ->map(function (Collection $group, string $category): array {
                $meta = (array) config("catalog_taxonomy.categories.{$category}", []);
                $count = $group->count();

                return [
                    'slug' => $category,
                    'name' => $group->first()['category_label'] ?? ($meta['label_ru'] ?? $category),
                    'label_ru' => $meta['label_ru'] ?? $group->first()['category_label'] ?? $category,
                    'label_en' => $meta['label_en'] ?? $category,
                    'description_ru' => $meta['description_ru'] ?? null,
                    'schema_org' => $meta['schema_org'] ?? 'Product',
                    'google_product_category' => $meta['google_product_category'] ?? null,
                    'count' => $count,
                    'product_count' => $count,
                    'seller_offer_count' => $group->filter(fn (array $card) => $card['has_selected_offer'])->count(),
                    'provider_count' => $group->sum('provider_count'),
                    'url' => route('meanly.catalog.categories.show', $category),
                    'machine_readable_url' => route('llms.categories.show', $category),
                ];
            })
            ->sortByDesc('count')
            ->values();

        return $limit === null
            ? $summaries
            : $summaries->take($limit)->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $cards
     * @return Collection<int, array<string, mixed>>
     */
    private function brandSummaries(Collection $cards): Collection
    {
        return $cards
            ->filter(fn (array $card) => !empty($card['brand']))
            ->groupBy('brand')
            ->map(fn (Collection $group, string $brand) => [
                'name' => $brand,
                'count' => $group->count(),
                'seller_offer_count' => $group->filter(fn (array $card) => $card['has_selected_offer'])->count(),
                'url' => route('meanly.catalog.brands.show', Str::slug($brand)),
            ])
            ->sortByDesc('count')
            ->take(12)
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $cards
     * @return Collection<int, array<string, mixed>>
     */
    private function sortCards(Collection $cards): Collection
    {
        return $cards
            ->sortBy([
                ['search_score', 'desc'],
                ['has_selected_offer', 'desc'],
                ['seller_offer_count', 'desc'],
                ['provider_count', 'desc'],
                ['last_seen_timestamp', 'desc'],
            ])
            ->values();
    }

    /**
     * @param  Collection<int, string>  $needleVariants
     * @param  Collection<int, Collection<int, string>>  $tokenGroups
     */
    private function cardSearchScore(string $haystack, string $needle, Collection $needleVariants, Collection $tokenGroups): int
    {
        if ($needle === '' || $haystack === '') {
            return 0;
        }

        $score = 0;

        if ($needleVariants->contains(fn (string $variant): bool => $variant !== '' && str_contains($haystack, $variant))) {
            $score += 70;
        }

        $matchedTokens = $tokenGroups
            ->filter(fn (Collection $variants): bool => $variants->contains(
                fn (string $token): bool => $token !== '' && str_contains($haystack, $token)
            ))
            ->count();

        if ($tokenGroups->isNotEmpty() && $matchedTokens === $tokenGroups->count()) {
            $score += 40;
        }

        $score += $matchedTokens * 12;

        if ($matchedTokens === 0) {
            $score += $this->fuzzyTokenScore($haystack, $tokenGroups);
        }

        return $score;
    }

    /**
     * @param  array<string, mixed>  $card
     */
    private function cardSearchText(array $card): string
    {
        $offer = (array) ($card['selected_offer'] ?? []);

        return $this->normalizeSearchText(implode(' ', array_filter([
            $card['slug'] ?? null,
            $card['name'] ?? null,
            $card['category'] ?? null,
            $card['category_label'] ?? null,
            $card['brand'] ?? null,
            $card['product_family'] ?? null,
            $card['face_value'] ?? null,
            $card['face_value_currency'] ?? null,
            $card['region'] ?? null,
            $card['status_label'] ?? null,
            data_get($offer, 'name'),
            data_get($offer, 'seller.name'),
            data_get($offer, 'seller.legal_entity'),
            data_get($offer, 'availability'),
            data_get($offer, 'price.amount'),
            data_get($offer, 'price.currency'),
        ], fn ($value): bool => $value !== null && $value !== '')));
    }

    /**
     * @return array<string, int>
     */
    private function stats(): array
    {
        if (! $this->identityTablesExist()) {
            return [
                'total_canonical_products' => 0,
                'provider_backed_products' => 0,
                'seller_offer_products' => 0,
                'public_storefront_products' => 0,
                'review_excluded_products' => 0,
            ];
        }

        $providerBacked = CanonicalProductIdentity::query()
            ->where('provider_candidates_count', '>', 0)
            ->count();

        $publicQuery = CanonicalProductIdentity::query();
        $this->applyPublicIdentityFilters($publicQuery);
        $publicCount = $publicQuery->count();

        return [
            'total_canonical_products' => CanonicalProductIdentity::query()->count(),
            'provider_backed_products' => $providerBacked,
            'seller_offer_products' => CanonicalProductIdentity::query()
                ->where(function ($query): void {
                    $query->where('seller_offers_count', '>', 0)
                        ->orWhereNotNull('best_offer_product_id');
                })
                ->count(),
            'public_storefront_products' => $publicCount,
            'review_excluded_products' => max(0, $providerBacked - $publicCount),
        ];
    }

    /**
     * @return array<int, array{label: string, query: string}>
     */
    private function quickChips(): array
    {
        return [
            ['label' => __('runtime.home.steam_turkey'), 'query' => 'steam turkey'],
            ['label' => __('runtime.home.playstation_us'), 'query' => 'playstation us'],
            ['label' => __('runtime.home.spotify_subscription'), 'query' => 'spotify subscription'],
            ['label' => 'Xbox gift card', 'query' => 'xbox gift card'],
            ['label' => __('runtime.home.card_20_eur'), 'query' => '20 eur'],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function categoryKeysMatchingToken(string $token): array
    {
        $token = $this->normalizeSearchText($token);
        if ($token === '') {
            return [];
        }

        return collect((array) config('catalog_taxonomy.categories', []))
            ->filter(function (array $meta, string $category) use ($token): bool {
                $haystack = $this->normalizeSearchText(implode(' ', [
                    $category,
                    $meta['label_ru'] ?? '',
                    $meta['label_en'] ?? '',
                    $meta['description_ru'] ?? '',
                    $meta['schema_org'] ?? '',
                    $meta['google_product_category'] ?? '',
                ]));

                return str_contains($haystack, $token);
            })
            ->keys()
            ->values()
            ->all();
    }

    private function identityTablesExist(): bool
    {
        return $this->identityTablesExist ??= Schema::hasTable('canonical_product_identities')
            && Schema::hasTable('canonical_product_identity_sources');
    }

    private function overrideTableExists(): bool
    {
        return $this->overrideTableExists ??= Schema::hasTable('canonical_product_identity_overrides');
    }

    private function formatAmount(float $amount): string
    {
        return rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.');
    }

    /**
     * @return Collection<int, Collection<int, string>>
     */
    private function searchTokenGroups(string $query): Collection
    {
        return collect(explode(' ', $this->normalizeSearchText($query)))
            ->filter()
            ->unique()
            ->take(8)
            ->map(fn (string $token): Collection => collect($this->expandSearchToken($token))
                ->map(fn (string $variant): string => $this->normalizeSearchText($variant))
                ->filter()
                ->unique()
                ->values())
            ->filter(fn (Collection $variants): bool => $variants->isNotEmpty())
            ->values();
    }

    /**
     * @return array<int, string>
     */
    private function searchTextVariants(string $query): array
    {
        $variants = [
            $query,
            $this->convertKeyboardLayout($query, 'ru_to_en'),
            $this->convertKeyboardLayout($query, 'en_to_ru'),
            $this->transliterateCyrillicToLatin($query),
        ];

        return collect($variants)
            ->map(fn (string $variant): string => $this->normalizeSearchText($variant))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function expandSearchToken(string $token): array
    {
        $queue = collect([
            $token,
            $this->convertKeyboardLayout($token, 'ru_to_en'),
            $this->convertKeyboardLayout($token, 'en_to_ru'),
            $this->transliterateCyrillicToLatin($token),
        ])
            ->map(fn (string $variant): string => $this->normalizeSearchText($variant))
            ->filter()
            ->unique()
            ->values();

        $expanded = $queue->all();

        foreach ($queue as $variant) {
            $expanded = array_merge($expanded, self::SEARCH_SYNONYMS[$variant] ?? []);
            $expanded = array_merge($expanded, $this->fuzzyKnownTerms($variant));
        }

        return collect($expanded)
            ->map(fn (string $variant): string => $this->normalizeSearchText($variant))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function fuzzyKnownTerms(string $token): array
    {
        if (Str::length($token) < 4 || preg_match('/[^a-z0-9]/', $token)) {
            return [];
        }

        return collect(self::FUZZY_BRAND_TERMS)
            ->filter(function (string $term) use ($token): bool {
                if (str_starts_with($term, $token) || str_starts_with($token, $term)) {
                    return true;
                }

                $maxDistance = max(1, min(3, (int) floor(max(strlen($token), strlen($term)) / 4)));

                return levenshtein($token, $term) <= $maxDistance;
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Collection<int, string>>  $tokenGroups
     */
    private function fuzzyTokenScore(string $haystack, Collection $tokenGroups): int
    {
        $words = collect(explode(' ', $haystack))
            ->filter(fn (string $word): bool => strlen($word) >= 4)
            ->unique()
            ->values();

        if ($words->isEmpty()) {
            return 0;
        }

        $matched = $tokenGroups
            ->filter(fn (Collection $variants): bool => $variants->contains(
                fn (string $variant): bool => $this->fuzzyVariantMatchesWords($variant, $words)
            ))
            ->count();

        return $matched * 8;
    }

    /**
     * @param  Collection<int, string>  $words
     */
    private function fuzzyVariantMatchesWords(string $variant, Collection $words): bool
    {
        if (strlen($variant) < 4 || preg_match('/[^a-z0-9]/', $variant)) {
            return false;
        }

        return $words->contains(function (string $word) use ($variant): bool {
            if (preg_match('/[^a-z0-9]/', $word)) {
                return false;
            }

            if (str_starts_with($word, $variant) || str_starts_with($variant, $word)) {
                return true;
            }

            $maxDistance = max(1, min(3, (int) floor(max(strlen($variant), strlen($word)) / 4)));

            return levenshtein($variant, $word) <= $maxDistance;
        });
    }

    private function convertKeyboardLayout(string $value, string $direction): string
    {
        $ru = 'йцукенгшщзхъфывапролджэячсмитьбюё';
        $en = 'qwertyuiop[]asdfghjkl;\'zxcvbnm,.`';
        $from = $direction === 'ru_to_en' ? $ru : $en;
        $to = $direction === 'ru_to_en' ? $en : $ru;

        return strtr(Str::lower($value), array_combine(
            preg_split('//u', $from, -1, PREG_SPLIT_NO_EMPTY) ?: [],
            preg_split('//u', $to, -1, PREG_SPLIT_NO_EMPTY) ?: [],
        ) ?: []);
    }

    private function transliterateCyrillicToLatin(string $value): string
    {
        return strtr(Str::lower($value), [
            'а' => 'a',
            'б' => 'b',
            'в' => 'v',
            'г' => 'g',
            'д' => 'd',
            'е' => 'e',
            'ё' => 'e',
            'ж' => 'zh',
            'з' => 'z',
            'и' => 'i',
            'й' => 'y',
            'к' => 'k',
            'л' => 'l',
            'м' => 'm',
            'н' => 'n',
            'о' => 'o',
            'п' => 'p',
            'р' => 'r',
            'с' => 's',
            'т' => 't',
            'у' => 'u',
            'ф' => 'f',
            'х' => 'h',
            'ц' => 'ts',
            'ч' => 'ch',
            'ш' => 'sh',
            'щ' => 'sch',
            'ъ' => '',
            'ы' => 'y',
            'ь' => '',
            'э' => 'e',
            'ю' => 'yu',
            'я' => 'ya',
        ]);
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '\\%_');
    }

    private function normalizeSearchText(string $value): string
    {
        return Str::of($value)
            ->lower()
            ->replaceMatches('/[^\pL\pN]+/u', ' ')
            ->squish()
            ->toString();
    }
}
