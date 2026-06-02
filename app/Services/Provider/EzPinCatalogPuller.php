<?php

namespace App\Services\Provider;

use App\Models\Provider;
use App\Services\CanonicalCategoryResolver;
use App\Services\MappingService;
use App\Services\VaultTransitService;
use EzPin\EzPinClient;
use Illuminate\Support\Facades\DB;

class EzPinCatalogPuller
{
    public function __construct(
        private readonly VaultTransitService $vault,
        private readonly CanonicalCategoryResolver $categoryResolver
    ) {
    }

    /**
     * Pull fresh EZPin catalog streams and persist them as Meanly provider products.
     *
     * @return array{catalog: int, retailer: int, total: int, deactivated: int}
     */
    public function pullIntoProvider(Provider $catalogProvider, bool $deactivateMissing = true): array
    {
        $client = $this->clientFor($catalogProvider);

        $catalog = $client->getCatalog()['results'] ?? [];
        $retailer = $client->getRetailerProducts()['results'] ?? [];

        return $this->syncPayloadIntoProvider($catalogProvider, $catalog, $retailer, $deactivateMissing);
    }

    /**
     * @param  array<int, array<string, mixed>>  $catalogItems
     * @param  array<int, array<string, mixed>>  $retailerItems
     * @return array{catalog: int, retailer: int, total: int, deactivated: int}
     */
    public function syncPayloadIntoProvider(
        Provider $provider,
        array $catalogItems,
        array $retailerItems = [],
        bool $deactivateMissing = true
    ): array {
        $rows = [];
        $seenSkuIndexes = [];

        foreach ($catalogItems as $item) {
            $row = $this->providerProductRow($provider, $this->normalizeCatalogItem($item));
            if ($row === null) {
                continue;
            }

            $seenSkuIndexes[] = $row['sku_bidx'];
            $rows[] = $row;
        }

        foreach ($retailerItems as $item) {
            $row = $this->providerProductRow($provider, $this->normalizeRetailerItem($item));
            if ($row === null) {
                continue;
            }

            $seenSkuIndexes[] = $row['sku_bidx'];
            $rows[] = $row;
        }

        if ($rows === []) {
            return [
                'catalog' => 0,
                'retailer' => 0,
                'total' => 0,
                'deactivated' => 0,
            ];
        }

        foreach (array_chunk($rows, 300) as $chunk) {
            DB::table('provider_products')->upsert(
                $chunk,
                ['provider_id', 'sku_bidx'],
                [
                    'sku', 'market_sku', 'market_sku_bidx', 'name', 'category', 'canonical_category',
                    'reward_type', 'purchase_price', 'retail_price', 'min_price', 'max_price',
                    'currency', 'brand_id', 'region_id', 'image', 'activation_url',
                    'redemption_instructions', 'is_active', 'data', 'updated_at',
                ],
            );
        }

        $deactivated = 0;
        if ($deactivateMissing) {
            $deactivated = DB::table('provider_products')
                ->where('provider_id', $provider->id)
                ->whereNotIn('sku_bidx', array_values(array_unique($seenSkuIndexes)))
                ->update([
                    'is_active' => false,
                    'updated_at' => now(),
                ]);
        }

        $provider->forceFill(['last_sync_at' => now()])->save();

        return [
            'catalog' => count($catalogItems),
            'retailer' => count($retailerItems),
            'total' => count($rows),
            'deactivated' => $deactivated,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function normalizeCatalogItem(array $item): array
    {
        $price = $item['price'] ?? null;
        $minPrice = is_array($price)
            ? $this->number($price['min'] ?? $price['selling_price'] ?? $item['min_price'] ?? 0)
            : $this->number($price ?? $item['min_price'] ?? 0);
        $maxPrice = is_array($price)
            ? $this->number($price['max'] ?? $price['selling_price'] ?? $item['max_price'] ?? $minPrice)
            : $this->number($price ?? $item['max_price'] ?? $minPrice);

        $currency = $this->currency($item['currency'] ?? 'USD');
        $category = $this->category($item);
        $title = trim((string) ($item['name'] ?? $item['title'] ?? 'EZPin Catalog Item'));
        $serviceSku = trim((string) ($item['sku'] ?? ''));
        $brand = trim((string) ($item['brand'] ?? $category ?? $title));
        $purchasePrice = abs($minPrice - $maxPrice) < 0.0001 ? $minPrice : $minPrice;

        return [
            'service_sku' => $serviceSku,
            'market_sku' => $this->marketSku($serviceSku),
            'name' => $title,
            'category' => $category,
            'brand' => $brand,
            'region' => data_get($item, 'regions.0.code'),
            'reward_type' => $item['reward_type_text'] ?? $item['reward_type'] ?? 'Gift-Card',
            'purchase_price' => $purchasePrice,
            'retail_price' => $maxPrice > 0 ? $maxPrice : $purchasePrice,
            'min_price' => $minPrice,
            'max_price' => $maxPrice > 0 ? $maxPrice : $minPrice,
            'currency' => $currency,
            'image' => $item['image'] ?? null,
            'activation_url' => $item['activation_url'] ?? $item['redemption_url'] ?? null,
            'redemption_instructions' => $item['description'] ?? null,
            'is_active' => true,
            'data' => $item + [
                'provider_purchase' => [
                    'provider_type' => 'ezpin',
                    'purchase_mode' => 'catalog',
                    'provider_identifier_field' => 'sku',
                    'provider_identifier' => $serviceSku,
                    'sku' => $serviceSku,
                    'provider_purchase_price' => $purchasePrice,
                    'min_price' => $minPrice,
                    'max_price' => $maxPrice,
                    'currency' => $currency,
                    'pre_order' => (bool) ($item['pre_order'] ?? false),
                    'source' => 'meanly_ezpin_catalog_puller',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function normalizeRetailerItem(array $item): array
    {
        $product = is_array($item['product'] ?? null) ? $item['product'] : [];
        $serviceSku = trim((string) ($item['product_code'] ?? ''));
        $faceValue = $this->number($item['price'] ?? 0);
        $buyingPrice = $this->number($item['buying_price'] ?? $item['cost'] ?? $faceValue);
        $currency = $this->currency($item['currency'] ?? $product['currency'] ?? 'USD');
        $category = $this->category($product) ?: $this->category($item);
        $title = trim((string) ($product['title'] ?? $product['name'] ?? $item['product_name'] ?? $item['name'] ?? 'EZPin Retailer Item'));
        $brand = trim((string) ($product['brand'] ?? $category ?? $title));
        $catalogSku = isset($product['sku']) ? (string) $product['sku'] : null;

        return [
            'service_sku' => $serviceSku,
            'market_sku' => $this->marketSku($serviceSku),
            'name' => $title,
            'category' => $category ?: 'Retailer Catalog',
            'brand' => $brand,
            'region' => data_get($product, 'regions.0.code') ?? data_get($item, 'regions.0.code'),
            'reward_type' => $product['reward_type_text'] ?? $product['reward_type'] ?? 'Gift-Card',
            'purchase_price' => $buyingPrice,
            'retail_price' => $faceValue > 0 ? $faceValue : $buyingPrice,
            'min_price' => $buyingPrice,
            'max_price' => $buyingPrice,
            'currency' => $currency,
            'image' => $product['image'] ?? $product['logo'] ?? $item['image'] ?? $item['logo'] ?? null,
            'activation_url' => $product['activation_url'] ?? $product['redemption_url'] ?? null,
            'redemption_instructions' => $product['description'] ?? null,
            'is_active' => true,
            'data' => $item + [
                'provider_purchase' => [
                    'provider_type' => 'ezpin',
                    'purchase_mode' => 'retailer',
                    'provider_identifier_field' => 'product_code',
                    'provider_identifier' => $serviceSku,
                    'product_code' => $serviceSku,
                    'retailer_product_code' => $serviceSku,
                    'catalog_sku' => $catalogSku,
                    'face_value' => $faceValue,
                    'buying_price' => $buyingPrice,
                    'currency' => $currency,
                    'pre_order' => (bool) ($product['pre_order'] ?? $item['pre_order'] ?? false),
                    'source' => 'meanly_ezpin_retailer_puller',
                ],
            ],
        ];
    }

    public function checkAvailability(Provider $provider, string $sku, int $itemCount = 1, ?float $price = null): array
    {
        return $this->clientFor($provider)->checkAvailability($sku, $itemCount, $price);
    }

    public function getCards(Provider $provider, string $reference): array
    {
        return $this->clientFor($provider)->getCards($reference);
    }

    public function clientFor(Provider $catalogProvider): EzPinClient
    {
        if (! class_exists(EzPinClient::class)) {
            throw new \RuntimeException('EzPin SDK is not available.');
        }

        $credentials = $this->credentialsFor($catalogProvider);
        $clientId = (string) ($credentials['client_id'] ?? '');
        $secretKey = (string) ($credentials['secret_key'] ?? $credentials['client_secret'] ?? '');

        if ($clientId === '' || $secretKey === '') {
            throw new \RuntimeException('EzPin credentials are not configured.');
        }

        return new EzPinClient($clientId, $secretKey);
    }

    /**
     * @return array<string, mixed>
     */
    public function credentialsFor(Provider $catalogProvider): array
    {
        $upstreamTypes = in_array($catalogProvider->type, ['wildflow-sandbox', 'ezpin-sandbox'], true)
            ? ['ezpin-sandbox']
            : ['ezpin'];

        $upstream = Provider::query()
            ->whereIn('type', $upstreamTypes)
            ->where('is_active', true)
            ->first();

        $credentials = is_array($upstream?->credentials) ? $upstream->credentials : [];
        if ($credentials === []) {
            $credentials = is_array($catalogProvider->credentials) ? $catalogProvider->credentials : [];
        }

        return [
            'client_id' => $credentials['client_id'] ?? config('services.ezpin.client_id'),
            'secret_key' => $credentials['secret_key'] ?? $credentials['client_secret'] ?? config('services.ezpin.secret_key'),
            'client_secret' => $credentials['client_secret'] ?? $credentials['secret_key'] ?? config('services.ezpin.secret_key'),
            'terminal_id' => $credentials['terminal_id'] ?? config('services.ezpin.terminal_id'),
            'terminal_pin' => $credentials['terminal_pin'] ?? config('services.ezpin.terminal_pin'),
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null
     */
    private function providerProductRow(Provider $provider, array $item): ?array
    {
        $serviceSku = trim((string) ($item['service_sku'] ?? ''));
        $title = trim((string) ($item['name'] ?? ''));
        $purchasePrice = $this->number($item['purchase_price'] ?? 0);
        $retailPrice = $this->number($item['retail_price'] ?? $purchasePrice);

        if ($serviceSku === '' || $title === '' || $purchasePrice <= 0 || $retailPrice <= 0) {
            return null;
        }

        $payload = is_array($item['data'] ?? null) ? $item['data'] : [];
        $brand = trim((string) ($item['brand'] ?? $item['category'] ?? $title));
        $category = trim((string) ($item['category'] ?? $brand));
        $brandId = $this->resolveBrandId($provider, $brand, $serviceSku, $title, $category);
        $regionCode = trim((string) ($item['region'] ?? ''));
        $regionId = $regionCode !== '' ? MappingService::resolveRegion($regionCode, $regionCode) : null;
        $canonicalCategory = $this->categoryResolver->fromPayload($payload, [
            $title,
            $category,
            $brand,
            $item['reward_type'] ?? null,
        ]);
        $marketSku = (string) ($item['market_sku'] ?? $this->marketSku($serviceSku));
        $now = now();

        return [
            'provider_id' => $provider->id,
            'sku' => $this->vault->encrypt($serviceSku),
            'sku_bidx' => $this->vault->computeBlindIndex($serviceSku),
            'market_sku' => $this->vault->encrypt($marketSku),
            'market_sku_bidx' => $this->vault->computeBlindIndex($marketSku),
            'name' => $title,
            'category' => $category,
            'canonical_category' => $canonicalCategory,
            'reward_type' => $item['reward_type'] ?? null,
            'purchase_price' => $purchasePrice,
            'retail_price' => $retailPrice,
            'min_price' => $this->number($item['min_price'] ?? $purchasePrice),
            'max_price' => $this->number($item['max_price'] ?? $retailPrice),
            'currency' => $this->currency($item['currency'] ?? 'USD'),
            'brand_id' => $brandId,
            'region_id' => $regionId,
            'image' => $this->nullableString($item['image'] ?? null, 255),
            'activation_url' => $this->nullableString($item['activation_url'] ?? null, 255),
            'redemption_instructions' => $this->nullableString($item['redemption_instructions'] ?? null),
            'is_active' => (bool) ($item['is_active'] ?? true),
            'data' => $this->vault->encrypt(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function category(array $item): ?string
    {
        return $item['category'] ?? data_get($item, 'categories.0.name');
    }

    private function resolveBrandId(
        Provider $provider,
        string $brand,
        string $serviceSku,
        string $title,
        string $category
    ): ?int {
        try {
            return MappingService::resolveBrand($provider->id, $brand, $serviceSku, $title, $category);
        } catch (\Throwable) {
            return null;
        }
    }

    private function currency(mixed $currency): string
    {
        if (is_array($currency)) {
            $currency = $currency['code'] ?? $currency['iso'] ?? 'USD';
        }

        return strtoupper(substr((string) ($currency ?: 'USD'), 0, 10));
    }

    private function marketSku(string $serviceSku): string
    {
        return 'WFC-'.substr(hash('sha256', trim($serviceSku)), 0, 16);
    }

    private function number(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }

    private function nullableString(mixed $value, int $limit = 1000): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $limit);
    }
}
