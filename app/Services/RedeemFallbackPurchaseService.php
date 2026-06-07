<?php

namespace App\Services;

use App\Models\Order\OrderItems;
use App\Models\Provider;
use App\Models\WildflowCatalog;
use App\Services\Provider\ProviderHub;
use Illuminate\Support\Facades\Log;

/**
 * Вторая попытка закупа при redeem: отдельный Wildflow-провайдер (другой api_key в providers).
 */
class RedeemFallbackPurchaseService
{
    /**
     * @return string|null card_number при успехе
     */
    public function tryAlternateWildflowAfterPrimaryFailure(
        OrderItems $order_item,
        WildflowCatalog $catalog,
        string $contextMessage,
    ): ?string {
        $providerId = config('redeem.fallback_wildflow_provider_id');
        if (! $providerId) {
            Log::info('Redeem: резервный Wildflow не настроен (redeem.fallback_wildflow_provider_id)', [
                'order_item_id' => $order_item->id,
            ]);

            return null;
        }

        $provider = Provider::query()->find((int) $providerId);
        if (! $provider || empty(data_get($provider->credentials, 'api_key'))) {
            Log::warning('Redeem: резервный провайдер не найден или без api_key', ['provider_id' => $providerId]);

            return null;
        }

        $service_sku = $this->serviceSku($catalog);
        $service_price = data_get($catalog->data, 'data.price')
            ?? data_get($catalog->data, 'price')
            ?? $catalog->retail_price;
        if (! $service_sku || $service_price === null) {
            return null;
        }

        $ref = $order_item->providerReference() . '-fb1';
        $shop = $order_item->order?->shop;

        // 🛡️ Sovereign Ledger: Record the FALLBACK TRIGGER
        if ($shop) {
            app(\App\Services\LedgerService::class)->record($shop, 'PROVIDER_FALLBACK_START', $order_item, [
                'original_context' => $contextMessage,
                'fallback_provider_id' => $providerId,
                'provider_type' => $provider->type,
            ]);
        }

        try {
            $hub = app(ProviderHub::class);
            $driver = $hub->forProvider($provider);

            if ($shop) {
                app(\App\Services\LedgerService::class)->record($shop, 'PROVIDER_ORDER_START', $order_item, [
                    'provider' => $provider->type,
                    'reference' => $ref,
                    'is_fallback' => true,
                ]);
            }

            // 🚀 JIT MULTI-TENANCY: Pass seller info for auto-registration on Aggregator!
            $externalOrderId = $driver->createOrder(
                sku: $service_sku,
                reference: $ref,
                price: (float) $service_price,
                quantity: $order_item->count,
                meta: [
                    'type' => $catalog->type ?? 'gift_card',
                    'is_fallback' => true,
                    'seller_id' => $shop?->id,
                    'seller_name' => $shop?->name,
                    'terminal_id' => $shop ? (string)$shop->legal_entity_id : null,
                ]
            );
            $sourceReceipt = method_exists($driver, 'lastSourceLedgerReceipt') ? $driver->lastSourceLedgerReceipt() : null;

            sleep(1);

            // 2. Get Codes
            $codes = $driver->getCodes($externalOrderId);
            $code = !empty($codes) ? $codes[0] : null;

            if (filled($code)) {
                $order_item->update(['provider_order_id' => $externalOrderId ?: $ref]);

                if ($shop) {
                    app(\App\Services\LedgerService::class)->record($shop, 'PROVIDER_ORDER_SUCCESS', $order_item, [
                        'provider' => $provider->type,
                        'external_id' => $externalOrderId,
                        'is_fallback' => true,
                        ...$this->sourceReceiptPayload($sourceReceipt),
                    ]);
                }

                Log::info('Redeem: успех резервного провайдера', [
                    'order_item_id' => $order_item->id,
                    'fallback_provider_id' => $providerId,
                    'provider_type' => $provider->type,
                ]);

                return (string) $code;
            }
        } catch (\Throwable $e) {
            if ($shop) {
                app(\App\Services\LedgerService::class)->record($shop, 'PROVIDER_ORDER_FAILED', $order_item, [
                    'provider' => $provider->type,
                    'message' => $e->getMessage(),
                    'is_fallback' => true,
                ]);
            }

            Log::warning('Redeem: резервный провайдер не смог', [
                'order_item_id' => $order_item->id,
                'fallback_provider_id' => $providerId,
                'provider_type' => $provider->type,
                'message' => $e->getMessage(),
            ]);
        }

        return null;
    }

    private function serviceSku(WildflowCatalog $catalog): ?string
    {
        return data_get($catalog->data, 'service_sku')
            ?? data_get($catalog->data, 'data.sku')
            ?? data_get($catalog->data, 'data.product.sku')
            ?? data_get($catalog->data, 'product.sku')
            ?? $catalog->service_sku
            ?? null;
    }

    private function sourceReceiptPayload(?array $receipt): array
    {
        if (! is_array($receipt)) {
            return [];
        }

        return [
            'digital_goods_source_receipt_hash' => $receipt['event_hash'] ?? null,
            'source_order_reference' => $receipt['reference'] ?? null,
        ];
    }
}
