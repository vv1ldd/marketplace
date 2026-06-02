<?php

namespace App\Services\Provider;

use App\Models\Provider;
use App\Models\ProviderProduct;
use App\Models\WildflowCatalog;

class ProviderCatalogAggregator
{
    /**
     * @return array{success: true, provider: array<string, mixed>, count: int, items: array<int, array<string, mixed>>}
     */
    public function unifiedCatalog(Provider|string $provider, bool $includeInactive = false): array
    {
        $provider = $provider instanceof Provider
            ? $provider
            : Provider::query()->where('type', $provider)->firstOrFail();

        $items = $this->unifiedItemsForProvider($provider, $includeInactive);

        return [
            'success' => true,
            'provider' => [
                'id' => $provider->id,
                'type' => $provider->type,
                'name' => $provider->name,
            ],
            'count' => count($items),
            'items' => $items,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function unifiedItemsForProvider(Provider $provider, bool $includeInactive = false): array
    {
        $seenServiceSkuIndexes = [];

        $providerProducts = ProviderProduct::query()
            ->with(['brand', 'region', 'provider'])
            ->where('provider_id', $provider->id)
            ->when(! $includeInactive, fn ($query) => $query->where('is_active', true))
            ->orderBy('id')
            ->get()
            ->map(function (ProviderProduct $item) use (&$seenServiceSkuIndexes): array {
                if ($item->sku_bidx) {
                    $seenServiceSkuIndexes[$item->sku_bidx] = true;
                }

                return $this->fromProviderProduct($item);
            });

        $legacyCatalogItems = WildflowCatalog::query()
            ->with(['brand', 'region', 'provider'])
            ->where('provider_id', $provider->id)
            ->when(! $includeInactive, fn ($query) => $query->where('is_active', true))
            ->orderBy('id')
            ->get()
            ->reject(fn (WildflowCatalog $item): bool => $item->service_sku_bidx && isset($seenServiceSkuIndexes[$item->service_sku_bidx]))
            ->map(fn (WildflowCatalog $item): array => $this->fromWildflowCatalog($item));

        return $providerProducts
            ->merge($legacyCatalogItems)
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function fromProviderProduct(ProviderProduct $item): array
    {
        $rawData = is_array($item->data) ? $item->data : [];
        $serviceSku = (string) $item->sku;
        $marketSku = (string) ($item->market_sku ?: $serviceSku);
        $minPrice = (float) ($item->min_price ?: $item->retail_price ?: $item->purchase_price ?: 0);
        $maxPrice = (float) ($item->max_price ?: $item->retail_price ?: $minPrice);
        $purchasePrice = (float) ($item->purchase_price ?: $minPrice ?: $maxPrice);
        $retailPrice = (float) ($item->retail_price ?: $maxPrice ?: $minPrice);
        $currency = strtoupper((string) ($item->currency ?: data_get($rawData, 'currency') ?: 'USD'));
        $brand = $item->brand?->name ?: $item->category ?: data_get($rawData, 'brand') ?: 'Provider Catalog';

        return [
            'service_sku' => $serviceSku,
            'market_sku' => $marketSku,
            'sku' => $serviceSku,
            'name' => (string) ($item->name ?: $marketSku),
            'brand' => $brand,
            'category' => $item->category ?: data_get($rawData, 'category') ?: $brand,
            'canonical_category' => $item->canonical_category,
            'region' => $item->region?->code ?? data_get($rawData, 'region') ?? data_get($rawData, 'regions.0.code'),
            'currency' => $currency,
            'buying_price' => $purchasePrice,
            'purchase_price' => $purchasePrice,
            'retail_price' => $retailPrice,
            'min_price' => $minPrice,
            'max_price' => $maxPrice,
            'percentage_of_buying_price' => null,
            'image' => $item->image ?: data_get($rawData, 'image'),
            'reward_type' => $item->reward_type ?: data_get($rawData, 'reward_type'),
            'activation_url' => $item->activation_url ?: data_get($rawData, 'activation_url'),
            'redemption_instructions' => $item->redemption_instructions ?: data_get($rawData, 'redemption_instructions'),
            'inventory_type' => data_get($rawData, 'provider_purchase.purchase_mode') ?: 'provider_product',
            'provider_purchase' => [
                'provider_type' => $item->provider?->type,
                'provider_product_id' => $item->id,
                'service_sku' => $serviceSku,
                'market_sku' => $marketSku,
                'pre_order' => (bool) data_get($rawData, 'provider_purchase.pre_order', data_get($rawData, 'pre_order', false)),
            ],
            'status' => $item->is_active ? 'active' : 'inactive',
            'is_available' => (bool) $item->is_active,
            'raw_data' => $rawData,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fromWildflowCatalog(WildflowCatalog $item): array
    {
        $rawData = is_array($item->data) ? $item->data : [];
        $serviceSku = (string) $item->service_sku;
        $marketSku = (string) $item->sku;
        $minPrice = (float) ($item->min_price ?: $item->retail_price ?: $item->purchase_price ?: 0);
        $maxPrice = (float) ($item->max_price ?: $item->retail_price ?: $minPrice);
        $purchasePrice = (float) ($item->purchase_price ?: data_get($rawData, 'buying_price') ?: $minPrice ?: $maxPrice);
        $retailPrice = (float) ($item->retail_price ?: $maxPrice ?: $minPrice);
        $currency = strtoupper((string) (data_get($rawData, 'currency.code') ?: data_get($rawData, 'currency') ?: $item->currency_code ?: 'USD'));
        $brand = $item->brand?->name ?: $item->brand_name ?: data_get($rawData, 'categories.0.name') ?: 'Provider Catalog';

        return [
            'service_sku' => $serviceSku,
            'market_sku' => $marketSku,
            'sku' => $serviceSku,
            'name' => $item->title,
            'brand' => $brand,
            'category' => $item->category ?: $brand,
            'canonical_category' => $item->canonical_category,
            'region' => $item->region?->code ?? data_get($rawData, 'regions.0.code'),
            'currency' => $currency,
            'buying_price' => $purchasePrice,
            'purchase_price' => $purchasePrice,
            'retail_price' => $retailPrice,
            'min_price' => $minPrice,
            'max_price' => $maxPrice,
            'percentage_of_buying_price' => data_get($rawData, 'percentage_of_buying_price'),
            'image' => data_get($rawData, 'image'),
            'reward_type' => $item->reward_type ?: data_get($rawData, 'reward_type'),
            'activation_url' => $item->activation_url ?: data_get($rawData, 'activation_url'),
            'redemption_instructions' => $item->redemption_instructions,
            'inventory_type' => $item->type ?: 'wildflow_catalog',
            'provider_purchase' => [
                'provider_type' => $item->provider?->type,
                'wildflow_catalog_id' => $item->id,
                'service_sku' => $serviceSku,
                'market_sku' => $marketSku,
                'pre_order' => (bool) data_get($rawData, 'pre_order', false),
            ],
            'status' => $item->is_active ? 'active' : 'inactive',
            'is_available' => (bool) $item->is_active,
            'raw_data' => $rawData,
        ];
    }
}
