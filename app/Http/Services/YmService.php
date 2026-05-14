<?php

namespace App\Http\Services;

use App\Models\Product;
use App\Models\Settings;
use App\Models\Shop;
use AppYandexSdk\Api\BusinessOfferMappingsApi;
use AppYandexSdk\Api\CategoriesApi;
use AppYandexSdk\Api\OffersApi;
use AppYandexSdk\Api\PricesApi;
use AppYandexSdk\Configuration;
use AppYandexSdk\Model\GetCampaignOffersRequest;
use GuzzleHttp\Client;

class YmService
{
    private Configuration $config;

    private Client $httpClient;

    private int $ym_business_id;

    private int $campaign_id;

    public function __construct(?Shop $shop = null)
    {
        if ($shop) {
            $this->ym_business_id = (int) $shop->business_id;
            $this->campaign_id = (int) $shop->campaign_id;
            $api_key = trim((string) $shop->api_key); // 🛡️ Safety trim against clipboard weirdness
        } else {
            $this->ym_business_id = (int) Settings::get('YM_BUSINESS_ID', config('services.ym.business_id', 143486522));
            $this->campaign_id = (int) Settings::get('YM_CAMPAIGN_ID', config('services.ym.campaign_id', 143486522));
            $api_key = Settings::get('YM_API_KEY', config('services.ym.api_key', 'ACMA:3mHDTfT7sVhGMb6xtQXGOoq5RzpHvLCjTq12Jd1M:bf243683'));
        }

        $this->config = Configuration::getDefaultConfiguration()
            ->setApiKey('Api-Key', $api_key)
            ->setHost('https://api.partner.market.yandex.ru');

        $this->httpClient = new Client([
            'timeout' => 60,
            'verify' => false,
        ]);
    }

    /**
     * BUSINESS LEVEL: Offer Mappings (Catalog)
     */
    public function getOfferMappings(?string $pageToken = null, array $params = [])
    {
        $api = new BusinessOfferMappingsApi($this->httpClient, $this->config);

        // Note: SDK signature is ($business_id, $page_token, $limit, $language, $request_body)
        $response = $api->getOfferMappings(
            $this->ym_business_id,
            $pageToken,
            100
        );

        // Convert to array for compatibility with existing code
        $result = json_decode((string) $response, true);

        return $result['result'] ?? $result;
    }

    /**
     * BUSINESS LEVEL: Update Offer Mappings (Catalog)
     */
    public function offerMappingsUpdate(array $mappings)
    {
        $api = new BusinessOfferMappingsApi($this->httpClient, $this->config);

        $request = new \AppYandexSdk\Model\UpdateOfferMappingsRequest;

        $mappingDtos = [];
        foreach ($mappings as $item) {
            $mappingDto = new \AppYandexSdk\Model\UpdateOfferMappingDTO;

            if (isset($item['offer'])) {
                $offerData = $item['offer'];

                // Convert parameter_values arrays to DTO objects
                if (isset($offerData['parameter_values']) && is_array($offerData['parameter_values'])) {
                    $offerData['parameter_values'] = array_map(function ($p) {
                        return new \AppYandexSdk\Model\ParameterValueDTO($p);
                    }, $offerData['parameter_values']);
                }

                // Convert basic_price array to DTO object
                if (isset($offerData['basic_price']) && is_array($offerData['basic_price'])) {
                    $offerData['basic_price'] = new \AppYandexSdk\Model\PriceWithDiscountDTO($offerData['basic_price']);
                }

                // Convert manuals arrays to DTO objects
                if (isset($offerData['manuals']) && is_array($offerData['manuals'])) {
                    $offerData['manuals'] = array_map(function ($m) {
                        return new \AppYandexSdk\Model\OfferManualDTO($m);
                    }, $offerData['manuals']);
                }

                $offerDto = new \AppYandexSdk\Model\UpdateOfferDTO($offerData);

                // Ensure it's marked as downloadable (The "Old Way" standard)
                $offerDto->setDownloadable(true);

                // Centralized Supply Type enforcement
                $params = $offerDto->getParameterValues() ?: [];
                $hasSupplyType = false;
                foreach ($params as $p) {
                    if ($p->getParameterId() == 37693330) {
                        $p->setValueId(39982970); // Force electronic key
                        $p->setValue('электронный ключ');
                        $hasSupplyType = true;
                        break;
                    }
                }

                if (! $hasSupplyType) {
                    $params[] = new \AppYandexSdk\Model\ParameterValueDTO([
                        'parameter_id' => 37693330,
                        'value_id' => 39982970,
                        'value' => 'электронный ключ',
                    ]);
                }

                $offerDto->setParameterValues($params);
                $mappingDto->setOffer($offerDto);
            }

            if (isset($item['mapping'])) {
                $mappingDto->setMapping(new \AppYandexSdk\Model\UpdateMappingDTO($item['mapping']));
            }

            $mappingDtos[] = $mappingDto;
        }

        $request->setOfferMappings($mappingDtos);

        $response = $api->updateOfferMappings($this->ym_business_id, $request);

        return json_decode((string) $response, true);
    }

    /**
     * BUSINESS LEVEL: Prices
     */
    public function getBusinessPrices(?string $pageToken = null)
    {
        $api = new PricesApi($this->httpClient, $this->config);

        $response = $api->getDefaultPrices(
            $this->ym_business_id,
            $pageToken,
            500
        );

        $result = json_decode((string) $response, true);

        return $result['result'] ?? $result;
    }

    /**
     * CAMPAIGN LEVEL: Get Offers (Prices/Availability)
     */
    public function getCampaignOffers(?string $pageToken = null)
    {
        $api = new OffersApi($this->httpClient, $this->config);

        // Empty request body for "all offers"
        $requestBody = new GetCampaignOffersRequest;

        $response = $api->getCampaignOffers(
            $this->campaign_id,
            $requestBody,
            $pageToken,
            200
        );

        $result = json_decode((string) $response, true);

        return $result['result'] ?? $result;
    }

    /**
     * GLOBAL: Category Content Parameters (schema for product attributes)
     */
    public function getCategoryParameters(int $categoryId): array
    {
        $api = new \AppYandexSdk\Api\ContentApi($this->httpClient, $this->config);

        $response = $api->getCategoryContentParameters($categoryId);
        $result = json_decode((string) $response, true);

        return $result['result']['parameters'] ?? [];
    }

    /**
     * GLOBAL: Categories Tree
     */
    public function getCategoriesTree()
    {
        $api = new CategoriesApi($this->httpClient, $this->config);

        $response = $api->getCategoriesTree('RU');

        $result = json_decode((string) $response, true);

        return $result['result'] ?? $result;
    }

    /**
     * STOCKS: Get all Stocks (handles pagination)
     */
    public function getStocks(?array $requestBodyArray = null)
    {
        $api = new \AppYandexSdk\Api\StocksApi($this->httpClient, $this->config);
        $allWarehouses = [];
        $pageToken = null;

        do {
            $requestBody = null;
            if (! empty($requestBodyArray)) {
                $requestBody = new \AppYandexSdk\Model\GetWarehouseStocksRequest($requestBodyArray);
            } else {
                $requestBody = new \AppYandexSdk\Model\GetWarehouseStocksRequest;
            }

            $response = $api->getStocks(
                $this->campaign_id,
                $pageToken,
                200, // Maximum allowed by Yandex is 200 for stocks
                $requestBody
            );

            $result = json_decode((string) $response, true);
            $warehouses = $result['result']['warehouses'] ?? $result['warehouses'] ?? [];
            
            // Merge warehouses by ID
            foreach ($warehouses as $wh) {
                $whId = $wh['warehouseId'];
                if (!isset($allWarehouses[$whId])) {
                    $allWarehouses[$whId] = $wh;
                } else {
                    $allWarehouses[$whId]['offers'] = array_merge($allWarehouses[$whId]['offers'], $wh['offers']);
                }
            }

            $pageToken = $result['result']['paging']['nextPageToken'] ?? $result['paging']['nextPageToken'] ?? null;
        } while ($pageToken);

        return ['warehouses' => array_values($allWarehouses)];
    }

    /**
     * STOCKS: Update Stocks (Internal call with raw array)
     */
    public function updateStocks(array $requestBodyArray)
    {
        $api = new \AppYandexSdk\Api\StocksApi($this->httpClient, $this->config);

        $requestBody = new \AppYandexSdk\Model\UpdateStocksRequest($requestBodyArray);

        $response = $api->updateStocks(
            $this->campaign_id,
            $requestBody
        );

        return json_decode((string) $response, true);
    }

    /**
     * WAREHOUSES: Get Warehouses
     */
    public function getWarehouses()
    {
        $api = new \AppYandexSdk\Api\WarehousesApi($this->httpClient, $this->config);

        $response = $api->getPagedWarehouses($this->ym_business_id);

        $result = json_decode((string) $response, true);

        $warehouses = $result['result']['warehouses'] ?? $result['warehouses'] ?? [];

        // Map to id/name structure expected by WarehouseResource
        return array_map(fn($w) => [
            'id'   => $w['warehouseId'] ?? $w['id'],
            'name' => $w['name']
        ], $warehouses);
    }

    /**
     * CLEANUP: Identify offers on Yandex that don't exist locally and zero them out
     */
    public function cleanupYandexOffers()
    {
        $shop = Shop::where('business_id', $this->ym_business_id)->first();
        $ymWarehouseId = $shop->ym_warehouse_id;
        
        if (!$ymWarehouseId) {
             $fallbackWh = \App\Models\Warehouse::where('shop_id', $shop->id)->whereNotNull('ym_id')->first();
             $ymWarehouseId = $fallbackWh?->ym_id;
        }

        if (!$ymWarehouseId) {
            throw new \Exception("ID склада Яндекса не настроен. Невозможно обнулить остатки.");
        }

        // 1. Fetch all SKUs from Yandex (Business Level Catalog)
        $ymSkus = [];
        $pageToken = null;
        do {
            $response = $this->getOfferMappings($pageToken);
            $offers = $response['offers'] ?? [];
            foreach ($offers as $offer) {
                if (isset($offer['offerId'])) {
                    $ymSkus[] = $offer['offerId'];
                }
            }
            $pageToken = $response['paging']['nextPageToken'] ?? null;
        } while ($pageToken);

        // 2. Fetch all local SKUs
        $localSkus = Product::where('shop_id', $shop->id)->pluck('sku')->toArray();
        $localSkus = array_filter($localSkus); // Remove empty SKUs

        // 3. Identify SKUs to "Drop" (On Yandex but not locally)
        $skusToDrop = array_diff($ymSkus, $localSkus);

        if (empty($skusToDrop)) {
            return 0;
        }

        // 4. Zero out stocks for these SKUs
        $payload = [];
        foreach ($skusToDrop as $sku) {
            $payload[] = [
                'sku' => $sku,
                'warehouseId' => (int)$ymWarehouseId,
                'items' => [
                    [
                        'count' => 0,
                        'type' => 'FIT',
                        'updatedAt' => now()->toIso8601String(),
                    ]
                ]
            ];
        }

        $chunks = array_chunk($payload, 2000);
        foreach ($chunks as $chunk) {
            $this->updateStocks(['skus' => $chunk]);
        }

        return count($skusToDrop);
    }

    /**
     * Update Prices for a collection of products
     */
    public function updatePrices($products)
    {
        $api = new PricesApi($this->httpClient, $this->config);
        $financeService = app(\App\Services\FinanceService::class);
        $shop = Shop::where('business_id', $this->ym_business_id)->first();
        
        $priceDtos = [];
        foreach ($products as $product) {
            $priceRub = $financeService->getShopFinalPrice($product, $shop);
            
            $priceDtos[] = [
                'offerId' => $product->sku,
                'price' => [
                    'value' => (float)($priceRub / 100),
                    'currencyId' => 'RUR',
                ],
            ];
        }
        
        $chunks = array_chunk($priceDtos, 500);
        foreach ($chunks as $chunk) {
            $request = new \AppYandexSdk\Model\UpdateBusinessPricesRequest(['offers' => $chunk]);
            $api->updateBusinessPrices($this->ym_business_id, $request);
        }
        
        return count($priceDtos);
    }

    /**
     * Update Stocks for a collection of products based on the Main Warehouse
     */
    public function updateStocksForProducts($products)
    {
        $shop = Shop::where('business_id', $this->ym_business_id)->first();
        
        // 1. Get Yandex Warehouse ID from Settings or Fallback
        $ymWarehouseId = $shop->ym_warehouse_id;
        
        if (!$ymWarehouseId) {
             $fallbackWh = \App\Models\Warehouse::where('shop_id', $shop->id)->whereNotNull('ym_id')->first();
             $ymWarehouseId = $fallbackWh?->ym_id;
        }

        if (!$ymWarehouseId) {
            throw new \Exception("ID склада Яндекса не настроен. Пожалуйста, выберите его в Настройках Яндекс Маркета.");
        }

        // 2. Get Master Warehouse for Stock Count
        $masterWarehouse = \App\Models\Warehouse::where('shop_id', $shop->id)->where('is_main', true)->first();
        if (!$masterWarehouse) {
            throw new \Exception("Мастер-склад не найден. Пожалуйста, создайте его в разделе 'Склады'.");
        }

        $skus = [];
        // Group by SKU to avoid DUPLICATE_SHOP_SKU error
        $uniqueProducts = $products->unique('sku');
        
        foreach ($uniqueProducts as $product) {
            if (empty($product->sku)) continue;
            
            // Calculate stock on the Master warehouse
            $stock = \App\Models\WarehouseStock::where('warehouse_id', $masterWarehouse->id)
                ->where('product_id', $product->id)
                ->sum('count');
            
            $skus[] = [
                'sku' => $product->sku,
                'warehouseId' => (int)$ymWarehouseId,
                'items' => [
                    [
                        'count' => (int)$stock,
                        'type' => 'FIT',
                        'updatedAt' => now()->toIso8601String(),
                    ]
                ]
            ];
        }

        $chunks = array_chunk($skus, 2000);
        foreach ($chunks as $chunk) {
            $this->updateStocks(['skus' => $chunk]);
        }

        return count($skus);
    }

    // --- Legacy compatibility methods ---
    public function getOffers(?string $pageToken = null)
    {
        return $this->getOfferMappings($pageToken);
    }

    public function getPrices(?string $pageToken = null)
    {
        return $this->getBusinessPrices($pageToken);
    }

    /**
     * ORDERS: Get Orders
     *
     * @param  array  $params  status, substatus, from_date, page,
     *                         fake (bool) — только тестовые (true) или только боевые (false),
     *                         include_sandbox (bool) — если true, объединяет боевые и тестовые (для ручной синхронизации).
     */
    public function getOrders(array $params = [])
    {
        $includeSandbox = (bool) ($params['include_sandbox'] ?? false);
        unset($params['include_sandbox']);

        if (array_key_exists('fake', $params)) {
            $fake = (bool) $params['fake'];
            unset($params['fake']);

            return $this->getOrdersPage($params, $fake);
        }

        $real = $this->getOrdersPage($params, false);

        if (! $includeSandbox) {
            return $real;
        }

        $sandbox = $this->getOrdersPage($params, true);

        return $this->mergeOrdersById($real, $sandbox);
    }

    /**
     * @param  array<string, mixed>  $params  status, substatus, from_date, page
     */
    private function getOrdersPage(array $params, bool $fake): array
    {
        $api = new \AppYandexSdk\Api\OrdersApi($this->httpClient, $this->config);

        $status = $params['status'] ?? null;
        $substatus = $params['substatus'] ?? null;
        $fromDate = $params['from_date'] ?? date('d-m-Y', strtotime('-7 days'));
        $page = $params['page'] ?? 1;

        $response = $api->getOrders(
            $this->campaign_id,
            null,
            $status,
            $substatus,
            $fromDate,
            null,
            null,
            null,
            null,
            null,
            null,
            $fake,
            false,
            false,
            false,
            null,
            $page
        );

        $result = json_decode((string) $response, true);

        return $result['orders'] ?? [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $a
     * @param  array<int, array<string, mixed>>  $b
     * @return array<int, array<string, mixed>>
     */
    private function mergeOrdersById(array $a, array $b): array
    {
        $byId = [];
        foreach ($a as $order) {
            $id = data_get($order, 'id');
            if ($id !== null) {
                $byId[(string) $id] = $order;
            }
        }
        foreach ($b as $order) {
            $id = data_get($order, 'id');
            if ($id !== null) {
                $byId[(string) $id] = $order;
            }
        }

        return array_values($byId);
    }

    /**
     * Helper to get new orders (Processing / Started)
     */
    public function getNewOrders()
    {
        return $this->getOrders([
            'status' => 'PROCESSING',
            'substatus' => 'STARTED',
        ]);
    }

    /**
     * ORDERS: Get single order by orderId
     */
    public function getOrder(int|string $orderId, int|string|null $campaignId = null): array
    {
        $api = new \AppYandexSdk\Api\OrdersApi($this->httpClient, $this->config);

        $campaign = $campaignId ?? $this->campaign_id;
        $response = $api->getOrder($campaign, $orderId);
        $result = json_decode((string) $response, true);

        return $result['order'] ?? [];
    }

    /**
     * ORDERS: Get buyer info for an order
     */
    public function getOrderBuyerInfo(int|string $orderId, int|string|null $campaignId = null): array
    {
        $api = new \AppYandexSdk\Api\OrderBusinessInformationApi($this->httpClient, $this->config);

        $campaign = $campaignId ?? $this->campaign_id;
        $response = $api->getOrderBusinessBuyerInfo($campaign, $orderId);
        $result = json_decode((string) $response, true);

        return $result['result'] ?? [];
    }

    /**
     * ORDERS: Provide digital codes to Yandex Market
     */
    public function provideOrderDigitalCodes(array $keys, int|string $campaignId, int|string $orderId)
    {
        $api = new \AppYandexSdk\Api\OrdersApi($this->httpClient, $this->config);

        $items = [];
        foreach ($keys as $keyData) {
            $items[] = new \AppYandexSdk\Model\OrderDigitalItemDTO([
                'id' => (int) $keyData['id'],
                'codes' => $keyData['codes'],
                'slip' => $keyData['slip'] ?? '',
                'activate_till' => $keyData['activate_till'] ?? null,
            ]);
        }

        $request = new \AppYandexSdk\Model\ProvideOrderDigitalCodesRequest(['items' => $items]);

        $response = $api->provideOrderDigitalCodes($campaignId, $orderId, $request);

        return json_decode((string) $response, true);
    }

    /**
     * Helper to get campaign ID
     */
    public function getCampaignId(): int
    {
        return $this->campaign_id;
    }
}
