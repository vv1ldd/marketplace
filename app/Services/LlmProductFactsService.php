<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class LlmProductFactsService
{
    public function __construct(
        private readonly CanonicalCategoryResolver $categoryResolver,
        private readonly CanonicalProductIdentityService $identity,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function productFacts(Product $product): array
    {
        $wfCatalog = $product->wildflowCatalog()?->loadMissing(['brand', 'region']);
        $raw = $product->data ?? [];
        $wfRaw = $wfCatalog?->data ?? [];
        $canonicalCategory = $this->categoryResolver->forProduct($product);
        $brand = $product->brand?->name
            ?? $wfCatalog?->brand?->name
            ?? $product->vendor
            ?? $this->guessBrand($product);
        $faceValue = $this->faceValue($product, $wfCatalog);
        $currency = $this->currency($product, $wfRaw, $raw);
        $region = $this->region($wfCatalog, $wfRaw, $raw);
        $canonicalIdentity = $this->identity->forProduct($product);

        return [
            'type' => 'DigitalVoucher',
            'id' => $product->id,
            'sku' => $product->sku,
            'url' => route('meanly.storefront.products.show', $product->slug),
            'name' => $product->name,
            'description' => $this->plainDescription($product),
            'canonical_category' => $canonicalCategory,
            'canonical_category_label' => $this->categoryResolver->label($canonicalCategory),
            'brand' => $brand,
            'platform' => $this->platform($brand, $product, $wfRaw, $raw),
            'face_value' => $faceValue,
            'face_value_currency' => $currency,
            'region' => $region,
            'canonical_identity' => $canonicalIdentity,
            'price' => [
                'amount' => round(((float) ($product->price_rub ?? 0)) / 100, 2),
                'currency' => 'RUB',
            ],
            'delivery' => 'instant_digital',
            'activation' => $this->activation($product, $wfCatalog),
            'sellable' => (bool) $product->is_active,
            'stock_source' => $product->wildflow_catalog_sku ? 'provider_network' : 'seller_inventory',
            'seller' => [
                'name' => $product->shop?->name,
                'public_storefront' => true,
            ],
            'media' => [
                'image' => $this->publicImage($product),
            ],
            'seo' => [
                'title' => $product->meta_title ?: $product->name,
                'description' => $product->meta_description ?: Str::limit($this->plainDescription($product), 155),
            ],
            'machine_readable_at' => route('llms.products.show', $product->slug),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function productJsonLd(Product $product): array
    {
        $facts = $this->productFacts($product);

        return [
            '@context' => 'https://schema.org',
            '@type' => config("catalog_taxonomy.categories.{$facts['canonical_category']}.schema_org", 'Product'),
            '@id' => $facts['url'].'#product',
            'name' => $facts['name'],
            'description' => $facts['description'],
            'image' => array_filter([$facts['media']['image'] ?? null]),
            'sku' => $facts['sku'],
            'brand' => [
                '@type' => 'Brand',
                'name' => $facts['brand'] ?: 'Meanly',
            ],
            'category' => $facts['canonical_category_label'],
            'additionalProperty' => $this->additionalProperties($facts),
            'offers' => [
                '@type' => 'Offer',
                'url' => $facts['url'],
                'priceCurrency' => 'RUB',
                'price' => $facts['price']['amount'],
                'availability' => $facts['sellable'] ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                'seller' => [
                    '@type' => 'Organization',
                    'name' => $facts['seller']['name'] ?: 'Meanly',
                ],
            ],
        ];
    }

    /**
     * @param  Collection<int, Product>|LengthAwarePaginator<int, Product>  $products
     * @return array<string, mixed>
     */
    public function itemListJsonLd(Collection|LengthAwarePaginator $products, string $name = 'Meanly digital catalog'): array
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
                ->map(fn (Product $product, int $index) => [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'url' => route('meanly.storefront.products.show', $product->slug),
                    'name' => $product->name,
                ])
                ->all(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function categorySummaries(Builder $baseQuery): array
    {
        $categories = (array) config('catalog_taxonomy.categories', []);
        $counts = (clone $baseQuery)
            ->selectRaw('canonical_category, count(*) as total')
            ->groupBy('canonical_category')
            ->pluck('total', 'canonical_category');

        return collect($categories)
            ->map(fn (array $meta, string $slug) => [
                'slug' => $slug,
                'label_ru' => $meta['label_ru'] ?? $slug,
                'label_en' => $meta['label_en'] ?? $slug,
                'seo_indexable' => (bool) ($meta['seo_indexable'] ?? false),
                'schema_org' => $meta['schema_org'] ?? 'Product',
                'google_product_category' => $meta['google_product_category'] ?? null,
                'product_count' => (int) ($counts[$slug] ?? 0),
                'machine_readable_url' => route('llms.categories.show', $slug),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $facts
     * @return array<int, array<string, mixed>>
     */
    private function additionalProperties(array $facts): array
    {
        return collect([
            'canonical_category' => $facts['canonical_category'],
            'platform' => $facts['platform'],
            'face_value' => $facts['face_value'],
            'face_value_currency' => $facts['face_value_currency'],
            'region' => $facts['region'],
            'canonical_identity_fingerprint' => data_get($facts, 'canonical_identity.fingerprint'),
            'canonical_identity_slug' => data_get($facts, 'canonical_identity.identity_slug'),
            'canonical_identity_confidence' => data_get($facts, 'canonical_identity.confidence'),
            'canonical_product_family' => data_get($facts, 'canonical_identity.product_family'),
            'delivery' => $facts['delivery'],
            'activation' => $facts['activation'],
            'stock_source' => $facts['stock_source'],
        ])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->map(fn ($value, string $name) => [
                '@type' => 'PropertyValue',
                'name' => $name,
                'value' => $value,
            ])
            ->values()
            ->all();
    }

    private function plainDescription(Product $product): string
    {
        $description = trim(strip_tags((string) $product->description));
        if ($description !== '') {
            return Str::limit(preg_replace('/\s+/u', ' ', $description) ?? $description, 700);
        }

        return "{$product->name} is a digital voucher sold through Meanly with instant electronic delivery and protected checkout.";
    }

    private function publicImage(Product $product): ?string
    {
        $image = $product->getRedeemDisplayImageSrc();

        return str_starts_with($image, 'data:') ? null : $image;
    }

    private function guessBrand(Product $product): ?string
    {
        $name = trim((string) $product->name);
        if ($name === '') {
            return null;
        }

        foreach (['PlayStation', 'Xbox', 'Nintendo', 'Steam', 'Apple', 'Google Play', 'Bitdefender', 'American Express', 'Roblox', 'PUBG'] as $brand) {
            if (str_contains(mb_strtolower($name), mb_strtolower($brand))) {
                return $brand;
            }
        }

        return Str::of($name)->replace(['✅', '✨'], '')->trim()->explode(' ')->filter()->first();
    }

    private function platform(?string $brand, Product $product, array $wfRaw, array $raw): ?string
    {
        $text = mb_strtolower(implode(' ', array_filter([
            $brand,
            $product->name,
            data_get($wfRaw, 'data.product.title'),
            data_get($raw, 'data.product.title'),
        ])));

        return match (true) {
            str_contains($text, 'playstation') || str_contains($text, 'psn') => 'PlayStation Store',
            str_contains($text, 'xbox') => 'Xbox Store',
            str_contains($text, 'nintendo') => 'Nintendo eShop',
            str_contains($text, 'steam') => 'Steam',
            str_contains($text, 'apple') || str_contains($text, 'itunes') => 'App Store',
            str_contains($text, 'google') => 'Google Play',
            str_contains($text, 'roblox') => 'Roblox',
            str_contains($text, 'pubg') => 'PUBG',
            default => $brand,
        };
    }

    private function faceValue(Product $product, mixed $wfCatalog): ?float
    {
        $value = data_get($product->data, 'product.price')
            ?? data_get($product->data, 'data.product.price')
            ?? data_get($product->data, 'price')
            ?? data_get($product->params ?? [], 'wf_nominal')
            ?? data_get($wfCatalog?->data ?? [], 'data.product.price')
            ?? data_get($wfCatalog?->data ?? [], 'price');

        if (is_numeric($value)) {
            return (float) $value;
        }

        if (preg_match('/(?<![A-Z0-9])(\d+(?:[.,]\d+)?)\s*(USD|EUR|GBP|CAD|AED|TRY|SAR|RUB|KWD|QAR|OMR)\b/i', $product->name.' '.$product->sku, $match)) {
            return (float) str_replace(',', '.', $match[1]);
        }

        return null;
    }

    private function currency(Product $product, array $wfRaw, array $raw): ?string
    {
        $currency = $product->purchase_currency
            ?: data_get($raw, 'product.currency.code')
            ?: data_get($raw, 'data.product.currency.code')
            ?: data_get($wfRaw, 'data.product.currency.code')
            ?: data_get($wfRaw, 'currency_code');

        if ((! is_string($currency) || $currency === '') && preg_match('/\b(USD|EUR|GBP|CAD|AED|TRY|SAR|RUB|KWD|QAR|OMR)\b/i', $product->name.' '.$product->sku, $match)) {
            $currency = $match[1];
        }

        return is_string($currency) && $currency !== '' ? strtoupper($currency) : null;
    }

    private function region(mixed $wfCatalog, array $wfRaw, array $raw): ?string
    {
        $region = $wfCatalog?->region?->name_en
            ?? $wfCatalog?->region?->name_ru
            ?? data_get($raw, 'product.regions.0.name')
            ?? data_get($raw, 'data.product.regions.0.name')
            ?? data_get($wfRaw, 'data.product.regions.0.name')
            ?? data_get($wfRaw, 'region');

        return is_string($region) && $region !== '' ? $region : null;
    }

    private function activation(Product $product, mixed $wfCatalog): string
    {
        $activationUrl = $wfCatalog?->activation_url
            ?? data_get($product->data ?? [], 'product.activation_url')
            ?? data_get($product->data ?? [], 'data.product.activation_url');

        return $activationUrl ? 'redeem_on_external_service' : 'redeem_code';
    }
}
