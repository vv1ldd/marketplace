<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Product;
use App\Models\WildflowCatalog;
use App\Models\WildflowSkuAlias;
use App\Services\BrandActivationUrlResolver;
use App\Services\CanonicalCategoryResolver;
use App\Services\MappingService;
use App\Services\Provider\EzPinCatalogPuller;
use App\Services\Provider\ProviderCatalogAggregator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncCatalogsCommand extends Command
{
    protected $signature = 'app:sync-catalogs
        {provider? : ID or type of provider to sync}
        {--force : Force sync even if catalog hasn\'t changed}
        {--embedded : Use meanly.one embedded provider catalog projection instead of the external Wildflow API}
        {--pull-upstream : Pull fresh provider catalog from the upstream SDK before syncing the embedded projection}';

    protected $description = 'Sync catalogs from external providers (Wildflow, etc.)';

    /** @var array<string, string> service_sku => текущий sku в каталоге (память между retailer / catalog) */
    private array $previousCatalogSkusByService = [];

    private array $receivedSkus = [];

    private array $brandCache = [];

    private array $currencyCache = [];

    /** @var array<int, string> id бренда → upper(name) для fallback URL без N+1 */
    private array $brandNameUpperById = [];

    public function handle()
    {
        $providerId = $this->argument('provider');
        
        $providers = \App\Models\Provider::where('is_active', true)
            ->when($providerId, function ($q) use ($providerId) {
                $q->where(function ($sq) use ($providerId) {
                    $sq->where('id', $providerId)->orWhere('type', $providerId);
                });
            })
            ->get();

        if ($providers->isEmpty()) {
            $this->error('No active providers found.');
            return;
        }

        foreach ($providers as $provider) {
            $this->info("--- Syncing Provider: [{$provider->name}] (type: {$provider->type}) ---");
            
            if (in_array($provider->type, ['wildflow', 'wildflow-sandbox', 'ezpin', 'ezpin-sandbox', 'fazer'], true)) {
                $this->syncWildflow($provider);
            } else {
                $this->warn("Provider type [{$provider->type}] not yet supported for automated sync.");
            }
        }
    }

    protected function syncWildflow($provider)
    {
        $this->info('Wildflow catalog sync started...');
        ini_set('memory_limit', '1024M');
        set_time_limit(600);

        $this->previousCatalogSkusByService = WildflowCatalog::query()
            ->whereNotNull('service_sku_bidx')
            ->pluck('sku', 'service_sku_bidx')
            ->all();
        $this->receivedSkus = [];
        $this->brandCache = [];
        $this->brandNameUpperById = [];
        $this->currencyCache = \App\Models\Currency::pluck('id', 'code')->toArray();

        if ($this->shouldPullEzPinUpstream($provider)) {
            $this->info('Pulling fresh EZPin catalog directly from upstream SDK...');
            $stats = app(EzPinCatalogPuller::class)->pullIntoProvider($provider);
            $this->info(sprintf(
                'EZPin upstream pull complete: %d persisted (%d catalog, %d retailer), %d deactivated.',
                $stats['total'],
                $stats['catalog'],
                $stats['retailer'],
                $stats['deactivated'],
            ));
        }

        $client = null;
        if (! $this->useEmbeddedCatalog($provider)) {
            $wildflowService = new \App\Services\WildflowService(overrideToken: null, providerModel: $provider);
            $client = $wildflowService->getClient();
        }

        $financeService = app(\App\Services\FinanceService::class);

        // 🌊 THE ULTIMATE COLLAPSE: Only one unified entry stream handles EVERYTHING!
        $this->parseCatalog($client, 'unified_catalog', $provider, $financeService);

        $this->processAutoSync($provider);
    }

    protected function processDeactivations($provider)
    {
        // LEGACY: Intentionally bypassed in favor of the robust Soft-Reset & Populate model.
        return;
    }

    protected function processAutoSync($provider)
    {
        if (count($this->receivedSkus) === 0) return;

        $autoSyncShops = \App\Models\Shop::where('is_wildflow_auto_sync', true)->get();
        if ($autoSyncShops->isEmpty()) return;

        $this->info('Auto-syncing products to '.$autoSyncShops->count().' shops...');

        foreach ($autoSyncShops as $shop) {
            $allowedBrandIds = $shop->allowed_categories ?? [];
            if (empty($allowedBrandIds)) continue;

            $productsToSync = \App\Models\Product::where('provider_id', $provider->id)
                ->where('is_active', true)
                ->whereIn('brand_id', $allowedBrandIds)
                ->pluck('id')
                ->toArray();

            if (! empty($productsToSync)) {
                $shop->products()->syncWithoutDetaching($productsToSync);
                $this->info("  [AUTO-SYNC] Linked " . count($productsToSync) . " products to shop [{$shop->name}].");
            }
        }
    }

    private function useEmbeddedCatalog($provider): bool
    {
        if ($this->shouldPullEzPinUpstream($provider)) {
            return true;
        }

        if ((bool) $this->option('embedded')) {
            return true;
        }

        return (string) data_get($provider->settings, 'catalog_source') === 'embedded';
    }

    private function shouldPullEzPinUpstream($provider): bool
    {
        if (! (bool) $this->option('pull-upstream')) {
            return false;
        }

        return in_array($provider->type, ['wildflow', 'wildflow-sandbox', 'ezpin', 'ezpin-sandbox'], true);
    }

    private function parseCatalog($client, string $type, $provider, $financeService): bool
    {
        // 🌊 THE GRAND CONFLUENCE: Pull everything in ONE standardized request!
        if ($this->useEmbeddedCatalog($provider)) {
            $payload = app(ProviderCatalogAggregator::class)->unifiedCatalog($provider, true);
            $items = $payload['items'] ?? [];
            $responseBody = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } else {
            $providerSlug = $provider->type === 'wildflow' ? 'ezpin' : $provider->type;
            $endpoint = "providers/{$providerSlug}/unified-catalog";
            $response = $client->get($endpoint, ['include_inactive' => 1]);
            $items = $response->json('items') ?? [];
            $responseBody = $response->body();
        }
        
        $this->info("Unified Catalog returned ".count($items).' total normalized items.');

        if (count($items) === 0) {
            $this->error("Catalog response for {$type}: ".$responseBody);
            return false;
        }

        // 🧠 Smart Fetching: Check if catalog has changed
        // Strip dynamic dates and sort items by SKU to ensure stable hashing
        $hashItems = collect($items)->map(function($item) {
            $cleanItem = $item;
            unset($cleanItem['updated_at'], $cleanItem['created_at'], $cleanItem['raw_data']); // Drop dynamic values for stable hash
            return $cleanItem;
        })->sortBy('service_sku')->values()->toArray();
        
        $catalogHash = hash('sha256', json_encode($hashItems));
        $lastHash = $provider->settings["last_hash_{$type}"] ?? null;
        
        if ($catalogHash === $lastHash && !$this->option('force')) {
            $this->info("  > Catalog [{$type}] hasn't changed. Skipping deep sync.");
            // Mark all items as received to prevent deactivation
            $this->receivedSkus = array_merge(
                $this->receivedSkus, 
                WildflowCatalog::where('type', $type)->where('is_active', true)->pluck('sku')->toArray()
            );
            return true;
        }

        $rows = [];
        $skuMigrations = [];
        $categoryResolver = app(CanonicalCategoryResolver::class);

        foreach ($items as $item) {
            // 🛡️ OBLIGATORIO PRICE VALIDATION
            // Skip unsafe items missing BOTH direct buying price and percentage to prevent financial losses.
            $rawEzpinItem = $item['raw_data'] ?? [];

            $hasBuyingPrice = ($item['buying_price'] 
                ?? $item['data']['buying_price'] 
                ?? $rawEzpinItem['buying_price'] 
                ?? $rawEzpinItem['data']['buying_price'] 
                ?? $item['min_price']
                ?? $rawEzpinItem['price']
                ?? null) !== null;

            $hasPercentage = ($item['percentage_of_buying_price'] 
                ?? $item['data']['percentage_of_buying_price'] 
                ?? $rawEzpinItem['percentage_of_buying_price'] 
                ?? $rawEzpinItem['data']['percentage_of_buying_price'] 
                ?? null) !== null;

            if (!$hasBuyingPrice && !$hasPercentage) {
                continue;
            }

            $serviceSku = (string) $item['service_sku'];
            $vault = app(\App\Services\VaultTransitService::class);
            $serviceSkuBidx = $vault->computeBlindIndex($serviceSku);

            $newSku = $this->resolveCatalogSku($serviceSku);
            $oldSku = $this->previousCatalogSkusByService[$serviceSkuBidx] ?? null;
            if ($oldSku !== null && $oldSku !== '' && $oldSku !== $newSku) {
                $skuMigrations[$oldSku] = ['old' => $oldSku, 'new' => $newSku];
            }
            $this->previousCatalogSkusByService[$serviceSkuBidx] = $newSku;

            $isItemActive = ($item['is_available'] ?? false) && (($item['status'] ?? '') === 'active');

            if (!$isItemActive) {
                \App\Models\WildflowCatalog::applyProviderOutOfStockToSku($newSku, 'sync');
            }

            $rows[] = [
                'provider_id' => $provider->id,
                'service_sku' => $vault->encrypt($serviceSku),
                'service_sku_bidx' => $serviceSkuBidx,
                'sku' => $newSku,
                'data' => json_encode($item),
                'type' => $item['inventory_type'] ?? $type, // Dynamic stream identity!
                'canonical_category' => $categoryResolver->fromPayload($item, [$item['inventory_type'] ?? $type]),
                'is_active' => $isItemActive,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $this->receivedSkus[] = $newSku;
        }

        if (count($rows) === 0) {
            $this->error("No valid catalog rows parsed for {$type}. Existing products were left untouched.");
            return false;
        }

        // Soft-deactivate only after a valid upstream payload has been parsed.
        $this->info("Pre-reset: Soft-deactivating current products...");
        if (! $this->useEmbeddedCatalog($provider)) {
            \App\Models\ProviderProduct::where('provider_id', $provider->id)->update(['is_active' => false]);
        }
        \App\Models\WildflowCatalog::where('provider_id', $provider->id)->update(['is_active' => false]);
        \App\Models\Product::where('provider_id', $provider->id)->update(['is_active' => false]);

        $this->info("Upserting ".count($rows)." catalog rows to database (chunked)...");
        foreach (array_chunk($rows, 500) as $chunk) {
            WildflowCatalog::upsert(
                $chunk,
                ['provider_id', 'service_sku_bidx'],
                ['service_sku', 'sku', 'data', 'type', 'canonical_category', 'is_active', 'updated_at']
            );
        }

        $this->applyCatalogSkuMigrations(array_values($skuMigrations));

        // Get exchange rates
        $bybit_service = new \App\Http\Services\BybitService;
        $tax = $provider->settings['tax'] ?? 30;
        $manual_rate = $provider->settings['currency_rate'] ?? null;

        $usdt_rub = $bybit_service->tickerPrice('USDTRUB');
        // Wildflow is usually USD based
        $effective_rate = $manual_rate ?? $usdt_rub;

        $ym = new \App\Http\Controllers\Ym\MainController($tax);

        // Синхронизируем с универсальной таблицей products и provider_products
        $products = [];
        $providerProducts = [];

        foreach ($rows as $row) {
            $item = json_decode($row['data'], true);
            $serviceSku = (string) ($item['service_sku'] ?? $item['data']['sku'] ?? $item['data']['service_sku'] ?? 'UNKNOWN');
            $newSku = $row['sku']; // The resolved WFC SKU stored in our loop memory
            $rawEzpinItem = $item['raw_data'] ?? []; // Peak inside raw feed if needed
            $preOrderSupported = $this->firstBool(
                data_get($item, 'provider_purchase.pre_order'),
                data_get($item, '_wildflow_purchase.pre_order'),
                data_get($item, 'purchase.pre_order'),
                data_get($item, 'pre_order'),
                data_get($item, 'product.pre_order'),
                data_get($rawEzpinItem, 'pre_order'),
                data_get($rawEzpinItem, 'product.pre_order'),
                false
            );
            $item['pre_order'] = $preOrderSupported;
            if (! isset($item['provider_purchase']) || ! is_array($item['provider_purchase'])) {
                $item['provider_purchase'] = [];
            }
            $item['provider_purchase']['pre_order'] = $preOrderSupported;

            // 🌟 PURE ARCHITECTURAL ELEGANCE: Direct Flat Extractions
            // 🌟 ROBUST RESOLUTION: Extract values utilizing deep defensive checks covering ALL JSON structures
            $title = $item['name'] ?? $item['item']['title'] ?? $item['data']['title'] ?? $row['sku'];
            
            $minPrice = (float)($item['min_price'] ?? $item['data']['min_price'] ?? $item['data']['price']['min'] ?? 0);
            $maxPrice = (float)($item['max_price'] ?? $item['data']['max_price'] ?? $item['data']['price']['max'] ?? 0);
            
            $currencyCode = $item['currency'] ?? $item['data']['currency']['code'] ?? 'USD';
            $externalBrandName = $item['brand'] ?? $item['category'] ?? $item['data']['categories'][0]['name'] ?? 'WILDFLOW GIFTS';
            $providerCategoryName = $item['category'] ?? $item['data']['categories'][0]['name'] ?? $externalBrandName;

            // Calculated values ensuring stability for ranged products: Always lean on Min Price!
            $retailPrice = ($minPrice > 0) ? $minPrice : $maxPrice;
            
            // 🛡️ ROBUST DISCOUNT & PURCHASE PRICE RESOLUTION
            $rawBuyingPrice = $item['buying_price'] 
                ?? $item['data']['buying_price'] 
                ?? $rawEzpinItem['buying_price'] 
                ?? $rawEzpinItem['data']['buying_price'] 
                ?? $item['min_price']
                ?? $rawEzpinItem['price']
                ?? null;

            $percentageAdjustment = $item['percentage_of_buying_price'] 
                ?? $item['data']['percentage_of_buying_price'] 
                ?? $rawEzpinItem['percentage_of_buying_price'] 
                ?? $rawEzpinItem['data']['percentage_of_buying_price'] 
                ?? null;

            if ($rawBuyingPrice !== null && (float)$rawBuyingPrice > 0) {
                $purchasePrice = (float) $rawBuyingPrice;
            } elseif ($percentageAdjustment !== null) {
                // If percentage_of_buying_price is e.g. -15, then price is 85% of nominal.
                $purchasePrice = (float) ($retailPrice * (1 + ((float) $percentageAdjustment / 100)));
            } else {
                $purchasePrice = $retailPrice; 
            }

            $name = 'Подарочная карта ' . $title;

            // Auto-create missing currencies
            if (! isset($this->currencyCache[$currencyCode])) {
                $newCurrency = \App\Models\Currency::firstOrCreate(
                    ['code' => $currencyCode],
                    [
                        'name' => $currencyCode,
                        'symbol' => $currencyCode, // Simplified symbol fallback
                        'is_auto_update' => true,
                        'rate_to_rub' => 1.0,
                    ]
                );
                $this->currencyCache[$currencyCode] = $newCurrency->id;
                $this->info("Created new currency: {$currencyCode}");
            }

            // Calculate price in RUB for reference
            $rate = $financeService->getRate($currencyCode);
            $priceRub = (int) round($retailPrice * $rate * 100);

            // Standardize dynamic name formatting
            if ($minPrice > 0 && $maxPrice > 0 && $minPrice !== $maxPrice) {
                $name = $title . ' ' . $minPrice . '-' . $maxPrice . $currencyCode;
            } else {
                $name = $title . ' ' . $retailPrice . $currencyCode;
            }

            $categoryLabel = 'Подарочные карты';
            $category = $categoryLabel;

            // 🔥 Unified Mapping System
            $brandId = \App\Services\MappingService::resolveBrand(
                $provider->id,
                $externalBrandName,
                $row['sku'],
                $title,
                $providerCategoryName
            );

            // Fallback resolution attempts
            if (!$brandId) {
                $firstWord = trim(explode(' ', trim($title))[0]);
                if (!empty($firstWord) && strlen($firstWord) > 2) {
                    $brandId = \App\Services\MappingService::resolveBrand($provider->id, $firstWord, null, null);
                }
            }

            if (!$brandId) {
                $brandObj = \App\Models\Brand::firstOrCreate(['name' => 'Нет бренда']);
                $brandId = $brandObj->id;
            }

            // Specialized Metadata Analysis (Passing legacy raw payload for compatibility)
            $meta = MappingService::extractRedemptionMetadata($rawEzpinItem);
            $activation = trim((string) ($meta['activation_url'] ?? ''));
            if ($activation === '') {
                $activation = $this->activationUrlFromItemPayload($rawEzpinItem);
            }
            $brandUpper = $this->brandDisplayNameUpper((int) $brandId, (string) $externalBrandName);
            if ($activation === '') {
                $activation = (string) (BrandActivationUrlResolver::fallbackActivationUrl(
                    $brandUpper,
                    strtoupper($title . ' ' . $categoryLabel)
                ) ?? '');
            }
            $activation = $activation !== '' ? mb_substr($activation, 0, 255) : null;

            $rewardType = $meta['reward_type'] ?? null;
            $rewardType = is_string($rewardType) && $rewardType !== '' ? mb_substr($rewardType, 0, 255) : null;

            $upc = $meta['upc'] ?? null;
            $upc = is_string($upc) && $upc !== '' ? mb_substr($upc, 0, 255) : null;

            // 🎯 TARGET ACQUIRED: Locate DB record by original decrypted input using secure index
            $vault = app(\App\Services\VaultTransitService::class);
            $serviceSkuBidx = $vault->computeBlindIndex((string)$item['service_sku']);

            // 🌍 RESOLVE REGION ID
            $regionId = null;
            $wfRegion = $item['region'] ?? null;
            
            // Fallback for older items if needed
            if (!$wfRegion) {
                $wfRegion = data_get($rawEzpinItem, 'product.regions.0.code') ?? data_get($rawEzpinItem, 'regions.0.code');
            }
            
            $wfRegionName = data_get($rawEzpinItem, 'product.regions.0.name') ?? data_get($rawEzpinItem, 'regions.0.name') ?? $wfRegion;
            
            if ($wfRegion) {
                $regionId = \App\Services\MappingService::resolveRegion($wfRegion, $wfRegionName);
            }

            WildflowCatalog::query()->where('service_sku_bidx', $serviceSkuBidx)->update([
                'brand_id'                => $brandId,
                'region_id'               => $regionId,
                'retail_price'            => $retailPrice,
                'purchase_price'          => $purchasePrice,
                'min_price'               => $minPrice,
                'max_price'               => $maxPrice,
                'redemption_instructions' => $meta['redemption_instructions'],
                'activation_url'          => $activation,
                'reward_type'             => $rewardType,
                'upc'                     => $upc,
            ]);

            // Identify Category ID (Global ID from DB)
            $catId = 3629; // "Онлайн-подписки и карты оплаты"
            if ($categoryLabel === 'Подписка') {
                $catId = 3629; // Same category for now as it handles both
            }

            // Map Attributes to Yandex Params
            $params = [
                '37821410' => $retailPrice, // Номинал
                '37693330' => '39982970',   // Вид поставки: электронный ключ
                '37978250' => '39047470',   // Применение: пополнение счета
            ];

            // Map Region
            $wfRegion = data_get($rawEzpinItem, 'product.regions.0.code') ?? data_get($rawEzpinItem, 'regions.0.code');
            if ($wfRegion) {
                $regionMap = [
                    'US' => '44341770',
                    'TR' => '51048330',
                    'AR' => '51157105',
                    'PL' => '51916613',
                    'IN' => '53952735',
                    'AE' => '54022287',
                    'UA' => '65510503',
                    'JP' => '73064019',
                    'RU' => '39017222',
                ];
                if (isset($regionMap[$wfRegion])) {
                    $params['37919810'] = $regionMap[$wfRegion];
                } else {
                    $params['37919810'] = '39017223'; // "все страны" fallback
                }
            }

            // Map Brand to Service Name if possible
            if ($externalBrandName) {
                $serviceMap = [
                    'Steam' => '43534830',
                    'Xbox' => '39043565',
                    'Nintendo' => '43499070',
                    'Roblox' => '43534850',
                    'iTunes' => '39043561',
                    'Google Play' => '39043572',
                    'Battle.net' => '50887955',
                ];
                foreach ($serviceMap as $namePart => $id) {
                    if (str_contains(strtolower($externalBrandName), strtolower($namePart))) {
                        $params['37972050'] = $id;
                        break;
                    }
                }
            }

            // Marketplace Product (What shops see)
            $products[] = [
                'provider_id' => $provider->id,
                'brand_id' => $brandId,
                'category_id' => $catId,
                'sku' => (string) $row['service_sku'],
                'name' => $name,
                'type' => 'giftcard',
                'category' => $category,
                'canonical_category' => $row['canonical_category'] ?? $categoryResolver->fromPayload($item, [$category]),
                'purchase_price' => (float)$retailPrice,   // Float
                'purchase_currency' => $currencyCode,
                'base_price' => (float)$purchasePrice,     // Float
                'price_rub' => $priceRub,
                'params' => $params,
                'data' => $row['data'],
                'is_active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ];

            // Internal Provider Product (What WE see)
            // 💡 CLEANUP: Remove heavy raw_data from local storage to prevent 'Data too long' crashes!
            unset($item['raw_data']);
            
            $providerProducts[] = [
                'provider_id'    => $provider->id,
                'sku'            => $serviceSku,
                'market_sku'     => $newSku,
                'name'           => $title,
                'category'       => $category,
                'canonical_category' => $row['canonical_category'] ?? $categoryResolver->fromPayload($item, [$category]),
                'purchase_price' => (float) $purchasePrice,
                'retail_price'   => (float) $retailPrice,
                'min_price'      => $minPrice,
                'max_price'      => $maxPrice,
                'currency'       => $currencyCode,
                'brand_id'       => $brandId,
                'region_id'      => $regionId,
                'is_active'      => (bool)$row['is_active'],
                'data'           => json_encode($item), // Using sanitized slim array!
                'updated_at'     => now(),
                'created_at'     => now(),
            ];
        }

        // 🚫 Architecural Shift: We NO LONGER update the main products table here.
        // The main 'products' table is for store-specific inventory only.
        // We only maintain the internal Provider Catalog as 'raw material'.

        // Update Internal Provider Products (High Speed Direct DB Mode to bypass mysterious model-lock)
        $this->info('Updating provider products ('.count($providerProducts).' items) via High-Speed DB Bridge...');
        
        foreach (array_chunk($providerProducts, 200) as $chunk) {
            $processedChunk = [];
            $vault = app(\App\Services\VaultTransitService::class);
            
            foreach ($chunk as $pp) {
                $skuBidx = $vault->computeBlindIndex($pp['sku']);
                $processedChunk[] = [
                    'provider_id'    => $pp['provider_id'],
                    'sku_bidx'       => $skuBidx,
                    'sku'            => $vault->encrypt($pp['sku']), // 🛡️ LOCK DOWN: Never send raw data!
                    'market_sku'     => $vault->encrypt($pp['market_sku']), // 🛡️ LOCK DOWN!
                    'market_sku_bidx'=> $vault->computeBlindIndex($pp['market_sku']),
                    'name'           => $pp['name'],
                    'category'       => $pp['category'] ?? null,
                    'canonical_category' => $pp['canonical_category'] ?? null,
                    'purchase_price' => $pp['purchase_price'],
                    'retail_price'   => $pp['retail_price'] ?? 0,
                    'min_price'      => $pp['min_price'],
                    'max_price'      => $pp['max_price'],
                    'currency'       => $pp['currency'],
                    'brand_id'       => $pp['brand_id'] ?? null,
                    'region_id'      => $pp['region_id'] ?? null,
                    'is_active'      => $pp['is_active'] ? 1 : 0,
                    'data'           => $pp['data'],
                    'updated_at'     => now(),
                    'created_at'     => now(),
                ];
            }

            \Illuminate\Support\Facades\DB::table('provider_products')->upsert(
                $processedChunk,
                ['provider_id', 'sku_bidx'], // Unique Index
                [
                    'sku', 'market_sku', 'market_sku_bidx', 'name', 'category', 'canonical_category', 'purchase_price', 'retail_price', 
                    'min_price', 'max_price', 'currency', 'brand_id', 'region_id', 'is_active', 'data', 'updated_at'
                ]
            );
        }

        // Update Provider settings with new hash
        $settings = $provider->settings ?? [];
        $settings["last_hash_{$type}"] = $catalogHash;
        $provider->update([
            'settings' => $settings,
            'last_sync_at' => now(), // ⌚ UPDATE DASHBOARD TIME!
        ]);

        return true;
    }

    private function brandDisplayNameUpper(int $brandId, string $externalBrandFallback): string
    {
        if ($brandId > 0) {
            if (! array_key_exists($brandId, $this->brandNameUpperById)) {
                $name = (string) Brand::query()->whereKey($brandId)->value('name');
                $this->brandNameUpperById[$brandId] = strtoupper(trim($name));
            }
            if ($this->brandNameUpperById[$brandId] !== '') {
                return $this->brandNameUpperById[$brandId];
            }
        }

        return strtoupper(trim($externalBrandFallback));
    }

    /**
     * @param  array<string, mixed>  $item  Полный объект позиции из API (как в wildflow_catalogs.data).
     */
    private function activationUrlFromItemPayload(array $item): string
    {
        foreach ([
            'data.activation_url',
            'data.product.activation_url',
            'data.redemption_url',
            'data.product.redemption_url',
            'activation_url',
        ] as $path) {
            $raw = trim((string) data_get($item, $path));
            if ($raw === '') {
                continue;
            }
            if (preg_match('#^https?://#i', $raw)) {
                return mb_substr($raw, 0, 255);
            }
            $fromText = MappingService::extractActivationUrlFromText($raw);
            if ($fromText) {
                return mb_substr($fromText, 0, 255);
            }
        }

        return '';
    }

    /**
     * Короткий стабильный SKU каталога по service_sku Wildflow (один и тот же при каждом парсе).
     */
    private function resolveCatalogSku(string $serviceSku): string
    {
        $serviceSku = trim($serviceSku);

        return 'WFC-'.substr(hash('sha256', $serviceSku), 0, 16);
    }

    private function firstBool(mixed ...$values): bool
    {
        foreach ($values as $value) {
            if ($value === null) {
                continue;
            }

            if (is_bool($value)) {
                return $value;
            }

            if (is_numeric($value)) {
                return (int) $value === 1;
            }

            if (is_string($value)) {
                $normalized = strtolower(trim($value));
                if (in_array($normalized, ['1', 'true', 'yes', 'y', 'on'], true)) {
                    return true;
                }
                if (in_array($normalized, ['0', 'false', 'no', 'n', 'off', ''], true)) {
                    return false;
                }
            }

            return (bool) $value;
        }

        return false;
    }

    /**
     * Алиас старый → новый + обновление products у магазинов из config('services.wildflow.sku_map_shop_ids').
     *
     * @param  array<int, array{old: string, new: string}>  $migrations
     */
    private function applyCatalogSkuMigrations(array $migrations): void
    {
        if ($migrations === []) {
            return;
        }

        $shopIds = config('services.wildflow.sku_map_shop_ids', [1]);
        if ($shopIds === []) {
            return;
        }

        foreach (array_chunk($migrations, 80) as $chunk) {
            DB::transaction(function () use ($chunk, $shopIds): void {
                foreach ($chunk as $m) {
                    $old = $m['old'];
                    $new = $m['new'];
                    if ($old === '' || $new === '' || $old === $new) {
                        continue;
                    }

                    WildflowSkuAlias::query()->updateOrInsert(
                        ['alias_sku' => $old],
                        [
                            'wildflow_catalog_sku' => $new,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );

                    $vault = app(\App\Services\VaultTransitService::class);
                    $oldBidx = $vault->computeBlindIndex($old);

                    Product::query()
                        ->whereHas('shop', fn ($q) => $q->whereIn('shops.id', $shopIds))
                        ->where(function ($q) use ($old, $oldBidx) {
                            $q->where('wildflow_catalog_sku_bidx', $oldBidx)->orWhere('sku', $old);
                        })
                        ->each(function (Product $product) use ($old, $new): void {
                            if ($product->wildflow_catalog_sku === $old) {
                                $product->wildflow_catalog_sku = $new;
                            }
                            if ($product->sku === $old) {
                                $product->sku = $new;
                            }
                            $product->saveQuietly();
                            WildflowSkuAlias::syncForProduct($product);
                        });
                }
            });
        }
    }
}

