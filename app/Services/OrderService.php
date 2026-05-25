<?php

namespace App\Services;

use App\Mail\SendActivationCode;
use App\Models\Order\Order;
use App\Models\Order\OrderItems;
use App\Models\WildflowCatalog;
use App\Services\Provider\ProviderHub;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class OrderService
{
    /**
     * Retry the automated purchase (autozakup) for a specific order item.
     */
    public function retryAutozakup(OrderItems $item): array
    {
        if ($item->is_activated !== true) {
            return [
                'success' => false,
                'error' => 'Код еще не был погашен пользователем (Redeem не пройден).',
            ];
        }

        // 1. Resolve Product and Provider
        $product = \App\Models\Product::where('sku', $item->sku)->first();
        if (!$product) {
            return [
                'success' => false,
                'error' => "Товар с SKU {$item->sku} не найден в каталоге.",
            ];
        }

        $provider = $product->provider;
        if (!$provider) {
            return [
                'success' => false,
                'error' => "Провайдер не привязан к товару {$item->sku}.",
            ];
        }

        try {
            Log::info("Autozakup Attempt for Item ID: {$item->id}", [
                'provider' => $provider->type,
                'sku' => $item->sku,
                'uuid' => $item->uuid,
            ]);

            $result = $this->fulfillAgnostic($item, $product);

            if ($result['success']) {
                $this->recalculateOrderProgress($item->order);
            }

            return $result;

        } catch (\Exception $e) {
            Log::error("Autozakup Failed for Item ID: {$item->id}", [
                'error' => $e->getMessage(),
            ]);

            // Deactivate product if it's out of stock at provider (for Wildflow)
            if ($provider->type === 'wildflow') {
                WildflowCatalog::deactivateIfProviderOutOfStock($e->getMessage(), $item->sku);
            }

            $item->update([
                'purchase_status' => 'failed',
                'purchase_error' => $e->getMessage(),
            ]);

            if ($item->order) {
                $item->order->update(['is_problem' => 1]);
            }

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Unified fulfillment logic for any provider.
     */
    protected function fulfillAgnostic(OrderItems $item, \App\Models\Product $product): array
    {
        $provider = $product->provider;
        $hub = app(ProviderHub::class);
        $driver = $hub->forProvider($provider);

        // 🛡️ Resolve SKU for the provider
        $catalogSku = $product->wildflow_catalog_sku ?? $product->sku;
        
        // Resolve shop for seller attribution
        $shop = $item->order?->shop;

        // 🚀 Dynamic Denomination Logic
        // For Steam/Roblox/etc, we might need a custom amount instead of simple count
        $meta = [
            'type' => $product->type ?? 'gift_card',
            'email' => data_get($item->client_info, 'email') ?? 'sataniyazow@gmail.com',
            'seller_id' => $shop ? (string)$shop->id : null,
            'seller_name' => $shop?->name,
            'terminal_id' => $shop ? (string)$shop->legal_entity_id : null,
        ];

        // If the item has a specific 'amount' or 'denomination' in its info, we use it
        $dynamicAmount = data_get($item->client_info, 'amount') 
            ?? data_get($item->client_info, 'meta.amount')
            ?? data_get($item->client_info, 'amount_usd');

        if ($dynamicAmount) {
            $meta['amount'] = $dynamicAmount;
        }

        // For Topups, we need player ID fields
        if (isset($item->client_info['game_fields'])) {
            $meta['game_fields'] = $item->client_info['game_fields'];
        }

        // 🛡️ Sovereign Ledger: Record the START of the external order
        $providerReference = $item->providerReference();
        if ($shop) {
            app(\App\Services\LedgerService::class)->record($shop, 'PROVIDER_ORDER_START', $item, [
                'provider' => $provider->type,
                'sku' => $catalogSku,
                'reference' => $providerReference,
            ]);
        }

        try {
            // 1. Create order at Provider
            $externalOrderId = $driver->createOrder(
                sku: $catalogSku,
                reference: $providerReference,
                price: (float)($product->retail_price ?? 0),
                quantity: (int)$item->count,
                meta: $meta
            );

            if (!$externalOrderId) {
                throw new \Exception("Заказ создан у провайдера {$provider->name}, но не получен внешний ID.");
            }

            $item->update(['provider_order_id' => $externalOrderId]);

            sleep(1);

            // 2. Fetch the codes (if available immediately)
            $codes = $driver->getCodes($externalOrderId);
            $code = !empty($codes) ? $codes[0] : null;

            if ($code) {
                if ($shop) {
                    app(\App\Services\LedgerService::class)->record($shop, 'PROVIDER_ORDER_SUCCESS', $item, [
                        'provider' => $provider->type,
                        'external_id' => $externalOrderId,
                        'sku' => $catalogSku,
                    ]);
                }

                $item->update([
                    'purchase_status' => 'success',
                    'original_code' => $code,
                    'purchase_error' => null,
                ]);

                $this->notifyCustomer($item, $code);

                return ['success' => true, 'code' => $code];
            }
        } catch (\Exception $e) {
            if ($shop) {
                app(\App\Services\LedgerService::class)->record($shop, 'PROVIDER_ORDER_FAILED', $item, [
                    'provider' => $provider->type,
                    'message' => $e->getMessage(),
                    'sku' => $catalogSku,
                ]);
            }
            throw $e; // Rethrow to be caught by retryAutozakup
        }

        // 3. Otherwise, it might be a pending topup
        $item->update([
            'purchase_status' => 'processing',
            'purchase_error' => null,
        ]);

        return [
            'success' => true,
            'status'  => 'processing',
            'message' => 'Заказ в обработке у провайдера.',
        ];
    }

    protected function notifyCustomer(OrderItems $item, string $code): void
    {
        $email = data_get($item->client_info, 'email');
        if ($email) {
            try {
                Mail::to($email)->send(new SendActivationCode($code, $item->order));
            } catch (\Exception $e) {
                Log::error("Failed to send activation email: " . $e->getMessage());
            }
        }
    }

    /**
     * Recalculate order overall status based on items.
     */
    public function recalculateOrderProgress(Order $order): void
    {
        $items = $order->items;
        $all_purchased = $items->every(fn ($item) => $item->purchase_status === 'success');
        $all_activated = $items->every('is_activated');

        if ($all_purchased && $all_activated) {
            $order->update(['progress_id' => 4]); // Выполнено
        }
    }
}
