<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProviderProduct;
use App\Models\Settings;
use App\Models\WildflowCatalog;

class CanonicalCategoryResolver
{
    public function forProduct(Product $product): string
    {
        if ($this->isKnown($product->canonical_category ?? null)) {
            return $product->canonical_category;
        }

        return $this->fromPayload($product->data ?? [], [
            $product->name,
            $product->category,
            $product->vendor,
            $product->brand?->name,
            $product->provider?->name,
        ]);
    }

    public function forProviderProduct(ProviderProduct $providerProduct, ?WildflowCatalog $catalogItem = null): string
    {
        if ($this->isKnown($providerProduct->canonical_category ?? null)) {
            return $providerProduct->canonical_category;
        }

        if ($catalogItem && $this->isKnown($catalogItem->canonical_category ?? null)) {
            return $catalogItem->canonical_category;
        }

        return $this->fromPayload($providerProduct->data ?? [], [
            $providerProduct->name,
            $providerProduct->category,
            $providerProduct->reward_type,
            $providerProduct->brand?->name,
            $providerProduct->provider?->name,
            $catalogItem?->title,
            $catalogItem?->category,
            $catalogItem?->reward_type,
            $catalogItem?->brand?->name,
        ]);
    }

    public function forWildflowCatalog(WildflowCatalog $catalogItem): string
    {
        if ($this->isKnown($catalogItem->canonical_category ?? null)) {
            return $catalogItem->canonical_category;
        }

        return $this->fromPayload($catalogItem->data ?? [], [
            $catalogItem->title,
            $catalogItem->category,
            $catalogItem->reward_type,
            $catalogItem->brand?->name,
            $catalogItem->provider?->name,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, mixed>  $context
     */
    public function fromPayload(array $payload, array $context = []): string
    {
        $text = $this->normalizeText($this->flattenText([
            ...$context,
            $payload,
            data_get($payload, 'data'),
            data_get($payload, 'product'),
            data_get($payload, 'data.product'),
            data_get($payload, 'categories'),
            data_get($payload, 'product.categories'),
            data_get($payload, 'data.product.categories'),
        ]));

        foreach ((array) config('catalog_taxonomy.keyword_rules', []) as $category => $needles) {
            foreach ((array) $needles as $needle) {
                if ($needle !== '' && str_contains($text, $this->normalizeText((string) $needle))) {
                    return $category;
                }
            }
        }

        return (string) config('catalog_taxonomy.default', 'gift_cards');
    }

    public function yandexCategoryId(string $canonicalCategory, ?int $fallback = null): int
    {
        $category = $this->isKnown($canonicalCategory)
            ? $canonicalCategory
            : (string) config('catalog_taxonomy.default', 'gift_cards');

        $mapping = (array) config("catalog_taxonomy.channels.yandex_market.categories.{$category}", []);
        $setting = $mapping['setting'] ?? null;
        $default = (int) ($mapping['default'] ?? $fallback ?? 989939);

        if (is_string($setting) && $setting !== '') {
            return (int) Settings::get($setting, $default);
        }

        return $default;
    }

    public function label(string $canonicalCategory, string $locale = 'ru'): string
    {
        $category = $this->isKnown($canonicalCategory)
            ? $canonicalCategory
            : (string) config('catalog_taxonomy.default', 'gift_cards');

        return (string) config(
            "catalog_taxonomy.categories.{$category}.label_{$locale}",
            config("catalog_taxonomy.categories.{$category}.label_ru", $category)
        );
    }

    private function isKnown(?string $category): bool
    {
        return is_string($category)
            && $category !== ''
            && array_key_exists($category, (array) config('catalog_taxonomy.categories', []));
    }

    /**
     * @param  array<int, mixed>  $values
     */
    private function flattenText(array $values): string
    {
        $parts = [];

        foreach ($values as $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if (is_scalar($value)) {
                $parts[] = (string) $value;
                continue;
            }

            if (is_array($value)) {
                $parts[] = $this->flattenText(array_values($value));
            }
        }

        return implode(' ', $parts);
    }

    private function normalizeText(string $text): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $text) ?? $text));
    }
}
