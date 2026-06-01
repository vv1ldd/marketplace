<?php

namespace App\Services;

use App\Models\ProviderProduct;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ProviderNetworkCatalogService
{
    public function __construct(
        private readonly CanonicalCategoryResolver $categoryResolver,
        private readonly SellerOfferRankingService $offerRanking,
        private readonly CanonicalProductIdentityService $identity,
        private readonly ProductIndexingPolicyService $indexingPolicy,
    ) {}

    /**
     * @return Builder<ProviderProduct>
     */
    public function candidatesQuery(?string $category = null): Builder
    {
        return ProviderProduct::query()
            ->with(['brand', 'region'])
            ->where('is_active', true)
            ->whereNotNull('canonical_category')
            ->whereHas('provider', fn ($query) => $query->where('is_active', true))
            ->where(function ($query) {
                $query->where('retail_price', '>', 0)
                    ->orWhere('purchase_price', '>', 0)
                    ->orWhere('min_price', '>', 0);
            })
            ->whereNotIn('currency', ['EZD', 'XXX'])
            ->where('name', 'not like', '%test%')
            ->where('name', 'not like', '%reject%')
            ->where('name', 'not like', '%sandbox%')
            ->when($category, fn ($query) => $query->where('canonical_category', $category));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function categorySummaries(): Collection
    {
        $counts = $this->candidatesQuery()
            ->selectRaw('canonical_category, count(*) as total')
            ->groupBy('canonical_category')
            ->pluck('total', 'canonical_category');

        return collect((array) config('catalog_taxonomy.categories', []))
            ->map(fn (array $meta, string $slug) => [
                'slug' => $slug,
                'label_ru' => $meta['label_ru'] ?? $slug,
                'label_en' => $meta['label_en'] ?? $slug,
                'description_ru' => $meta['description_ru'] ?? null,
                'schema_org' => $meta['schema_org'] ?? 'Product',
                'google_product_category' => $meta['google_product_category'] ?? null,
                'candidate_count' => (int) ($counts[$slug] ?? 0),
                'url' => route('meanly.network.categories.show', $slug),
            ])
            ->filter(fn (array $category) => (int) $category['candidate_count'] > 0)
            ->values();
    }

    public function publicSlug(ProviderProduct $product): string
    {
        return $product->id.'-'.Str::slug(Str::limit((string) $product->name, 90, ''));
    }

    public function findByPublicSlug(string $idSlug): ?ProviderProduct
    {
        $id = (int) Str::before($idSlug, '-');
        if ($id <= 0) {
            return null;
        }

        return $this->candidatesQuery()->whereKey($id)->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function facts(ProviderProduct $product): array
    {
        $canonicalCategory = $product->canonical_category
            ?: $this->categoryResolver->forProviderProduct($product);
        $price = (float) ($product->retail_price ?: $product->purchase_price ?: $product->min_price ?: 0);
        $currency = strtoupper((string) ($product->currency ?: 'USD'));
        $brand = $product->brand?->name ?: $this->guessBrand($product);
        $url = route('meanly.network.products.show', $this->publicSlug($product));
        $sellerOffers = $this->offerRanking->rankedOffersForProviderProduct($product);
        $bestOffer = $this->offerRanking->bestOffer($sellerOffers);
        $canonicalIdentity = $this->identity->forProviderProduct($product);
        $seoQuality = $this->quality($product);
        $indexingPolicy = $this->indexingPolicy->forProviderNetworkCandidate(
            $canonicalIdentity,
            $seoQuality,
            $bestOffer,
            ['status' => ['seo_quality' => $seoQuality]],
            $product,
        );
        $canonicalProductUrl = ! empty($canonicalIdentity['identity_slug'])
            ? route('meanly.canonical-products.show', $canonicalIdentity['identity_slug'])
            : null;

        return [
            'type' => 'ProviderNetworkCatalogCandidate',
            'id' => $product->id,
            'url' => $url,
            'machine_readable_at' => route('llms.network.products.show', $this->publicSlug($product)),
            'canonical_product_url' => $canonicalProductUrl,
            'canonical_product_machine_readable_at' => ! empty($canonicalIdentity['identity_slug'])
                ? route('llms.catalog.canonical-products.show', $canonicalIdentity['identity_slug'])
                : null,
            'name' => $product->name,
            'description' => $this->description($product, $brand, $canonicalCategory),
            'canonical_category' => $canonicalCategory,
            'canonical_category_label' => $this->categoryResolver->label($canonicalCategory),
            'brand' => $brand,
            'region' => $product->region?->name_en ?? $product->region?->name_ru,
            'face_value' => $this->faceValue($product),
            'face_value_currency' => $currency,
            'canonical_identity' => $canonicalIdentity,
            'estimated_provider_price' => [
                'amount' => $price,
                'currency' => $currency,
            ],
            'status' => [
                'sellable_now' => $sellerOffers->isNotEmpty(),
                'available_through_provider_network' => true,
                'seo_quality' => $seoQuality,
            ],
            'indexing_policy' => $indexingPolicy,
            'indexing' => $indexingPolicy,
            'seller_offers' => [
                'count' => $sellerOffers->count(),
                'best_offer' => $bestOffer,
                'offers' => $sellerOffers->values(),
                'ranking_method' => 'price_stock_sales_seller_reliability_v1',
            ],
            'fulfillment' => [
                'delivery' => 'instant_digital_when_enabled',
                'stock_source' => 'provider_network',
                'activation' => $product->activation_url ? 'redeem_on_external_service' : 'redeem_code',
            ],
            'media' => [
                'image' => $this->imageUrl($product),
            ],
        ];
    }

    /**
     * Lightweight policy path for sitemap generation. It avoids offer lookups,
     * so medium identities with seller offers may still be omitted conservatively.
     *
     * @return array<string, mixed>
     */
    public function indexingPolicyForProduct(ProviderProduct $product): array
    {
        $seoQuality = $this->quality($product);

        return $this->indexingPolicy->forProviderNetworkCandidate(
            $this->identity->forProviderProduct($product),
            $seoQuality,
            null,
            ['status' => ['seo_quality' => $seoQuality]],
            $product,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonLd(ProviderProduct $product): array
    {
        $facts = $this->facts($product);

        return [
            '@context' => 'https://schema.org',
            '@type' => config("catalog_taxonomy.categories.{$facts['canonical_category']}.schema_org", 'Product'),
            '@id' => $facts['url'].'#seller-supply-preview',
            'name' => $facts['name'],
            'description' => $facts['description'],
            'image' => array_filter([$facts['media']['image']]),
            'brand' => [
                '@type' => 'Brand',
                'name' => $facts['brand'] ?: 'Digital',
            ],
            'category' => $facts['canonical_category_label'],
            'offers' => $this->offersJsonLd($facts),
            'additionalProperty' => collect([
                'region' => $facts['region'],
                'face_value' => $facts['face_value'],
                'face_value_currency' => $facts['face_value_currency'],
            ])
                ->filter(fn ($value) => $value !== null && $value !== '')
                ->map(fn ($value, string $name) => [
                    '@type' => 'PropertyValue',
                    'name' => $name,
                    'value' => $value,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  Collection<int, ProviderProduct>|LengthAwarePaginator<int, ProviderProduct>  $products
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
                ->map(fn (ProviderProduct $product, int $index) => [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'url' => route('meanly.network.products.show', $this->publicSlug($product)),
                    'name' => $product->name,
                ])
                ->all(),
        ];
    }

    /**
     * @param  Collection<int, ProviderProduct>  $products
     * @return Collection<int, array<string, mixed>>
     */
    public function groupCandidatesByIdentity(Collection $products): Collection
    {
        $products = $products->values();
        $productsById = $products->keyBy('id');

        return $this->identity->groupProviderProducts($products)
            ->map(function (array $group) use ($productsById) {
                $candidates = collect($group['candidate_ids'] ?? [])
                    ->map(fn ($id) => $productsById->get($id))
                    ->filter(fn ($product) => $product instanceof ProviderProduct)
                    ->map(fn (ProviderProduct $product) => [
                        'id' => $product->id,
                        'name' => $product->name,
                        'provider_id' => $product->provider_id,
                        'url' => route('meanly.network.products.show', $this->publicSlug($product)),
                        'machine_readable_at' => route('llms.network.products.show', $this->publicSlug($product)),
                        'canonical_category' => $product->canonical_category ?: data_get($group, 'canonical_identity.canonical_category'),
                        'estimated_provider_price' => [
                            'amount' => (float) ($product->retail_price ?: $product->purchase_price ?: $product->min_price ?: 0),
                            'currency' => strtoupper((string) ($product->currency ?: data_get($group, 'canonical_identity.face_value_currency') ?: 'USD')),
                        ],
                    ])
                    ->values();

                $group['candidates'] = $candidates;

                return $group;
            })
            ->values();
    }

    /**
     * @param  array<string, mixed>  $facts
     * @return array<string, mixed>
     */
    private function offersJsonLd(array $facts): array
    {
        $offers = collect($facts['seller_offers']['offers'] ?? []);
        if ($offers->isEmpty()) {
            return [
                '@type' => 'Offer',
                'url' => $facts['url'],
                'priceCurrency' => $facts['estimated_provider_price']['currency'],
                'price' => $facts['estimated_provider_price']['amount'],
                'availability' => 'https://schema.org/LimitedAvailability',
                'description' => 'This product can be connected by a Meanly seller before checkout is available.',
            ];
        }

        $prices = $offers->pluck('price.amount')->filter(fn ($value) => is_numeric($value))->map(fn ($value) => (float) $value);
        $priceCurrency = (string) data_get($offers->first(), 'price.currency', pricing()->displayCurrency);

        return [
            '@type' => 'AggregateOffer',
            'offerCount' => $offers->count(),
            'lowPrice' => $prices->min(),
            'highPrice' => $prices->max(),
            'priceCurrency' => $priceCurrency,
            'offers' => $offers
                ->filter(fn (array $offer) => (bool) data_get($offer, 'indexing.indexable'))
                ->take(10)
                ->map(fn (array $offer) => [
                    '@type' => 'Offer',
                    'url' => $offer['url'],
                    'priceCurrency' => $offer['price']['currency'],
                    'price' => $offer['price']['amount'],
                    'availability' => match ($offer['availability']) {
                        'in_stock' => 'https://schema.org/InStock',
                        'auto_purchase' => 'https://schema.org/PreOrder',
                        default => 'https://schema.org/LimitedAvailability',
                    },
                    'seller' => [
                        '@type' => 'Organization',
                        'name' => $offer['seller']['name'] ?: 'Meanly seller',
                    ],
                ])
                ->values()
                ->all(),
        ];
    }

    public function quality(ProviderProduct $product): string
    {
        if ($product->brand_id && $product->image && $product->currency && ((float) ($product->retail_price ?: $product->purchase_price) > 0)) {
            return 'ready';
        }

        if ($product->brand_id && $product->currency && ((float) ($product->retail_price ?: $product->purchase_price ?: $product->min_price) > 0)) {
            return 'thin';
        }

        return 'noindex_candidate';
    }

    private function guessBrand(ProviderProduct $product): ?string
    {
        $name = trim((string) $product->name);
        foreach (['PlayStation', 'Xbox', 'Nintendo', 'Steam', 'Apple', 'Google Play', 'Bitdefender', 'American Express', 'Roblox', 'PUBG'] as $brand) {
            if (str_contains(mb_strtolower($name), mb_strtolower($brand))) {
                return $brand;
            }
        }

        return Str::of($name)->replace(['✅', '✨'], '')->trim()->explode(' ')->filter()->first();
    }

    private function faceValue(ProviderProduct $product): ?float
    {
        foreach ([$product->retail_price, data_get($product->data, 'product.price'), data_get($product->data, 'data.product.price'), data_get($product->data, 'price')] as $value) {
            if (is_numeric($value) && (float) $value > 0) {
                return (float) $value;
            }
        }

        return null;
    }

    private function imageUrl(ProviderProduct $product): ?string
    {
        $image = trim((string) $product->image);
        if ($image === '') {
            return null;
        }

        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
            return $image;
        }

        return asset(ltrim($image, '/'));
    }

    private function description(ProviderProduct $product, ?string $brand, string $canonicalCategory): string
    {
        $label = $this->categoryResolver->label($canonicalCategory);
        $region = $product->region?->name_ru ?: $product->region?->name_en;
        $parts = array_filter([
            $brand,
            $label,
            $region ? "region: {$region}" : null,
            $product->currency ? "currency: {$product->currency}" : null,
        ]);

        return trim((string) $product->name).' is a digital product that Meanly sellers can connect to their storefront. '.implode(', ', $parts).'.';
    }
}
