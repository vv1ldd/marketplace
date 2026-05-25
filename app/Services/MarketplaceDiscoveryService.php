<?php

namespace App\Services;

use App\Models\CatalogGroup;
use App\Models\MarketplaceFavorite;
use App\Models\Order\Order;
use App\Models\Order\OrderItems;
use App\Models\Product;
use App\Models\ProviderProduct;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MarketplaceDiscoveryService
{
    public function __construct(
        private readonly MeanlyFirstPartyStorefrontService $storefront,
        private readonly MarketplaceDiscoveryExplanationService $explainer,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function homepage(Request $request, Shop $shop): array
    {
        $intentText = trim((string) ($request->query('intent') ?? $request->query('q') ?? ''));
        $intent = $this->parseIntent($intentText);
        $allProducts = $this->storefront->marketplaceProductsQuery()
            ->latest('id')
            ->get();

        $offerSignals = $this->offerSignals($allProducts);
        $ranked = $this->bestOfferMatches($this->rankProducts($allProducts, $intent, $offerSignals));
        $bestOfferProducts = $ranked->map(fn (array $match) => $match['product'])->values();
        $intentResults = $ranked
            ->filter(fn (array $match) => (int) $match['match_score'] > 0)
            ->take(6)
            ->values();

        return [
            'intent_text' => $intentText,
            'intent' => $intent,
            'intent_summary' => $this->intentSummary($intent),
            'intent_results' => $intentResults,
            'ai_explanation' => $this->explainer->explain($intent, $intentResults),
            'quick_chips' => $this->quickChips(),
            'browse_products' => $this->browseProducts($request, $shop),
            'popular' => $this->popularProducts($shop, $bestOfferProducts),
            'frequently_bought' => $this->frequentlyBought($shop, $bestOfferProducts),
            'categories' => $this->categories($allProducts),
            'favorites' => $this->productsByIds($shop, $this->favoriteIds($request, $shop)),
            'recently_viewed' => $this->productsByIds($shop, $this->recentIds($request)),
            'favorite_ids' => $this->favoriteIds($request, $shop),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function parseIntent(string $text): array
    {
        $normalized = Str::of($text)->lower()->replace(['ё'], ['е'])->toString();
        $tokens = collect(preg_split('/[\s,.;:!?]+/u', $normalized) ?: [])->filter()->values();

        $platforms = [
            'steam' => ['steam', 'стим'],
            'playstation' => ['playstation', 'psn', 'ps', 'плейстейшн', 'псн'],
            'xbox' => ['xbox', 'иксбокс'],
            'spotify' => ['spotify', 'спотифай'],
            'netflix' => ['netflix', 'нетфликс'],
            'apple' => ['apple', 'itunes', 'appstore', 'эпл'],
            'google' => ['google', 'play', 'гугл'],
        ];
        $regions = [
            'TR' => ['turkey', 'turkiye', 'турция', 'турецк', 'try'],
            'US' => ['usa', 'us', 'сша', 'америка', 'usd'],
            'EU' => ['europe', 'eu', 'европа', 'eur', 'евро'],
            'RU' => ['russia', 'ru', 'россия', 'руб', 'рублей', 'rub'],
        ];
        $classes = [
            'gift_card' => ['gift', 'card', 'карта', 'подарочн', 'сертификат'],
            'subscription' => ['subscription', 'подписк', 'месяц'],
            'game_key' => ['key', 'ключ', 'игр'],
            'top_up' => ['topup', 'top-up', 'пополн', 'баланс'],
        ];

        return [
            'raw' => $text,
            'normalized' => $normalized,
            'tokens' => $tokens->all(),
            'platform' => $this->matchDictionary($normalized, $platforms),
            'region' => $this->matchDictionary($normalized, $regions),
            'intent_class' => $this->matchDictionary($normalized, $classes),
            'amount' => $this->extractAmount($normalized),
        ];
    }

    /**
     * @param Collection<int, Product> $products
     * @param array<string, mixed> $intent
     * @param array<string, mixed>|null $offerSignals
     * @return Collection<int, array{product: Product, score: int, match_score: int, offer_score: int, reasons: array<int, string>, offer_badges: array<int, string>, metrics: array<string, mixed>}>
     */
    public function rankProducts(Collection $products, array $intent, ?array $offerSignals = null): Collection
    {
        $offerSignals ??= $this->offerSignals($products);

        return $products
            ->map(function (Product $product) use ($intent, $offerSignals) {
                $haystack = Str::of(implode(' ', [
                    $product->name,
                    $product->category,
                    $product->market_category_name,
                    $product->vendor,
                    $product->sku,
                    $product->description,
                ]))->lower()->replace(['ё'], ['е'])->toString();

                $matchScore = 0;
                $reasons = [];

                if ($intent['platform'] && str_contains($haystack, strtolower((string) $intent['platform']))) {
                    $matchScore += 45;
                    $reasons[] = 'платформа совпадает';
                }
                if ($intent['region'] && str_contains($haystack, strtolower((string) $intent['region']))) {
                    $matchScore += 25;
                    $reasons[] = 'регион совпадает';
                }
                if ($intent['intent_class'] && $this->productMatchesIntentClass($haystack, (string) $intent['intent_class'])) {
                    $matchScore += 18;
                    $reasons[] = 'тип товара подходит';
                }
                if ($intent['amount']) {
                    $priceRub = ((float) ($product->price_rub ?? 0)) / 100;
                    $distance = abs($priceRub - (float) $intent['amount']);
                    if ($priceRub > 0 && $distance <= max(250, (float) $intent['amount'] * 0.35)) {
                        $matchScore += 16;
                        $reasons[] = 'цена близка к запросу';
                    }
                }

                foreach ($intent['tokens'] ?? [] as $token) {
                    if (mb_strlen((string) $token) >= 3 && str_contains($haystack, (string) $token)) {
                        $matchScore += 4;
                    }
                }

                $offer = $this->offerScore($product, $offerSignals);
                $score = (($intent['raw'] ?? '') !== '' ? $matchScore * 10 : 0) + $offer['score'];

                $product->setAttribute('marketplace_match_score', $matchScore);
                $product->setAttribute('marketplace_offer_score', $offer['score']);
                $product->setAttribute('marketplace_total_score', $score);
                $product->setAttribute('marketplace_offer_badges', $offer['badges']);
                $product->setAttribute('marketplace_offer_metrics', $offer['metrics']);

                return [
                    'product' => $product,
                    'score' => $score,
                    'match_score' => $matchScore,
                    'offer_score' => $offer['score'],
                    'reasons' => array_values(array_unique($reasons ?: ['похожий товар'])),
                    'offer_badges' => $offer['badges'],
                    'metrics' => $offer['metrics'],
                ];
            })
            ->sort(function (array $left, array $right) {
                if ($left['score'] !== $right['score']) {
                    return $right['score'] <=> $left['score'];
                }

                $leftPrice = (float) ($left['product']->price_rub ?? 0);
                $rightPrice = (float) ($right['product']->price_rub ?? 0);
                if ($leftPrice !== $rightPrice) {
                    return $leftPrice <=> $rightPrice;
                }

                return ((int) $left['product']->id) <=> ((int) $right['product']->id);
            })
            ->values();
    }

    /**
     * Keep one public offer per canonical provider product. The kept offer is
     * still selected deterministically by total score, then price, then id.
     *
     * @param Collection<int, array{product: Product, score: int}> $matches
     * @return Collection<int, array<string, mixed>>
     */
    public function bestOfferMatches(Collection $matches): Collection
    {
        return $matches
            ->groupBy(fn (array $match) => $this->canonicalProductKey($match['product']))
            ->map(fn (Collection $group) => $group->sort($this->matchComparator(...))->first())
            ->sort($this->matchComparator(...))
            ->values();
    }

    public function rememberRecentlyViewed(Request $request, Product $product): void
    {
        $ids = collect($request->session()->get('marketplace_recently_viewed', []))
            ->reject(fn ($id) => (int) $id === (int) $product->id)
            ->prepend($product->id)
            ->take(12)
            ->values()
            ->all();

        $request->session()->put('marketplace_recently_viewed', $ids);
    }

    /**
     * @return array<string, mixed>
     */
    public function toggleFavorite(Request $request, Product $product, Shop $shop): array
    {
        $sessionIds = collect($request->session()->get('marketplace_favorites', []))->map(fn ($id) => (int) $id);
        $isFavorite = $sessionIds->contains((int) $product->id);

        if ($isFavorite) {
            $sessionIds = $sessionIds->reject(fn ($id) => (int) $id === (int) $product->id);
        } else {
            $sessionIds = $sessionIds->prepend($product->id)->unique()->take(100);
        }

        $request->session()->put('marketplace_favorites', $sessionIds->values()->all());

        if ($request->user() && Schema::hasTable('marketplace_favorites')) {
            if ($isFavorite) {
                MarketplaceFavorite::query()
                    ->where('user_id', $request->user()->id)
                    ->where('product_id', $product->id)
                    ->delete();
            } else {
                MarketplaceFavorite::query()->firstOrCreate([
                    'user_id' => $request->user()->id,
                    'product_id' => $product->id,
                ], [
                    'shop_id' => $product->shop_id,
                    'session_id' => $request->session()->getId(),
                ]);
            }
        }

        return [
            'favorite' => ! $isFavorite,
            'favorite_ids' => $this->favoriteIds($request, $shop),
        ];
    }

    /**
     * @return LengthAwarePaginator<Product>
     */
    private function browseProducts(Request $request, Shop $shop): LengthAwarePaginator
    {
        $query = trim((string) ($request->query('q') ?? ''));
        $intentText = trim((string) ($request->query('intent') ?? $query));
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 24;

        $products = $this->storefront->marketplaceProductsQuery()
            ->when($query !== '', function ($builder) use ($query) {
                $builder->where(function ($inner) use ($query) {
                    $inner->where('name', 'like', "%{$query}%")
                        ->orWhere('category', 'like', "%{$query}%")
                        ->orWhere('vendor', 'like', "%{$query}%");
                });
            })
            ->latest('id')
            ->get();

        $rankedProducts = $this->bestOfferMatches($this->rankProducts($products, $this->parseIntent($intentText)))
            ->map(fn (array $match) => $match['product'])
            ->values();

        return (new LengthAwarePaginator(
            $rankedProducts->forPage($page, $perPage)->values(),
            $rankedProducts->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ],
        ))->withQueryString();
    }

    /**
     * @param Collection<int, Product> $products
     * @return array<string, mixed>
     */
    private function offerSignals(Collection $products): array
    {
        $productIds = $products->pluck('id')->filter()->values();
        $shopIds = $products->pluck('shop_id')->filter()->unique()->values();
        $prices = $products
            ->map(fn (Product $product) => ((float) ($product->price_rub ?? 0)) / 100)
            ->filter(fn (float $price) => $price > 0)
            ->values();

        $sales = OrderItems::query()
            ->selectRaw('orders.shop_id, order_items.sku, SUM(order_items.count) as sold_count')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.shop_id', $shopIds)
            ->where('orders.sales_channel', $this->storefront->storefrontChannel())
            ->where('orders.created_at', '>=', now()->subDays(30))
            ->groupBy('orders.shop_id', 'order_items.sku')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->shop_id.'|'.$row->sku => (int) $row->sold_count]);

        $sellerOrders = Order::query()
            ->selectRaw("shop_id, COUNT(*) as total_orders, SUM(CASE WHEN status IN ('COMPLETED', 'DELIVERED', 'PAID') OR progress_id = 4 THEN 1 ELSE 0 END) as completed_orders")
            ->whereIn('shop_id', $shopIds)
            ->where('sales_channel', $this->storefront->storefrontChannel())
            ->where('created_at', '>=', now()->subDays(90))
            ->groupBy('shop_id')
            ->get()
            ->keyBy('shop_id');

        $stock = \App\Models\WarehouseStock::query()
            ->selectRaw('product_id, SUM(count) as stock_count')
            ->whereIn('product_id', $productIds)
            ->groupBy('product_id')
            ->pluck('stock_count', 'product_id');

        return [
            'min_price' => $prices->min(),
            'max_price' => $prices->max(),
            'max_sales' => max(1, (int) $sales->max()),
            'sales' => $sales,
            'seller_orders' => $sellerOrders,
            'stock' => $stock,
        ];
    }

    /**
     * @param array<string, mixed> $signals
     * @return array{score: int, badges: array<int, string>, metrics: array<string, mixed>}
     */
    private function offerScore(Product $product, array $signals): array
    {
        $score = 0;
        $badges = [];
        $priceRub = ((float) ($product->price_rub ?? 0)) / 100;
        $minPrice = (float) ($signals['min_price'] ?? 0);
        $maxPrice = (float) ($signals['max_price'] ?? 0);

        if ($priceRub > 0 && $minPrice > 0 && $maxPrice > 0) {
            $range = max(1, $maxPrice - $minPrice);
            $priceScore = $maxPrice === $minPrice ? 22 : (int) round(30 * (1 - (($priceRub - $minPrice) / $range)));
            $score += max(0, min(30, $priceScore));

            if ($priceRub <= $minPrice) {
                $badges[] = 'лучшая цена';
            } elseif ($priceScore >= 22) {
                $badges[] = 'хорошая цена';
            }
        }

        $stockCount = (int) (($signals['stock'][$product->id] ?? 0));
        if ($stockCount > 0) {
            $score += min(20, 10 + $stockCount);
            $badges[] = 'есть в наличии';
        } elseif ($product->shop?->auto_purchase_enabled || $product->auto_replenish_enabled) {
            $score += 8;
            $badges[] = 'автозакупка';
        }

        $salesKey = $product->shop_id.'|'.$product->sku;
        $soldCount = (int) (($signals['sales'][$salesKey] ?? 0));
        if ($soldCount > 0) {
            $score += min(15, (int) round(15 * ($soldCount / max(1, (int) $signals['max_sales']))));
            $badges[] = 'покупают чаще';
        }

        $sellerOrders = $signals['seller_orders'][$product->shop_id] ?? null;
        $totalOrders = (int) ($sellerOrders?->total_orders ?? 0);
        $completedOrders = (int) ($sellerOrders?->completed_orders ?? 0);
        if ($totalOrders > 0) {
            $completionRate = $completedOrders / max(1, $totalOrders);
            $sellerScore = (int) round(($completionRate * 15) + (min($totalOrders, 20) / 20 * 10));
            $score += $sellerScore;
            if ($completionRate >= 0.9 && $totalOrders >= 3) {
                $badges[] = 'надежный продавец';
            }
        } else {
            $score += 8;
            $badges[] = 'новый продавец';
        }

        $yandex = $this->yandexSignals($product);
        if ($yandex['rating'] !== null) {
            $score += (int) round(min(5.0, max(0.0, $yandex['rating'])) / 5 * 12);
            if ($yandex['rating'] >= 4.5) {
                $badges[] = 'высокий рейтинг';
            }
        }

        if ($yandex['reviews_count'] > 0) {
            $score += min(8, (int) floor(log($yandex['reviews_count'] + 1, 2)));
            $badges[] = 'есть отзывы';
        }

        if ($yandex['price_competitiveness'] === 'OPTIMAL') {
            $score += 8;
            $badges[] = 'цена хороша на Маркете';
        } elseif ($yandex['price_competitiveness'] === 'LOW') {
            $score -= 6;
        }

        if ($yandex['published']) {
            $score += 4;
            $badges[] = 'есть на Яндекс.Маркете';
        }

        return [
            'score' => $score,
            'badges' => array_values(array_unique($badges)),
            'metrics' => [
                'price_rub' => $priceRub,
                'stock_count' => $stockCount,
                'sold_30_days' => $soldCount,
                'seller_orders_90_days' => $totalOrders,
                'seller_completed_90_days' => $completedOrders,
                'yandex_rating' => $yandex['rating'],
                'yandex_reviews_count' => $yandex['reviews_count'],
                'yandex_price_competitiveness' => $yandex['price_competitiveness'],
                'yandex_published' => $yandex['published'],
            ],
        ];
    }

    /**
     * @return array{rating: float|null, reviews_count: int, price_competitiveness: string|null, published: bool}
     */
    private function yandexSignals(Product $product): array
    {
        $raw = $product->data['ym_raw'] ?? [];

        $rating = data_get($raw, 'offer.rating')
            ?? data_get($raw, 'mapping.rating')
            ?? data_get($raw, 'statistics.rating')
            ?? data_get($raw, 'offer.averageRating')
            ?? data_get($raw, 'offer.modelRating')
            ?? data_get($product->data ?? [], 'yandex.rating');

        $reviewsCount = data_get($raw, 'offer.reviewsCount')
            ?? data_get($raw, 'mapping.reviewsCount')
            ?? data_get($raw, 'statistics.reviewsCount')
            ?? data_get($raw, 'offer.opinions')
            ?? data_get($product->data ?? [], 'yandex.reviews_count')
            ?? 0;

        $priceCompetitiveness = $product->price_competitiveness?->value
            ?? (is_string($product->price_competitiveness) ? $product->price_competitiveness : null)
            ?? data_get($raw, 'priceCompetitiveness')
            ?? data_get($raw, 'offer.priceCompetitiveness')
            ?? data_get($raw, 'mapping.priceCompetitiveness');

        return [
            'rating' => is_numeric($rating) ? (float) $rating : null,
            'reviews_count' => is_numeric($reviewsCount) ? (int) $reviewsCount : 0,
            'price_competitiveness' => is_string($priceCompetitiveness) ? strtoupper($priceCompetitiveness) : null,
            'published' => filled($product->ym_url)
                || filled(data_get($raw, 'offer.marketSku'))
                || filled(data_get($raw, 'mapping.marketSku'))
                || filled(data_get($raw, 'offer.offerId')),
        ];
    }

    /**
     * @param Collection<int, Product> $products
     * @return Collection<int, Product>
     */
    private function popularProducts(Shop $shop, Collection $products): Collection
    {
        $counts = OrderItems::query()
            ->selectRaw('orders.shop_id, order_items.sku, SUM(order_items.count) as sold_count')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.sales_channel', $this->storefront->storefrontChannel())
            ->where('orders.created_at', '>=', now()->subDays(30))
            ->groupBy('orders.shop_id', 'order_items.sku')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->shop_id.'|'.$row->sku => (int) $row->sold_count]);

        return $products
            ->sortByDesc(fn (Product $product) => (int) ($counts[$product->shop_id.'|'.$product->sku] ?? 0))
            ->unique(fn (Product $product) => $this->canonicalProductKey($product))
            ->take(8)
            ->values();
    }

    /**
     * @param Collection<int, Product> $products
     * @return Collection<int, Product>
     */
    private function frequentlyBought(Shop $shop, Collection $products): Collection
    {
        $orderCounts = OrderItems::query()
            ->selectRaw('orders.shop_id, order_items.sku, COUNT(DISTINCT orders.id) as order_count')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.sales_channel', $this->storefront->storefrontChannel())
            ->where('orders.created_at', '>=', now()->subDays(60))
            ->groupBy('orders.shop_id', 'order_items.sku')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->shop_id.'|'.$row->sku => (int) $row->order_count]);

        $frequent = $products
            ->filter(fn (Product $product) => (int) ($orderCounts[$product->shop_id.'|'.$product->sku] ?? 0) > 1)
            ->sortByDesc(fn (Product $product) => (int) ($orderCounts[$product->shop_id.'|'.$product->sku] ?? 0))
            ->unique(fn (Product $product) => $this->canonicalProductKey($product))
            ->take(8)
            ->values();

        return $frequent->isNotEmpty()
            ? $frequent
            : $products->unique(fn (Product $product) => $this->canonicalProductKey($product))->take(8)->values();
    }

    /**
     * @param array{product: Product, score: int} $left
     * @param array{product: Product, score: int} $right
     */
    private function matchComparator(array $left, array $right): int
    {
        if ($left['score'] !== $right['score']) {
            return $right['score'] <=> $left['score'];
        }

        $leftPrice = (float) ($left['product']->price_rub ?? 0);
        $rightPrice = (float) ($right['product']->price_rub ?? 0);
        if ($leftPrice !== $rightPrice) {
            return $leftPrice <=> $rightPrice;
        }

        return ((int) $left['product']->id) <=> ((int) $right['product']->id);
    }

    private function canonicalProductKey(Product $product): string
    {
        return trim((string) ($product->wildflow_catalog_sku ?: $product->sku)) ?: 'product:'.$product->id;
    }

    /**
     * @param Collection<int, Product> $products
     * @return Collection<int, array<string, mixed>>
     */
    private function categories(Collection $products): Collection
    {
        $baseProviderProducts = ProviderProduct::query()
            ->where('is_active', true)
            ->whereHas('provider', fn ($query) => $query->where('is_active', true));

        $groups = collect(CatalogGroup::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function (CatalogGroup $group) use ($baseProviderProducts) {
                return [
                    'name' => $group->name,
                    'slug' => $group->slug,
                    'icon' => $group->icon,
                    'count' => (clone $baseProviderProducts)
                        ->whereHas('brand', fn ($query) => $query->where('catalog_group_id', $group->id))
                        ->count(),
                    'sample_products' => collect(),
                    'source' => 'catalog_groups',
                ];
            })
            ->filter(fn (array $category) => (int) $category['count'] > 0)
            ->all());

        $providerCategories = collect();
        if ($groups->isEmpty()) {
            $providerCategories = (clone $baseProviderProducts)
                ->selectRaw('category, COUNT(*) as products_count')
                ->whereNotNull('category')
                ->where('category', '!=', '')
                ->groupBy('category')
                ->orderByDesc('products_count')
                ->limit(12)
                ->get()
                ->map(fn ($row) => [
                    'name' => (string) $row->category,
                    'slug' => Str::slug((string) $row->category),
                    'icon' => null,
                    'count' => (int) $row->products_count,
                    'sample_products' => collect(),
                    'source' => 'provider_products',
                ]);
        }

        $categories = $groups->isNotEmpty() ? $groups : $providerCategories;
        if ($categories->isEmpty()) {
            $categories = $this->defaultCategories();
        }

        return $categories
            ->unique(fn (array $category) => Str::lower((string) $category['name']))
            ->take(12)
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function defaultCategories(): Collection
    {
        return collect([
            ['name' => 'Подарочные карты', 'slug' => 'gift-cards', 'icon' => null, 'count' => 0, 'sample_products' => collect(), 'source' => 'default'],
            ['name' => 'Подписки', 'slug' => 'subscriptions', 'icon' => null, 'count' => 0, 'sample_products' => collect(), 'source' => 'default'],
            ['name' => 'Игровые ключи', 'slug' => 'game-keys', 'icon' => null, 'count' => 0, 'sample_products' => collect(), 'source' => 'default'],
            ['name' => 'Пополнение баланса', 'slug' => 'top-up', 'icon' => null, 'count' => 0, 'sample_products' => collect(), 'source' => 'default'],
            ['name' => 'Steam', 'slug' => 'steam', 'icon' => null, 'count' => 0, 'sample_products' => collect(), 'source' => 'default'],
            ['name' => 'PlayStation', 'slug' => 'playstation', 'icon' => null, 'count' => 0, 'sample_products' => collect(), 'source' => 'default'],
            ['name' => 'Xbox', 'slug' => 'xbox', 'icon' => null, 'count' => 0, 'sample_products' => collect(), 'source' => 'default'],
            ['name' => 'Spotify', 'slug' => 'spotify', 'icon' => null, 'count' => 0, 'sample_products' => collect(), 'source' => 'default'],
        ]);
    }

    /**
     * @return array<int, int>
     */
    private function favoriteIds(Request $request, Shop $shop): array
    {
        $ids = collect($request->session()->get('marketplace_favorites', []))->map(fn ($id) => (int) $id);

        if ($request->user() && Schema::hasTable('marketplace_favorites')) {
            $ids = $ids->merge(MarketplaceFavorite::query()
                ->where('user_id', $request->user()->id)
                ->pluck('product_id')
                ->map(fn ($id) => (int) $id));
        }

        return $ids->unique()->values()->all();
    }

    /**
     * @return array<int, int>
     */
    private function recentIds(Request $request): array
    {
        return collect($request->session()->get('marketplace_recently_viewed', []))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->take(12)
            ->values()
            ->all();
    }

    /**
     * @param array<int, int> $ids
     * @return Collection<int, Product>
     */
    private function productsByIds(Shop $shop, array $ids): Collection
    {
        if ($ids === []) {
            return collect();
        }

        $order = array_flip($ids);

        return $this->storefront->marketplaceProductsQuery()
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn (Product $product) => $order[$product->id] ?? 9999)
            ->values();
    }

    /**
     * @param array<string, array<int, string>> $dictionary
     */
    private function matchDictionary(string $text, array $dictionary): ?string
    {
        foreach ($dictionary as $key => $words) {
            foreach ($words as $word) {
                if (str_contains($text, $word)) {
                    return (string) $key;
                }
            }
        }

        return null;
    }

    private function extractAmount(string $text): ?float
    {
        if (preg_match('/(?:на|около|до)?\s*(\d{2,6})(?:\s*)(?:руб|₽|rub|eur|euro|евро|usd|dollar|try|лир)?/u', $text, $matches)) {
            return (float) $matches[1];
        }

        return null;
    }

    private function productMatchesIntentClass(string $haystack, string $intentClass): bool
    {
        return match ($intentClass) {
            'gift_card' => str_contains($haystack, 'card') || str_contains($haystack, 'карта') || str_contains($haystack, 'gift'),
            'subscription' => str_contains($haystack, 'subscription') || str_contains($haystack, 'подпис'),
            'game_key' => str_contains($haystack, 'key') || str_contains($haystack, 'ключ') || str_contains($haystack, 'game'),
            'top_up' => str_contains($haystack, 'top') || str_contains($haystack, 'пополн') || str_contains($haystack, 'balance'),
            default => false,
        };
    }

    /**
     * @param array<string, mixed> $intent
     */
    private function intentSummary(array $intent): ?string
    {
        if (($intent['raw'] ?? '') === '') {
            return null;
        }

        $parts = [];
        if ($intent['platform']) {
            $parts[] = 'платформа: '.Str::headline((string) $intent['platform']);
        }
        if ($intent['region']) {
            $parts[] = 'регион: '.$intent['region'];
        }
        if ($intent['amount']) {
            $parts[] = 'бюджет около '.number_format((float) $intent['amount'], 0, '.', ' ').' ₽';
        }
        if ($intent['intent_class']) {
            $parts[] = 'тип: '.str_replace('_', ' ', (string) $intent['intent_class']);
        }

        return $parts ? 'Понял: '.implode(', ', $parts).'.' : 'Понял запрос, показываю самые близкие товары.';
    }

    /**
     * @return array<int, array{label: string, query: string}>
     */
    private function quickChips(): array
    {
        return [
            ['label' => 'Steam Турция', 'query' => 'хочу Steam Турция'],
            ['label' => 'PlayStation США', 'query' => 'нужна карта PlayStation США'],
            ['label' => 'Spotify подписка', 'query' => 'хочу Spotify подписку'],
            ['label' => 'Xbox gift card', 'query' => 'xbox gift card'],
            ['label' => 'Карта на 1000 ₽', 'query' => 'подарочная карта на 1000 рублей'],
        ];
    }
}
