<?php

namespace App\Services;

use App\Models\Order\Order;
use App\Models\Order\OrderItems;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\Provider;
use App\Models\ProviderProduct;
use App\Models\WildflowCatalog;
use App\Services\Provider\ProviderHub;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StorefrontFulfillmentService
{
    public const FULFILLMENT_INSTANT = 'instant';
    public const FULFILLMENT_PREORDER = 'preorder';

    public function isProviderBacked(Product $product): bool
    {
        return filled($product->wildflow_catalog_sku) || filled($product->provider_id);
    }

    /**
     * Check checkout availability without reserving stock or creating a provider order.
     *
     * @return array<string, mixed>
     */
    public function checkoutAvailability(Product $product, int $quantity = 1): array
    {
        $quantity = max(1, min($quantity, 5));
        $shop = $product->shop;
        $localAvailable = $this->localStockCount($product, $shop);

        if ($localAvailable >= $quantity) {
            return $this->availabilityPayload(
                status: 'available',
                reason: $this->isProviderBacked($product)
                    ? 'У продавца есть подготовленный entitlement. После оплаты сейф получит реальный код через защищенный обмен с поставщиком.'
                    : 'Код есть в локальном стоке и будет выдан сразу после оплаты.',
                source: $this->isProviderBacked($product) ? 'seller_entitlements' : 'local_vouchers',
                quantity: $quantity,
                localAvailable: $localAvailable,
                preOrderSupported: false,
                providerAvailability: null,
            );
        }

        if (! $this->isProviderBacked($product)) {
            return $this->availabilityPayload(
                status: 'available',
                reason: 'Товар будет подготовлен внутренней выдачей сразу после оплаты.',
                source: 'local_generated',
                quantity: $quantity,
                localAvailable: $localAvailable,
                preOrderSupported: false,
                providerAvailability: null,
            );
        }

        $providerAvailability = $this->providerAvailabilityForProduct($product, $quantity);
        if ($this->providerAvailabilityIsInstant($providerAvailability)) {
            return $this->availabilityPayload(
                status: 'available',
                reason: 'Поставщик подтвердил наличие. После оплаты выдача запросит код через защищенный обмен с провайдером.',
                source: 'provider_inventory',
                quantity: $quantity,
                localAvailable: $localAvailable,
                preOrderSupported: $this->providerAvailabilitySupportsPreOrder($providerAvailability),
                providerAvailability: $providerAvailability,
            );
        }

        return $this->availabilityPayload(
            status: 'unavailable',
            reason: $this->providerUnavailableReason($providerAvailability),
            source: 'provider_inventory',
            quantity: $quantity,
            localAvailable: $localAvailable,
            preOrderSupported: $this->providerAvailabilitySupportsPreOrder($providerAvailability),
            providerAvailability: $providerAvailability,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function providerAvailabilityForProduct(Product $product, int $quantity): ?array
    {
        $catalogSku = $this->catalogSkuForProduct($product);
        $catalog = $this->wildflowCatalogForSku($catalogSku);
        $providerProduct = $this->providerProductForProduct($product, $catalogSku, $catalog);
        $provider = $this->providerFor($product, $providerProduct, $catalogSku);

        if (! $provider || ! $this->mayCallProvider($provider)) {
            return null;
        }

        try {
            $wildflow = app()->bound(WildflowService::class)
                ? app(WildflowService::class)
                : app()->makeWith(WildflowService::class, ['providerModel' => $provider]);

            return $wildflow->checkAvailability(
                service_sku: $this->serviceSku($catalog, $providerProduct, $catalogSku),
                quantity: $quantity,
                price: $this->availabilityPrice($catalog, $providerProduct, $product),
                provider: $this->upstreamProvider($provider),
                terminalId: $this->providerTerminalId($provider, $product->shop),
            );
        } catch (\Throwable $e) {
            Log::warning('Storefront provider availability check failed', [
                'product_id' => $product->id,
                'provider_id' => $provider->id,
                'sku' => $catalogSku,
                'message' => $e->getMessage(),
            ]);

            return [
                'available' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function assertCheckoutAvailability(
        Product $product,
        int $quantity,
        ?string $requestedFulfillmentMode,
        bool $preorderAcknowledged = false
    ): array {
        $availability = $this->checkoutAvailability($product, $quantity);
        $status = (string) ($availability['status'] ?? 'unavailable');
        $requestedMode = strtolower(trim((string) $requestedFulfillmentMode));

        if ($requestedMode === self::FULFILLMENT_PREORDER) {
            throw ValidationException::withMessages([
                'fulfillment_mode' => 'Предзаказ доступен только для закупки продавца. Для покупателя оплата возможна после пополнения стока.',
            ]);
        }

        if ($status === 'unavailable') {
            throw ValidationException::withMessages([
                'availability' => $availability['reason'] ?? 'Товар временно недоступен для оплаты.',
            ]);
        }

        return $availability + [
            'fulfillment_mode' => self::FULFILLMENT_INSTANT,
            'preorder_acknowledged' => false,
        ];
    }

    public function markProviderPending(
        Order $order,
        OrderItems $item,
        Product $product,
        string $fulfillmentMode = self::FULFILLMENT_INSTANT
    ): void
    {
        $catalogSku = $this->catalogSkuForProduct($product);
        $catalog = $this->wildflowCatalogForSku($catalogSku);
        $providerProduct = $this->providerProductForProduct($product, $catalogSku, $catalog);
        $provider = $this->providerFor($product, $providerProduct, $catalogSku);
        $fulfillmentMode = $this->normalizeFulfillmentMode($fulfillmentMode);
        $pendingStatus = $this->pendingSafeStatus($fulfillmentMode);

        $clientInfo = $item->client_info ?? [];
        data_set($clientInfo, 'provider_redemption.status', $pendingStatus);
        data_set($clientInfo, 'provider_redemption.fulfillment_mode', $fulfillmentMode);
        data_set($clientInfo, 'provider_redemption.pre_order', $fulfillmentMode === self::FULFILLMENT_PREORDER);
        data_set($clientInfo, 'provider_redemption.catalog_sku', $catalogSku);
        data_set($clientInfo, 'provider_redemption.provider_product_id', $providerProduct?->id);
        data_set($clientInfo, 'provider_redemption.provider_id', $provider?->id);
        data_set($clientInfo, 'provider_redemption.provider_type', $provider?->type);

        $item->forceFill([
            'purchase_status' => 'pending',
            'purchase_error' => null,
            'client_info' => $clientInfo,
        ])->save();

        $info = $order->info ?? [];
        data_set($info, 'fulfillment_mode', $fulfillmentMode);
        data_set($info, 'order_safe.source', 'provider');
        data_set($info, 'order_safe.status', $pendingStatus);
        data_set($info, 'order_safe.fulfillment_mode', $fulfillmentMode);
        data_set($info, 'order_safe.pre_order', $fulfillmentMode === self::FULFILLMENT_PREORDER);
        data_set($info, 'order_safe.catalog_sku', $catalogSku);
        data_set($info, 'order_safe.provider_product_id', $providerProduct?->id);
        data_set($info, 'order_safe.provider_id', $provider?->id);
        data_set($info, 'order_safe.provider_type', $provider?->type);

        $order->forceFill([
            'status' => 'PROCESSING',
            'progress_id' => 2,
            'info' => $info,
        ])->save();
    }

    /**
     * Fulfill a paid storefront order that was marked provider-backed.
     *
     * This method is idempotent by order item: once a provider order id exists it
     * only polls for cards, and once encrypted codes exist it returns ready.
     *
     * @return array{status:string,codes:int,error?:string}
     */
    public function fulfillProviderOrder(Order $order): array
    {
        $order->loadMissing(['items', 'shop.legalEntity']);

        if ((string) data_get($order->info, 'order_safe.source') !== 'provider') {
            return ['status' => 'not_provider', 'codes' => 0];
        }

        $item = $order->items->first();
        if (! $item) {
            return $this->failOrder($order, null, 'В заказе нет позиции для выдачи.');
        }

        $existingCodes = $this->codesFromItem($item);
        if ($existingCodes !== []) {
            $this->markOrderReady($order, $item, $existingCodes, $item->provider_order_id);

            return ['status' => 'provider_code_ready', 'codes' => count($existingCodes)];
        }

        if ($item->purchase_status === 'failed' || (string) data_get($order->info, 'order_safe.status') === 'provider_redeem_failed') {
            return [
                'status' => 'provider_redeem_failed',
                'codes' => 0,
                'error' => (string) ($item->purchase_error ?: data_get($order->info, 'order_safe.failure', 'Provider redemption failed.')),
            ];
        }

        $claimed = DB::transaction(function () use ($item) {
            /** @var OrderItems $locked */
            $locked = OrderItems::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();

            if ($this->codesFromItem($locked) !== []) {
                return null;
            }

            if ($locked->purchase_status === 'processing' && blank($locked->provider_order_id)) {
                return false;
            }

            if ($locked->purchase_status === 'pending') {
                $locked->update(['purchase_status' => 'processing', 'purchase_error' => null]);
            }

            return $locked->refresh();
        });

        if ($claimed === null) {
            $order->refresh()->loadMissing('items');
            $readyCodes = $this->codesFromItem($order->items->first());

            return ['status' => 'provider_code_ready', 'codes' => count($readyCodes)];
        }

        if ($claimed === false) {
            return [
                'status' => $this->pendingSafeStatus($this->fulfillmentMode($order, $item)),
                'codes' => 0,
            ];
        }

        /** @var OrderItems $item */
        $item = $claimed;
        $product = $this->productForItem($order, $item);
        if (! $product) {
            return $this->failOrder($order, $item, 'Не найден товар для провайдерской выдачи.');
        }

        $catalogSku = $this->catalogSkuForProduct($product);
        $catalog = $this->wildflowCatalogForSku($catalogSku);
        $providerProduct = $this->providerProductForProduct($product, $catalogSku, $catalog);
        if (! $catalog && ! $providerProduct) {
            return $this->failOrder($order, $item, "Не найден продукт провайдера для SKU {$catalogSku}.");
        }

        $provider = $this->providerFor($product, $providerProduct, $catalogSku);
        if (! $provider) {
            return $this->failOrder($order, $item, 'Не найден провайдер для выдачи товара.');
        }

        if (! $this->mayCallProvider($provider)) {
            return $this->failOrder(
                $order,
                $item,
                'Live provider redemption is disabled for storefront safes. Configure a sandbox/local provider or enable MEANLY_ALLOW_LIVE_PROVIDER_REDEMPTION.'
            );
        }

        try {
            $driver = app(ProviderHub::class)->forProvider($provider);
            $reference = $item->provider_order_id ?: $item->providerReference();
            $serviceSku = $this->serviceSku($catalog, $providerProduct, $catalogSku);
            $nominal = $this->nominalAmount($item, $catalog, $providerProduct);
            $shop = $order->shop;

            if (blank($item->provider_order_id)) {
                app(LedgerService::class)->record($shop, 'PROVIDER_ORDER_START', $item, [
                    'provider' => $provider->type,
                    'sku' => $catalogSku,
                    'service_sku' => $serviceSku,
                    'provider_product_id' => $providerProduct?->id,
                    'reference' => $reference,
                    'context' => 'meanly_storefront_safe',
                ]);

                $externalOrderId = $driver->createOrder(
                    sku: $serviceSku,
                    reference: $reference,
                    price: $nominal,
                    quantity: (int) $item->count,
                    meta: [
                        'email' => data_get($item->client_info, 'delivery_email') ?: data_get($item->client_info, 'email'),
                        'pre_order' => $this->fulfillmentMode($order, $item) === self::FULFILLMENT_PREORDER,
                        'fulfillment_mode' => $this->fulfillmentMode($order, $item),
                        'seller_id' => $shop ? (string) $shop->id : null,
                        'seller_name' => $shop?->name,
                        'terminal_id' => $shop ? (string) $shop->legal_entity_id : null,
                        'storefront_order_id' => $order->order_id,
                    ]
                );

                $item->forceFill(['provider_order_id' => $externalOrderId ?: $reference])->save();

                if (method_exists($driver, 'lastOrderResponse')) {
                    $this->rememberProviderOrderResponse($item->fresh(), $providerProduct, $driver->lastOrderResponse());
                }
            }

            $externalOrderId = (string) ($item->fresh()->provider_order_id ?: $reference);
            try {
                $codes = array_values(array_filter($driver->getCodes($externalOrderId), fn ($code) => filled($code)));
            } catch (\Throwable $e) {
                $fulfillmentMode = $this->fulfillmentMode($order, $item);
                if ($fulfillmentMode === self::FULFILLMENT_PREORDER || $this->looksLikeProviderCardsPending($e->getMessage())) {
                    Log::warning('Storefront provider code poll deferred', [
                        'order_id' => $order->id,
                        'order_item_id' => $item->id,
                        'provider_order_id' => $externalOrderId,
                        'fulfillment_mode' => $fulfillmentMode,
                        'message' => $e->getMessage(),
                    ]);

                    $this->markOrderPending($order, $item->fresh(), $provider, $externalOrderId);

                    return ['status' => $this->pendingSafeStatus($fulfillmentMode), 'codes' => 0];
                }

                throw $e;
            }

            if ($codes === []) {
                $this->markOrderPending($order, $item->fresh(), $provider, $externalOrderId);

                return [
                    'status' => $this->pendingSafeStatus($this->fulfillmentMode($order, $item)),
                    'codes' => 0,
                ];
            }

            $item = $item->fresh();
            $this->storeProviderCodes($order, $item, $provider, $catalog, $providerProduct, $codes, $externalOrderId);

            app(LedgerService::class)->record($shop, 'PROVIDER_ORDER_SUCCESS', $item, [
                'provider' => $provider->type,
                'external_id' => $externalOrderId,
                'sku' => $catalogSku,
                'provider_product_id' => $providerProduct?->id,
                'codes_count' => count($codes),
                'context' => 'meanly_storefront_safe',
            ]);

            return ['status' => 'provider_code_ready', 'codes' => count($codes)];
        } catch (\Throwable $e) {
            report($e);

            app(LedgerService::class)->record($order->shop, 'PROVIDER_ORDER_FAILED', $item, [
                'provider' => $provider->type,
                'message' => $e->getMessage(),
                'sku' => $catalogSku,
                'context' => 'meanly_storefront_safe',
            ]);

            return $this->failOrder($order, $item, $e->getMessage());
        }
    }

    /**
     * Poll only an existing provider order. Used by the safe status endpoint.
     */
    public function pollProviderOrder(Order $order): void
    {
        $order->loadMissing(['items', 'shop']);
        $item = $order->items->first();

        if (! $item || blank($item->provider_order_id) || $this->codesFromItem($item) !== []) {
            return;
        }

        $product = $this->productForItem($order, $item);
        if (! $product) {
            return;
        }

        $catalogSku = $this->catalogSkuForProduct($product);
        $catalog = $this->wildflowCatalogForSku($catalogSku);
        $providerProduct = $this->providerProductForProduct($product, $catalogSku, $catalog);
        $provider = $this->providerFor($product, $providerProduct, $catalogSku);

        if ((! $catalog && ! $providerProduct) || ! $provider || ! $this->mayCallProvider($provider)) {
            return;
        }

        try {
            $codes = app(ProviderHub::class)
                ->forProvider($provider)
                ->getCodes((string) $item->provider_order_id);
            $codes = array_values(array_filter($codes, fn ($code) => filled($code)));

            if ($codes !== []) {
                $this->storeProviderCodes($order, $item, $provider, $catalog, $providerProduct, $codes, (string) $item->provider_order_id);
            }
        } catch (\Throwable $e) {
            Log::warning('Storefront safe provider poll failed', [
                'order_id' => $order->id,
                'order_item_id' => $item->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array<int, string>
     */
    public function codesFromItem(?OrderItems $item): array
    {
        if (! $item) {
            return [];
        }

        $codes = collect((array) data_get($item->client_info, 'provider_redemption.codes', []))
            ->map(fn ($code) => trim((string) $code))
            ->filter()
            ->values()
            ->all();

        if ($codes !== []) {
            return $codes;
        }

        if (is_array(data_get($item->client_info, 'provider_redemption'))) {
            return [];
        }

        $code = trim((string) $item->original_code);

        return $code === '' ? [] : [$code];
    }

    private function productForItem(Order $order, OrderItems $item): ?Product
    {
        return Product::queryByOfferSku((string) $item->sku)
            ->where('shop_id', $order->shop_id)
            ->first();
    }

    private function catalogSkuForProduct(Product $product): string
    {
        return (string) ($product->wildflow_catalog_sku ?: $product->sku);
    }

    private function providerProductForProduct(Product $product, string $catalogSku, ?WildflowCatalog $catalog = null): ?ProviderProduct
    {
        $providerProductId = data_get($product->data, 'provider_product_id')
            ?? data_get($product->data, 'source_provider_product_id')
            ?? data_get($product->params, 'provider_product_id')
            ?? data_get($product->params, 'source_provider_product_id');

        if ($providerProductId) {
            $direct = ProviderProduct::query()->whereKey($providerProductId)->first();
            if ($direct) {
                return $direct;
            }
        }

        $candidateSkus = collect([
            $catalogSku,
            $product->wildflow_catalog_sku,
            $product->sku,
            $catalog?->service_sku,
            data_get($product->params, 'wf_provider_sku'),
        ])
            ->map(fn ($sku) => trim((string) $sku))
            ->filter()
            ->unique()
            ->values();

        if ($candidateSkus->isEmpty()) {
            return null;
        }

        $vault = app(VaultTransitService::class);
        $blindIndexes = $candidateSkus
            ->map(fn (string $sku) => $vault->computeBlindIndex($sku))
            ->all();

        $query = ProviderProduct::query()
            ->where(function ($q) use ($blindIndexes) {
                $q->whereIn('market_sku_bidx', $blindIndexes)
                    ->orWhereIn('sku_bidx', $blindIndexes);
            });

        if ($product->provider_id) {
            $query->orderByRaw('case when provider_id = '.(int) $product->provider_id.' then 0 else 1 end');
        }

        return $query->first();
    }

    private function wildflowCatalogForSku(string $catalogSku): ?WildflowCatalog
    {
        return WildflowCatalog::query()->where('sku', $catalogSku)->first();
    }

    private function providerFor(Product $product, ?ProviderProduct $providerProduct, string $catalogSku): ?Provider
    {
        if ($product->provider) {
            return $product->provider;
        }

        if ($providerProduct?->provider) {
            return $providerProduct->provider;
        }

        $catalog = WildflowCatalog::query()->where('sku', $catalogSku)->first();
        if ($catalog?->provider) {
            return $catalog->provider;
        }

        return Provider::query()
            ->whereIn('type', ['wildflow-sandbox', 'wildflow'])
            ->where('is_active', true)
            ->orderByRaw("case when type = 'wildflow-sandbox' then 0 else 1 end")
            ->first();
    }

    private function serviceSku(?WildflowCatalog $catalog, ?ProviderProduct $providerProduct, string $catalogSku): string
    {
        $serviceSku = data_get($catalog?->data, 'service_sku')
            ?? data_get($catalog?->data, 'data.sku')
            ?? data_get($catalog?->data, 'data.product.sku')
            ?? data_get($catalog?->data, 'product.sku')
            ?? $catalog?->service_sku
            ?? data_get($providerProduct?->data, 'service_sku')
            ?? data_get($providerProduct?->data, 'data.sku')
            ?? data_get($providerProduct?->data, 'data.product.sku')
            ?? data_get($providerProduct?->data, 'product.sku')
            ?? $providerProduct?->sku
            ?? $catalogSku;

        return (string) $serviceSku;
    }

    private function nominalAmount(OrderItems $item, ?WildflowCatalog $catalog, ?ProviderProduct $providerProduct): float
    {
        return (float) (
            $item->nominal_amount
            ?: $catalog?->retail_price
            ?: $providerProduct?->retail_price
            ?: data_get($catalog?->data, 'data.price')
            ?: data_get($providerProduct?->data, 'data.price')
            ?: data_get($providerProduct?->data, 'price')
            ?: 0
        );
    }

    private function preOrder(?WildflowCatalog $catalog, ?ProviderProduct $providerProduct, ?Product $product = null): bool
    {
        return $this->firstBool(
            data_get($catalog?->data, 'provider_purchase.pre_order'),
            data_get($catalog?->data, '_wildflow_purchase.pre_order'),
            data_get($catalog?->data, 'purchase.pre_order'),
            data_get($catalog?->data, 'data.pre_order'),
            data_get($catalog?->data, 'pre_order'),
            data_get($catalog?->data, 'product.pre_order'),
            data_get($catalog?->data, 'data.product.pre_order'),
            data_get($catalog?->data, 'raw_data.pre_order'),
            data_get($catalog?->data, 'raw_data.product.pre_order'),
            data_get($catalog?->data, 'data.raw_data.pre_order'),
            data_get($catalog?->data, 'data.raw_data.product.pre_order'),
            data_get($providerProduct?->data, 'provider_purchase.pre_order'),
            data_get($providerProduct?->data, '_wildflow_purchase.pre_order'),
            data_get($providerProduct?->data, 'purchase.pre_order'),
            data_get($providerProduct?->data, 'data.pre_order'),
            data_get($providerProduct?->data, 'pre_order'),
            data_get($providerProduct?->data, 'product.pre_order'),
            data_get($providerProduct?->data, 'data.product.pre_order'),
            data_get($providerProduct?->data, 'raw_data.pre_order'),
            data_get($providerProduct?->data, 'raw_data.product.pre_order'),
            data_get($providerProduct?->data, 'data.raw_data.pre_order'),
            data_get($providerProduct?->data, 'data.raw_data.product.pre_order'),
            data_get($product?->data, 'provider_purchase.pre_order'),
            data_get($product?->data, '_wildflow_purchase.pre_order'),
            data_get($product?->data, 'purchase.pre_order'),
            data_get($product?->data, 'pre_order'),
            data_get($product?->data, 'product.pre_order'),
            data_get($product?->data, 'data.product.pre_order'),
            data_get($product?->data, 'source_provider.pre_order'),
            data_get($product?->data, 'source_provider.product.pre_order'),
            false
        );
    }

    private function localStockCount(Product $product, $shop): int
    {
        if (! $shop) {
            return 0;
        }

        $skuBidx = app(VaultTransitService::class)->computeBlindIndex((string) $product->sku);

        return ProductInventory::query()
            ->where('shop_id', $shop->id)
            ->where('sku_bidx', $skuBidx)
            ->where('is_used', false)
            ->where('status', 'available')
            ->whereNull('order_item_id')
            ->count();
    }

    /**
     * @return array<string, mixed>
     */
    private function availabilityPayload(
        string $status,
        string $reason,
        string $source,
        int $quantity,
        int $localAvailable,
        bool $preOrderSupported,
        ?array $providerAvailability
    ): array {
        $instantAvailable = $status === 'available';
        $preorderAvailable = $status === 'preorder_available';

        return [
            'status' => $status,
            'available' => $instantAvailable,
            'instant_available' => $instantAvailable,
            'preorder_available' => $preorderAvailable,
            'pre_order_supported' => $preOrderSupported,
            'recommended_fulfillment_mode' => $preorderAvailable ? self::FULFILLMENT_PREORDER : self::FULFILLMENT_INSTANT,
            'reason' => $reason,
            'source' => $source,
            'quantity' => $quantity,
            'local_available' => $localAvailable,
            'provider' => $providerAvailability,
        ];
    }

    private function providerAvailabilityIsInstant(?array $providerAvailability): bool
    {
        if (! is_array($providerAvailability)) {
            return false;
        }

        return (bool) (
            $providerAvailability['available']
            ?? data_get($providerAvailability, 'availability')
            ?? data_get($providerAvailability, 'raw.availability.available')
            ?? data_get($providerAvailability, 'raw.availability.availability')
            ?? data_get($providerAvailability, 'raw.availability.in_stock')
            ?? false
        );
    }

    private function providerAvailabilitySupportsPreOrder(?array $providerAvailability): bool
    {
        if (! is_array($providerAvailability)) {
            return false;
        }

        return $this->firstBool(
            data_get($providerAvailability, 'pre_order_supported'),
            data_get($providerAvailability, 'preorder_supported'),
            data_get($providerAvailability, 'pre_order'),
            data_get($providerAvailability, 'raw.pre_order_supported'),
            data_get($providerAvailability, 'raw.preorder_supported'),
            data_get($providerAvailability, 'raw.pre_order'),
            data_get($providerAvailability, 'raw.availability.pre_order_supported'),
            data_get($providerAvailability, 'raw.availability.preorder_supported'),
            data_get($providerAvailability, 'raw.availability.pre_order'),
            data_get($providerAvailability, 'raw.catalog.pre_order'),
            data_get($providerAvailability, 'raw.catalog.product.pre_order'),
            data_get($providerAvailability, 'raw.retailer.pre_order'),
            data_get($providerAvailability, 'raw.retailer.product.pre_order'),
            false
        );
    }

    private function providerUnavailableReason(?array $providerAvailability): string
    {
        $error = (string) (
            data_get($providerAvailability, 'error')
            ?? data_get($providerAvailability, 'raw.error')
            ?? data_get($providerAvailability, 'raw.message')
            ?? data_get($providerAvailability, 'raw.availability.detail')
            ?? ''
        );

        if ($error !== '' && $this->looksLikeProviderAuthFailure($error)) {
            return 'Поставщик не подтвердил наличие из-за ошибки авторизации upstream API. Проверьте credentials перед запуском продаж.';
        }

        if ($this->providerAvailabilitySupportsPreOrder($providerAvailability)) {
            return 'Поставщик поддерживает предзаказ, но моментальная выдача сейчас недоступна.';
        }

        return 'Поставщик не подтвердил наличие для моментальной выдачи.';
    }

    private function availabilityPrice(?WildflowCatalog $catalog, ?ProviderProduct $providerProduct, Product $product): ?float
    {
        $price = $catalog?->retail_price
            ?: $providerProduct?->retail_price
            ?: data_get($catalog?->data, 'data.price')
            ?: data_get($catalog?->data, 'price')
            ?: data_get($providerProduct?->data, 'data.price')
            ?: data_get($providerProduct?->data, 'price')
            ?: ((float) ($product->price_rub ?? 0) / 100);

        return $price > 0 ? (float) $price : null;
    }

    private function providerTerminalId(Provider $provider, $shop): ?string
    {
        $terminalId = data_get($provider->credentials, 'terminal_id')
            ?? data_get($provider->credentials, 'terminal')
            ?? data_get($provider->settings, 'terminal_id')
            ?? data_get($provider->settings, 'terminal')
            ?? $shop?->legal_entity_id;

        return filled($terminalId) ? (string) $terminalId : null;
    }

    private function upstreamProvider(Provider $provider): string
    {
        return (string) (
            data_get($provider->settings, 'upstream_provider')
            ?? data_get($provider->settings, 'provider')
            ?? data_get($provider->credentials, 'upstream_provider')
            ?? data_get($provider->credentials, 'provider')
            ?? ($provider->type === 'wildflow-sandbox' ? 'ezpin-sandbox' : null)
            ?? 'ezpin'
        );
    }

    private function normalizeFulfillmentMode(?string $mode): string
    {
        return strtolower(trim((string) $mode)) === self::FULFILLMENT_PREORDER
            ? self::FULFILLMENT_PREORDER
            : self::FULFILLMENT_INSTANT;
    }

    private function fulfillmentMode(Order $order, ?OrderItems $item = null): string
    {
        return $this->normalizeFulfillmentMode(
            data_get($item?->client_info, 'provider_redemption.fulfillment_mode')
            ?? data_get($order->info, 'order_safe.fulfillment_mode')
            ?? data_get($order->info, 'fulfillment_mode')
        );
    }

    private function pendingSafeStatus(string $fulfillmentMode): string
    {
        return $this->normalizeFulfillmentMode($fulfillmentMode) === self::FULFILLMENT_PREORDER
            ? 'preorder_pending'
            : 'provider_redeem_pending';
    }

    private function looksLikeProviderAuthFailure(string $error): bool
    {
        $error = strtolower($error);

        return str_contains($error, '401')
            || str_contains($error, 'unauthorized')
            || str_contains($error, 'token_not_valid')
            || str_contains($error, 'signature has expi');
    }

    private function looksLikeProviderCardsPending(string $error): bool
    {
        $error = strtolower($error);

        return str_contains($error, 'order is not ready')
            || str_contains($error, 'order_not_ready')
            || str_contains($error, 'digital cards not found')
            || str_contains($error, 'cards not found')
            || str_contains($error, 'request is in process')
            || str_contains($error, 'request_pending')
            || str_contains($error, 'request_in_process')
            || str_contains($error, '"code":609')
            || str_contains($error, '"code":"609"')
            || str_contains($error, '"code":710')
            || str_contains($error, '"code":"710"')
            || str_contains($error, '"code":823')
            || str_contains($error, '"code":"823"');
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

    private function mayCallProvider(Provider $provider): bool
    {
        if (app()->environment('testing') || (bool) config('meanly_storefront.provider_fulfillment.allow_live_redemption', false)) {
            return true;
        }

        $type = strtolower((string) $provider->type);
        if (str_contains($type, 'sandbox') || str_contains($type, 'local')) {
            return true;
        }

        $baseUrl = (string) (data_get($provider->credentials, 'base_url') ?: config('services.wildflow.base_url', ''));
        $host = strtolower((string) parse_url($baseUrl, PHP_URL_HOST));

        return in_array($host, ['localhost', '127.0.0.1', '0.0.0.0', 'api.wildflow.test'], true)
            || str_ends_with($host, '.test')
            || str_ends_with($host, '.local');
    }

    /**
     * @param array<int, string> $codes
     */
    private function storeProviderCodes(
        Order $order,
        OrderItems $item,
        Provider $provider,
        ?WildflowCatalog $catalog,
        ?ProviderProduct $providerProduct,
        array $codes,
        string $externalOrderId
    ): void {
        DB::transaction(function () use ($order, $item, $provider, $catalog, $providerProduct, $codes, $externalOrderId) {
            $clientInfo = $item->client_info ?? [];
            data_set($clientInfo, 'provider_redemption.status', 'provider_code_ready');
            data_set($clientInfo, 'provider_redemption.codes', $codes);
            data_set($clientInfo, 'provider_redemption.provider_order_id', $externalOrderId);
            data_set($clientInfo, 'provider_redemption.provider_id', $provider->id);
            data_set($clientInfo, 'provider_redemption.provider_type', $provider->type);
            data_set($clientInfo, 'provider_redemption.provider_product_id', $providerProduct?->id);
            data_set($clientInfo, 'provider_redemption.activation_url', $this->activationUrl($catalog, $providerProduct));
            data_set($clientInfo, 'provider_redemption.redeemed_at', now()->toJSON());

            $item->forceFill([
                'provider_order_id' => $externalOrderId,
                'purchase_status' => 'success',
                'purchase_error' => null,
                'original_code' => $codes[0],
                'client_info' => $clientInfo,
            ])->save();

            $this->markOrderReady($order, $item, $codes, $externalOrderId);
            $this->markLocalEntitlements($item, 'exchanged');
        });
    }

    private function rememberProviderOrderResponse(?OrderItems $item, ?ProviderProduct $providerProduct, mixed $response): void
    {
        if (! $item || $response === null) {
            return;
        }

        $clientInfo = $item->client_info ?? [];
        data_set($clientInfo, 'provider_redemption.provider_product_id', $providerProduct?->id);
        data_set($clientInfo, 'provider_redemption.aggregator_order.status', data_get($response, 'status_text', data_get($response, 'status')));
        data_set($clientInfo, 'provider_redemption.aggregator_order.order_id', data_get($response, 'order_id', data_get($response, 'referenceCode')));
        data_set($clientInfo, 'provider_redemption.aggregator_order.raw_response', $response);

        $item->forceFill(['client_info' => $clientInfo])->save();
    }

    private function activationUrl(?WildflowCatalog $catalog, ?ProviderProduct $providerProduct): ?string
    {
        return $catalog?->activation_url ?: $providerProduct?->activation_url;
    }

    /**
     * @param array<int, string> $codes
     */
    private function markOrderReady(Order $order, OrderItems $item, array $codes, ?string $externalOrderId): void
    {
        $info = $order->info ?? [];
        data_set($info, 'order_safe.source', 'provider');
        data_set($info, 'order_safe.status', 'provider_code_ready');
        data_set($info, 'order_safe.provider_order_id', $externalOrderId);
        data_set($info, 'order_safe.codes_count', count($codes));
        data_set($info, 'order_safe.ready_at', data_get($info, 'order_safe.ready_at') ?: now()->toJSON());
        data_set($info, 'order_safe.refund_needed', false);

        $order->forceFill([
            'status' => 'COMPLETED',
            'progress_id' => 4,
            'is_problem' => false,
            'info' => $info,
        ])->save();
    }

    private function markOrderPending(Order $order, OrderItems $item, Provider $provider, string $externalOrderId): void
    {
        $fulfillmentMode = $this->fulfillmentMode($order, $item);
        $pendingStatus = $this->pendingSafeStatus($fulfillmentMode);
        $clientInfo = $item->client_info ?? [];
        data_set($clientInfo, 'provider_redemption.status', $pendingStatus);
        data_set($clientInfo, 'provider_redemption.fulfillment_mode', $fulfillmentMode);
        data_set($clientInfo, 'provider_redemption.pre_order', $fulfillmentMode === self::FULFILLMENT_PREORDER);
        data_set($clientInfo, 'provider_redemption.provider_order_id', $externalOrderId);
        data_set($clientInfo, 'provider_redemption.provider_id', $provider->id);
        data_set($clientInfo, 'provider_redemption.provider_type', $provider->type);

        $item->forceFill([
            'purchase_status' => 'processing',
            'purchase_error' => null,
            'client_info' => $clientInfo,
        ])->save();

        $info = $order->info ?? [];
        data_set($info, 'fulfillment_mode', $fulfillmentMode);
        data_set($info, 'order_safe.source', 'provider');
        data_set($info, 'order_safe.status', $pendingStatus);
        data_set($info, 'order_safe.fulfillment_mode', $fulfillmentMode);
        data_set($info, 'order_safe.pre_order', $fulfillmentMode === self::FULFILLMENT_PREORDER);
        data_set($info, 'order_safe.provider_order_id', $externalOrderId);
        data_set($info, 'order_safe.provider_id', $provider->id);
        data_set($info, 'order_safe.provider_type', $provider->type);

        $order->forceFill(['status' => 'PROCESSING', 'progress_id' => 2, 'info' => $info])->save();
    }

    /**
     * @return array{status:string,codes:int,error:string}
     */
    private function failOrder(Order $order, ?OrderItems $item, string $message): array
    {
        $humanMessage = Str::limit($message, 500, '...');
        $buyerMessage = 'Поставщик не завершил выдачу кода. Поддержка проверит заказ или оформит возврат.';

        DB::transaction(function () use ($order, $item, $humanMessage, $buyerMessage) {
            if ($item) {
                $clientInfo = $item->client_info ?? [];
                data_set($clientInfo, 'provider_redemption.status', 'provider_redeem_failed');
                data_set($clientInfo, 'provider_redemption.failed_at', now()->toJSON());
                data_set($clientInfo, 'provider_redemption.refund_needed', true);
                data_set($clientInfo, 'provider_redemption.failure.internal_reason', $humanMessage);
                data_set($clientInfo, 'provider_redemption.failure.buyer_reason', $buyerMessage);

                $item->forceFill([
                    'purchase_status' => 'failed',
                    'purchase_error' => $humanMessage,
                    'client_info' => $clientInfo,
                ])->save();

                $this->markLocalEntitlements($item, 'exchange_failed');
            }

            $info = $order->info ?? [];
            data_set($info, 'order_safe.source', 'provider');
            data_set($info, 'order_safe.status', 'provider_redeem_failed');
            data_set($info, 'order_safe.failed_at', now()->toJSON());
            data_set($info, 'order_safe.refund_needed', true);
            data_set($info, 'order_safe.failure', $buyerMessage);
            data_set($info, 'order_safe.failure_internal', $humanMessage);

            $order->forceFill([
                'is_problem' => true,
                'info' => $info,
            ])->save();
        });

        return ['status' => 'provider_redeem_failed', 'codes' => 0, 'error' => $humanMessage];
    }

    private function markLocalEntitlements(OrderItems $item, string $status): void
    {
        ProductInventory::query()
            ->where('order_item_id', $item->id)
            ->whereIn('status', ['reserved', 'sold', 'exchange_failed'])
            ->update(['status' => $status, 'is_used' => true]);

        $clientInfo = $item->client_info ?? [];
        if (is_array(data_get($clientInfo, 'local_entitlement'))) {
            data_set($clientInfo, 'local_entitlement.status', $status);
            data_set($clientInfo, 'local_entitlement.updated_at', now()->toJSON());
            $item->forceFill(['client_info' => $clientInfo])->save();
        }
    }
}
