<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProviderProduct;
use App\Models\Settings;
use App\Models\WildflowCatalog;

class CanonicalCategoryResolver
{
    /**
     * @var array<int, string>|null
     */
    private ?array $intentResolutionPriority = null;

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
     * @return array{canonical_category: string, discovery_intent: string, resolution: string}
     */
    public function resolve(array $payload, array $context = [], ?string $legacyCategory = null): array
    {
        $canonicalCategory = $legacyCategory && $this->isKnown($legacyCategory)
            ? $legacyCategory
            : $this->fromPayload($payload, $context);

        [$discoveryIntent, $resolution] = $this->discoveryIntentWithResolution($canonicalCategory, $context);

        return [
            'canonical_category' => $canonicalCategory,
            'discovery_intent' => $discoveryIntent,
            'resolution' => $resolution,
        ];
    }

    /**
     * Phase 2: discovery corridor from legacy category + brand/text signals.
     *
     * @param  array<int, mixed>  $context
     */
    public function discoveryIntent(string $legacyCategory, array $context = []): string
    {
        return $this->discoveryIntentWithResolution($legacyCategory, $context)[0];
    }

    /**
     * @param  array<int, mixed>  $context
     * @return array{0: string, 1: string}
     */
    public function discoveryIntentWithResolution(string $legacyCategory, array $context = []): array
    {
        $text = $this->normalizeText($this->flattenText($context));
        $brandText = $this->normalizeText((string) ($this->firstScalar($context) ?? ''));

        foreach ($this->intentResolutionPriority() as $corridor) {
            $config = $this->intentCorridorConfig($corridor);
            if ($config === null) {
                continue;
            }

            if ($this->matchesBrandOverrides($config, $text, $brandText)) {
                return [$corridor, 'brand_override'];
            }
        }

        foreach ($this->intentResolutionPriority() as $corridor) {
            $config = $this->intentCorridorConfig($corridor);
            if ($config === null) {
                continue;
            }

            $legacyCategories = (array) ($config['legacy_categories'] ?? []);
            if (! in_array($legacyCategory, $legacyCategories, true)) {
                continue;
            }

            if ($corridor === 'shop' && $this->matchesExcludedBrands($config, $text, $brandText)) {
                continue;
            }

            return [$corridor, 'legacy_category'];
        }

        return [
            (string) config('catalog_taxonomy.discovery_default', 'unclassified'),
            'default',
        ];
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

    public function discoveryIntentKey(string $corridor): string
    {
        if (! $this->isKnownIntentCorridor($corridor)) {
            return 'discover:unclassified';
        }

        return (string) config(
            "catalog_taxonomy.intent_corridors.{$corridor}.intent_key",
            "discover:{$corridor}"
        );
    }

    public function discoveryLabel(string $corridor, ?string $locale = null): string
    {
        if (! $this->isKnownIntentCorridor($corridor)) {
            return $corridor;
        }

        $locale = $locale ?: app()->getLocale();

        return (string) config(
            "catalog_taxonomy.intent_corridors.{$corridor}.label_{$locale}",
            config(
                "catalog_taxonomy.intent_corridors.{$corridor}.label_en",
                config("catalog_taxonomy.intent_corridors.{$corridor}.label_ru", $corridor)
            )
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function crossLinksForCorridor(string $corridor): array
    {
        return (array) config("catalog_taxonomy.cross_links.{$corridor}", []);
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

    public function label(string $canonicalCategory, ?string $locale = null): string
    {
        $category = $this->isKnown($canonicalCategory)
            ? $canonicalCategory
            : (string) config('catalog_taxonomy.default', 'gift_cards');
        $locale = $locale ?: app()->getLocale();

        return (string) config(
            "catalog_taxonomy.categories.{$category}.label_{$locale}",
            config("catalog_taxonomy.categories.{$category}.label_en", config("catalog_taxonomy.categories.{$category}.label_ru", $category))
        );
    }

    public function isKnownIntentCorridor(?string $corridor): bool
    {
        return is_string($corridor)
            && $corridor !== ''
            && array_key_exists($corridor, (array) config('catalog_taxonomy.intent_corridors', []));
    }

    private function isKnown(?string $category): bool
    {
        return is_string($category)
            && $category !== ''
            && array_key_exists($category, (array) config('catalog_taxonomy.categories', []));
    }

    /**
     * @return array<int, string>
     */
    private function intentResolutionPriority(): array
    {
        if ($this->intentResolutionPriority !== null) {
            return $this->intentResolutionPriority;
        }

        $priority = (array) config('catalog_taxonomy.intent_resolution_priority', []);
        if ($priority === []) {
            $priority = array_keys((array) config('catalog_taxonomy.intent_corridors', []));
        }

        return $this->intentResolutionPriority = array_values(array_filter(
            $priority,
            fn (string $corridor) => $this->isKnownIntentCorridor($corridor)
        ));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function intentCorridorConfig(string $corridor): ?array
    {
        $config = config("catalog_taxonomy.intent_corridors.{$corridor}");

        return is_array($config) ? $config : null;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function matchesBrandOverrides(array $config, string $text, string $brandText): bool
    {
        foreach ((array) ($config['brand_overrides'] ?? []) as $brand => $aliases) {
            if (is_int($brand)) {
                $brand = (string) $aliases;
                $aliases = [$brand];
            }

            foreach ((array) $aliases as $alias) {
                $needle = $this->normalizeText((string) $alias);
                if ($needle === '') {
                    continue;
                }

                if (
                    ($brandText !== '' && str_contains($brandText, $needle))
                    || str_contains($text, $needle)
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function matchesExcludedBrands(array $config, string $text, string $brandText): bool
    {
        foreach ((array) ($config['exclude_brands'] ?? []) as $brand) {
            $needle = $this->normalizeText((string) $brand);
            if ($needle === '') {
                continue;
            }

            if (
                ($brandText !== '' && str_contains($brandText, $needle))
                || str_contains($text, $needle)
            ) {
                return true;
            }
        }

        return false;
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

    /**
     * @param  array<int, mixed>  $values
     */
    private function firstScalar(array $values): mixed
    {
        foreach ($values as $value) {
            if (is_scalar($value) && (string) $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function normalizeText(string $text): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $text) ?? $text));
    }
}
