<?php

namespace App\Jobs;

use App\Mail\SendActivationCode;
use App\Models\Customer;
use App\Models\Order\Order;
use App\Models\Order\OrderItems;
use App\Models\WildflowCatalog;
use App\Services\Provider\ProviderHub;
use App\Services\WildflowService;
use App\Services\RedeemFallbackPurchaseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class ProcessRedeemWildflowPurchase implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $orderItemId,
        public int $customerId,
        public bool $deliverToChat,
    ) {}

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('redeem-wildflow-'.$this->orderItemId))->releaseAfter(90)];
    }

    public function handle(RedeemFallbackPurchaseService $fallbackPurchase): void
    {
        $order_item = OrderItems::with(['order.shop'])->find($this->orderItemId);
        if (! $order_item) {
            Log::warning('ProcessRedeemWildflowPurchase: order item missing', ['id' => $this->orderItemId]);

            return;
        }

        if ($order_item->purchase_status !== 'pending') {
            return;
        }

        $customer = Customer::find($this->customerId);
        if (! $customer || ! $customer->email) {
            Log::error('ProcessRedeemWildflowPurchase: customer or email missing', [
                'order_item_id' => $this->orderItemId,
                'customer_id' => $this->customerId,
            ]);

            return;
        }

        $order = $order_item->order;
        if (! $order) {
            return;
        }

        if ($order->isDevAsyncRedeemDemo()) {
            $original_code = 'DEMO-REDEEM-'.strtoupper(Str::random(8));
            $order->comments()->create([
                'user_id' => $customer->id,
                'user_type' => $customer::class,
                'comment' => 'Dev async demo: выдан тестовый код (без Wildflow): '.Str::mask($original_code, '*', 4, -4),
            ]);
            $order_item->update([
                'purchase_status' => 'success',
                'original_code' => $original_code,
                'purchase_error' => null,
            ]);

            $this->captureFundsFromHold($order, $order_item, $customer);
            
            $this->markOrderProgressIfComplete($order, $order_item);
            $this->notifyCustomerWithCode($order_item, $order, $customer, $original_code);

            return;
        }

        $catalog = WildflowCatalog::findForOrderOfferSku($order_item->sku);
        if (! $catalog) {
            $order_item->update(['purchase_status' => 'manual']);

            return;
        }

        // 🛡️ Provider Hub Integration
        $hub = app(ProviderHub::class);
        $vault = app(\App\Services\VaultTransitService::class);
        $providerProduct = \App\Models\ProviderProduct::where('market_sku_bidx', $vault->computeBlindIndex($catalog->sku))->first();
        $provider = $providerProduct?->provider ?? \App\Models\Provider::where('type', 'wildflow')->first();
        $driver = $hub->forProvider($provider);
        
        // The true numeric SKU for provider API is buried inside the JSON blob, 
        // while the column holds the internal encrypted vault alias string.
        $numericServiceSku = data_get($catalog->data, 'service_sku') 
            ?? data_get($catalog->data, 'data.sku') 
            ?? $catalog->service_sku;

        try {
            // 🛡️ Sovereign Ledger: Record the START of the external order
            app(\App\Services\LedgerService::class)->record($order->shop, 'PROVIDER_ORDER_START', $order_item, [
                'provider' => $provider->type,
                'sku' => $catalog->sku,
                'reference' => $order_item->uuid,
            ]);

            // 1. Determine exact prices (respect dynamic nominal if stored)
            $finalNominal = (float) ($order_item->nominal_amount ?? $catalog->retail_price);
            $finalBuyPrice = $catalog->is_variable_price
                ? ($finalNominal * (1 + (float)(data_get($catalog->data, 'data.percentage_of_buying_price', data_get($catalog->data, 'percentage_of_buying_price', -2)) / 100)))
                : $catalog->purchase_price;

            // 1. Create Order
            $externalOrderId = $driver->createOrder(
                sku: $numericServiceSku,
                reference: $order_item->uuid,
                price: $finalNominal,
                quantity: $order_item->count,
                meta: [
                    'buying_price' => $finalBuyPrice,
                    'email' => $customer->email,
                    'pre_order' => (bool) data_get($catalog->data, 'data.pre_order', data_get($catalog->data, 'pre_order', false)),
                ]
            );

            // 2. Poll for Cards (Adaptive Strategy to combat async delay)
            $codes = [];
            for ($attempt = 1; $attempt <= 12; $attempt++) {
                sleep(2); // Give provider space to breathe
                $codes = $driver->getCodes($externalOrderId);
                if (!empty($codes)) {
                    break;
                }
                \Illuminate\Support\Facades\Log::info("Redeem Polling attempt {$attempt}/12 gave no codes yet for order {$externalOrderId}. Waiting...");
            }
            $original_code = !empty($codes) ? $codes[0] : null;

            if ($original_code) {
                $this->captureFundsFromHold($order, $order_item, $customer);

                // ⛓️ Sovereign Ledger: Record the SUCCESSFUL REDEEM
                app(\App\Services\LedgerService::class)->record($order->shop, 'VOUCHER_REDEEM_SUCCESS', $order_item, [
                    'provider' => $provider->type,
                    'external_id' => $externalOrderId,
                    'customer_id' => $customer->id,
                    'customer_email' => $customer->email,
                    'sku' => $order_item->sku,
                    'code_masked' => Str::mask($original_code, '*', 4, -4),
                ]);

                $order->comments()->create([
                    'user_id' => $customer->id,
                    'user_type' => $customer::class,
                    'comment' => "Активация успешна ({$provider->name}). Получен код: ".Str::mask($original_code, '*', 4, -4),
                ]);
                $order_item->update([
                    'purchase_status' => 'success',
                    'original_code' => $original_code,
                    'purchase_error' => null,
                ]);
                $this->markOrderProgressIfComplete($order, $order_item);
                $this->notifyCustomerWithCode($order_item, $order, $customer, $original_code);

                return;
            }

            // 🤝 SOFT HANDOFF TO ASYNC QUEUE:
            // If synchronous polling fails, we DO NOT crash. We silently forward this task to 
            // the background queue worker to keep polling while the user watches a pretty spinner.
            $order_item->increment('purchase_retry_count');
            $currentRetries = (int) $order_item->fresh()->purchase_retry_count;
            
            if ($currentRetries <= 3) {
                self::dispatch($this->orderItemId, $this->customerId, $this->deliverToChat)
                    ->delay(now()->addSeconds(15));
                
                \Illuminate\Support\Facades\Log::info("Synchronous window closed. Forwarded order item {$this->orderItemId} to ASYNC background queue for adaptive retry #{$currentRetries}.");
                
                // Quiet exit. Status stays 'pending'. CodeController will redirect to Spinner view.
                return; 
            }

            // Exhausted all fallback attempts (sync + 3 async rounds). Now we throw real failure.
            throw new \Exception("Provider returned empty codes list for order {$externalOrderId} after max fallback attempts.");

        } catch (\Exception $e) {
            // ⛓️ Sovereign Ledger: Record the FAILED PROVIDER ORDER
            app(\App\Services\LedgerService::class)->record($order->shop, 'PROVIDER_ORDER_FAILED', $order_item, [
                'provider' => $provider->type,
                'message' => $e->getMessage(),
                'sku' => $catalog->sku,
            ]);

            Log::error('Provider redeem job error', [
                'provider' => $provider->type,
                'message' => $e->getMessage(), 
                'uuid' => $order_item->uuid
            ]);

            WildflowCatalog::deactivateIfProviderOutOfStock($e->getMessage(), $order_item->sku);

            $fallbackCode = $fallbackPurchase->tryAlternateWildflowAfterPrimaryFailure(
                $order_item,
                $catalog,
                $e->getMessage(),
            );

            if ($fallbackCode) {
                $this->applyFallbackSuccess($order, $order_item, $customer, $fallbackCode);

                return;
            }

            $this->finalizeRedeemSoftFailure(
                $order,
                $order_item,
                $customer,
                "Ошибка провайдера ({$provider->name}): ".$e->getMessage(),
                $e->getMessage()
            );
        }
    }

    private function applyFallbackSuccess(Order $order, OrderItems $order_item, Customer $customer, string $original_code): void
    {
        $this->captureFundsFromHold($order, $order_item, $customer);

        // ⛓️ Sovereign Ledger: Record the SUCCESSFUL REDEEM (Fallback)
        app(\App\Services\LedgerService::class)->record($order->shop, 'VOUCHER_REDEEM_SUCCESS', $order_item, [
            'customer_id' => $customer->id,
            'customer_email' => $customer->email,
            'sku' => $order_item->sku,
            'is_fallback' => true,
            'code_masked' => Str::mask($original_code, '*', 4, -4),
        ]);

        $order->comments()->create([
            'user_id' => $customer->id,
            'user_type' => $customer::class,
            'comment' => 'Активация товара (через резервного провайдера). Код: '.Str::mask($original_code, '*', 4, -4),
        ]);

        $this->markOrderProgressIfComplete($order, $order_item);
        $this->notifyCustomerWithCode($order_item, $order, $customer, $original_code);
    }

    /**
     * Клиенту на /redeem не показываем purchase_error — только мягкий текст; детали в комментарии заказа.
     */
    private function finalizeRedeemSoftFailure(
        Order $order,
        OrderItems $order_item,
        Customer $customer,
        string $commentBody,
        string $internalSnippet,
    ): void {
        $humanError = $this->humanizeWildflowError($internalSnippet);

        // 🔄 REFUND LOGIC: Return funds to LegalEntity balance on definitive failure
        $shop = $order->shop;
        $legalEntity = $shop?->legalEntity;
        if ($shop && $shop->id !== 1 && $legalEntity) {
             // Calculate what was deducted (service price in cents * count)
             $catalog = \App\Models\WildflowCatalog::findForOrderOfferSku($order_item->sku);
             $service_price = $catalog ? (float)data_get($catalog, 'data.data.price', 0) : 0;
             $totalCost = (int)($service_price * 100) * $order_item->count;

             if ($totalCost > 0) {
                 // 🔄 RELEASE HOLD: Move back from reserved_balance to balance
                 \DB::transaction(function () use ($legalEntity, $totalCost) {
                     $legalEntity->decrement('reserved_balance', $totalCost);
                     $legalEntity->increment('balance', $totalCost);
                 });
                 
                 $order->comments()->create([
                     'user_id' => $customer->id,
                     'user_type' => $customer::class,
                     'comment' => "Финансы: Резерв средств в размере " . ($totalCost / 100) . " руб разблокирован и возвращен на баланс из-за ошибки активации.",
                 ]);

                 Log::info("Hold released for LegalEntity [ID: {$legalEntity->id}] due to redeem failure.", ['amount' => $totalCost / 100]);
             }
        }

        $order->comments()->create([
            'user_id' => $customer->id,
            'user_type' => $customer::class,
            'comment' => $commentBody . ' | Ошибка: ' . $humanError,
        ]);

        // ⛓️ Sovereign Ledger: Record the FAILED REDEEM
        app(\App\Services\LedgerService::class)->record($order->shop, 'VOUCHER_REDEEM_FAILED', $order_item, [
            'customer_id' => $customer->id,
            'error' => $internalSnippet,
        ]);

        $order_item->update([
            'purchase_status' => 'failed',
            'purchase_error' => $humanError, // Filtered for Seller eyes
        ]);

        $order->update(['is_problem' => 1]);

        // 🧊 Liquidation: The intermediate voucher given to customer is now VOID
        $inventory = \App\Models\ProductInventory::where('order_item_id', $order_item->id)->first();
        if ($inventory) {
            $inventory->liquidate("Redeem Failed: {$internalSnippet}");
        }
    }

        private function humanizeWildflowError(string $internalSnippet): string
    {
        // Pre-clean snippet from nasty backslashes for easier matching
        $clean = str_replace("\\\"", "\"", $internalSnippet);

        // 1. Critical mapping: Lack of Balance!
        if (str_contains($clean, '"code":"610"') || stripos($clean, 'balance is not sufficient') !== false || stripos($clean, 'Insufficient balance') !== false) {
            return 'У поставщика временно закончились лимиты для выдачи этого товара';
        }

        // 2. Recognized error code mappings
        if (str_contains($clean, '"code":"635"') || stripos($clean, 'Not enough cards available') !== false) {
            return 'У поставщика закончился сток (карты данного типа)';
        }
        if (str_contains($clean, '"code":"612"') || stripos($clean, 'Product price is incorrect') !== false) {
            return 'Цена товара изменилась у поставщика';
        }
        if (str_contains($clean, '"code":"602"') || stripos($clean, 'Product is not available') !== false) {
            return 'Товар временно недоступен у поставщика';
        }

        // 3. Intelligent extraction of "detail" message using simpler substring logic
        if (stripos($clean, '"detail":"') !== false) {
            $parts = explode('"detail":"', $clean);
            $end = explode('"', $parts[1]);
            if (!empty($end[0])) {
                return 'Провайдер сообщает: ' . trim($end[0]);
            }
        }

        // 4. Fallback to generic but clean message
        return 'Временная техническая недоступность провайдера';
    }

    private function markOrderProgressIfComplete(Order $order, OrderItems $order_item): void
    {
        $order_items = OrderItems::where('order_id', $order->id)->get();
        if ($order_items->every(fn (OrderItems $item) => $item->purchase_status === 'success' || $item->id === $order_item->id)) {
            $order->update(['progress_id' => 4]);
        }
    }

    private function notifyCustomerWithCode(OrderItems $order_item, Order $order, Customer $customer, string $original_code): void
    {
        $finishUrl = URL::temporarySignedRoute(
            'redeem.success',
            now()->addDays(14),
            ['uuid' => $order_item->uuid]
        );

        Mail::to($customer->email)->send(new SendActivationCode($original_code, $order, 'sataniyazow@gmail.com', $finishUrl));

        if ($order->chat_id && $this->deliverToChat) {
            try {
                $ymService = new \App\Http\Services\YmService($order->shop);
                $ymService->sendMessage($order->chat_id, view('chat.send_code_message', ['code' => $original_code, 'shop' => $order->shop])->render());

                $order->comments()->create([
                    'user_id' => $customer->id,
                    'user_type' => $customer::class,
                    'comment' => 'Код успешно дублирован в чат Яндекс.Маркета',
                ]);
            } catch (\Exception $chatE) {
                Log::error('YM Chat send error (redeem job)', ['message' => $chatE->getMessage()]);
                $order->comments()->create([
                    'user_id' => $customer->id,
                    'user_type' => $customer::class,
                    'comment' => 'Ошибка отправки кода в чат: '.$chatE->getMessage(),
                ]);
            }
        }
    }

    /**
     * Move money from Reserved to "Sold" (Actual Capture)
     */
    private function captureFundsFromHold(Order $order, OrderItems $order_item, Customer $customer): void
    {
        $legalEntity = $order->shop?->legalEntity;
        if (! $legalEntity) {
            return;
        }

        // 1. Determine cost (based on actual catalog price at moment of sale or purchase price)
        $product = \App\Models\Product::where('sku', $order_item->sku)->where('shop_id', $order->shop_id)->first();
        $catalogSku = $product?->wildflow_catalog_sku ?? $order_item->sku;
        
        $catalog = \App\Models\WildflowCatalog::where('sku', $catalogSku)->first();
        if (! $catalog) {
            Log::warning('Capture: Catalog item not found for SKU', ['sku' => $catalogSku]);
            return;
        }

        $financeService = app(\App\Services\FinanceService::class);
        $cur = $order_item->nominal_currency ?: ($catalog->currency_code ?? 'USD');
        $rate = $financeService->getRate($cur);
        
        $baseNominal = (float) ($order_item->nominal_amount ?? $catalog->retail_price);
        $costRub = $baseNominal * $rate * $order_item->count;

        // 2. Decrement from Reserved Balance
        // Note: We check if there's enough in reserved, though usually there is.
        if ($legalEntity->reserved_balance >= $costRub) {
            $legalEntity->decrement('reserved_balance', $costRub);

            // ✅ Mark inventory as SOLD
            $inventory = \App\Models\ProductInventory::where('order_item_id', $order_item->id)->first();
            if ($inventory) {
                $inventory->update(['status' => 'sold']);
            }

            $order->comments()->create([
                'user_id' => $customer->id,
                'user_type' => $customer::class,
                'comment' => "Финансы: Захват средств (Capture) на сумму " . number_format($costRub, 2) . " RUB завершен успешно.",
            ]);

            // ⛓️ Sovereign Ledger: Record the CAPTURE
            app(\App\Services\LedgerService::class)->record($order->shop, 'FINANCE_CAPTURE', $order, [
                'amount_rub' => $costRub,
                'order_item_id' => $order_item->id,
                'customer_id' => $customer->id,
                'customer_email' => $customer->email,
            ]);
        } else {
            // Log warning if for some reason reserved balance was already low
            Log::warning('Capture: Reserved balance lower than cost', [
                'order_id' => $order->id,
                'reserved' => $legalEntity->reserved_balance,
                'cost' => $costRub
            ]);
        }
    }
}
