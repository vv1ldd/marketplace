<?php

namespace App\Http\Controllers;

use App\Helpers\GenerateSecureCode;
use App\Helpers\NormalizePhone;
use App\Http\Services\YmService;
use App\Jobs\SendTelegramJob;
use App\Models\Order\Order;
use App\Models\Order\OrderItems;
use App\Models\Settings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Str;
use Random\RandomException;

class OrderController extends Controller
{
    private $log;

    public function __construct(string $method)
    {
        $this->log = \Log::channel('ps_order')->withContext([
            'method' => $method,
            'log_id' => Str::random(8),
        ]);

        $this->log->info('/-----------------------/');
    }


    /**
     * @return array|true[]
     */
    public function updated(array $data): array
    {
        $log = $this->log;

        $log->debug('updated data', [$data]);

        $order = Order::where('order_id', $data['orderId'])
            ->first();

        if (! $order) {
            $log->error('order not found', [$order]);

            return [
                'success' => false,
                'error' => 'order not found',
            ];
        }

        if ($order->status === 'DELIVERED') {
            $log->error('current order status not NEW', [$order]);

            return [
                'success' => false,
                'error' => 'current order status not NEW or PROCESSING',
                'code' => 1,
            ];
        }

        if ($data['status'] === 'PROCESSING') {

            $log->info('status PROCESSING');

            $order_info = $order->info;

            $keys_data = [];
            $insert_data = [];

            $service = new YmService($order->shop);

            foreach ($order_info['items'] as $item) {

                $sku = (string) data_get($item, 'offerId');
                
                // 🎟 Try to find a pre-generated voucher in stock
                $inventory = \App\Models\ProductInventory::where('shop_id', $order->shop_id)
                    ->where('sku', $sku)
                    ->where('is_used', false)
                    ->first();
                
                if (! $inventory) {
                    $product_model = \App\Models\Product::with('provider')->queryByOfferSku($sku)->where('shop_id', $order->shop_id)->first();
                    
                    $hasActiveProvider = $product_model && $product_model->provider_id && $product_model->provider?->is_active;

                    if ($hasActiveProvider && $order->shop_id >= 1) { // Include shop 1 for testing and main operations
                        $log->info('Stock empty, attempting auto-replenish', ['sku' => $sku]);
                        
                        try {
                            $catalogItem = \App\Models\WildflowCatalog::where('sku', $product_model->wildflow_catalog_sku ?? $product_model->sku)->first();
                            $sellerId = $order->shop->sellers()->first()?->id ?? 1; // Fallback to system user if no seller linked

                            if ($catalogItem) {
                                // Try to buy 1 item into stock automatically
                                \App\Jobs\AddCatalogItemToShop::dispatchSync(
                                    catalogItemId: $catalogItem->id,
                                    shopId: $order->shop_id,
                                    sellerId: $sellerId,
                                    count: 1
                                );
                                
                                // Re-search for inventory
                                $inventory = \App\Models\ProductInventory::where('shop_id', $order->shop_id)
                                    ->where('sku', $sku)
                                    ->where('is_used', false)
                                    ->first();
                                    
                                if ($inventory) {
                                    $log->info('Auto-replenish success, voucher obtained');
                                    $order->comments()->create([
                                        'comment' => "📦 Автопополнение: Товар {$sku} автоматически добавлен на склад (баланс позволил).",
                                    ]);
                                }
                            }
                        } catch (\Exception $e) {
                            $log->warning('Auto-replenish failed', ['error' => $e->getMessage()]);
                        }
                    }
                }

                if (! $inventory) {
                    throw new \Exception("Товар {$sku} отсутствует на складе и автопополнение невозможно (проверьте баланс или наличие у поставщика).");
                }

                $key = $inventory->voucher; // our internal redeem token (not the real Wildflow gift-card code)

                $keys_data[] = [
                    'id' => data_get($item, 'id'),
                    'activate_till' => now()->addYear()->format('Y-m-d'),
                    'slip' => view('instruction', ['shop' => $order->shop])->render(),
                    'codes' => [$key],
                ];

                $product_model = \App\Models\Product::queryByOfferSku($sku)->first();
                $type_form_id = $product_model?->type_form_id;

                $order_item_uuid = Str::uuid()->toString();
                
                $orderItem = OrderItems::create([
                    'key' => $key,
                    'uuid' => $order_item_uuid,
                    'order_id' => $order->id,
                    'activate_till' => now()->addYear()->format('Y-m-d'),
                    'sku' => $sku,
                    'count' => data_get($item, 'count'),
                    'price_rub' => data_get($item, 'price') * 100,
                    'price_try' => data_get($item, 'buyerPrice') * 100,
                    'type_form_id' => $type_form_id,
                    'purchase_status' => 'pending',
                ]);

                // If we used a pre-generated code, link it to this item
                if ($inventory) {
                    $inventory->update([
                        'is_used' => true,
                        'order_item_id' => $orderItem->id,
                        'status' => 'reserved', // Voucher is now held under this order
                    ]);

                    // ⛓️ Sovereign Ledger: Record the STOCK_RESERVE
                    app(\App\Services\LedgerService::class)->record($order->shop, 'STOCK_RESERVE', $inventory, [
                        'order_id' => $order->id,
                        'order_item_id' => $orderItem->id,
                        'sku' => $inventory->sku,
                    ]);

                    // 💰 Sovereign Ledger: Record the FINANCE_CAPTURE (Since we just delivered the code to Yandex)
                    app(\App\Services\LedgerService::class)->record($order->shop, 'FINANCE_CAPTURE', $order, [
                        'order_id' => $order->id,
                        'inventory_id' => $inventory->id,
                        'sku' => $inventory->sku,
                    ]);

                    // 📊 Stock Management: Check rules (notifications, auto-replenish)
                    if ($product_model) {
                        app(\App\Services\StockManagementService::class)->processStockChange($product_model);
                    }
                }
            }

            $log->debug('keys', [$keys_data]);
            $log->debug('order items created');

            try {

                \DB::beginTransaction();

                // OrderItems already created above
                
                $service->provideOrderDigitalCodes(keys: $keys_data, campaignId: $data['campaignId'], orderId: $data['orderId']);

                $order->update([
                    'status' => 'PROCESSING',
                    'sub_status' => $data['substatus'],
                    'progress_id' => 2, // В обработке
                ]);

                \DB::commit();

            } catch (\Exception $e) {

                \DB::rollBack();

                $log->error('provideOrderDigitalCodes', [$e->getMessage()]);

                return [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }

            $order = Order::where('order_id', $data['orderId'])->first();

            if (isset($order->chat_id) && $order->chat_id) {
                try {
                    $service->sendMessage($order->chat_id, view('chat.finish_message', ['shop' => $order->shop])->render());
                    $log->debug('success send YM finish Message');
                } catch (ConnectionException $e) {
                    $log->error('sendMessage finish', [
                        'exception' => $e->getMessage(),
                    ]);
                }
            }

            $log->info('success');

            if (! data_get($data, 'is_manual_sync', false)) {
                SendTelegramJob::dispatchSync(order_id: $data['orderId'], status: 'new');
            }

            return [
                'success' => true,
            ];

        } elseif ($data['status'] === 'DELIVERY' || $data['status'] === 'DELIVERED') {

            $log->info('status DELIVERED or DELIVERY');

            $order->update([
                'status' => $data['status'],
                'sub_status' => $data['substatus'],
                'progress_id' => ($data['status'] === 'DELIVERED') ? 4 : 2,
            ]);

            return [
                'success' => true,
            ];

        } elseif ($data['status'] === 'CANCELLED') {

            $log->info('status CANCELLED - triggering release logic');

            $order_items = OrderItems::where('order_id', $order->id)->get();

            foreach ($order_items as $item) {
                $inventory = \App\Models\ProductInventory::where('order_item_id', $item->id)->first();
                if ($inventory) {
                    $inventory->release('Marketplace Cancellation: ' . ($data['substatus'] ?? 'No reason provided'));
                }
            }

            $order->update([
                'status' => 'CANCELLED',
                'sub_status' => $data['substatus'] ?? 'CANCELLED',
                'progress_id' => 5, // Отменен
            ]);

            $order->comments()->create([
                'comment' => "🚫 Заказ отменен маркетплейсом ({$data['substatus']}). Товар возвращен на склад, баланс восстановлен.",
            ]);

            // ⛓️ Sovereign Ledger: Record the Order Cancellation
            app(\App\Services\LedgerService::class)->record($order->shop, 'ORDER_CANCEL', $order, [
                'reason' => $data['substatus'] ?? 'unknown',
            ]);

            return [
                'success' => true,
            ];

        } else {

            $log->error('status not PROCESSING or DELIVERED or DELIVERY');

            return [
                'success' => false,
                'error' => 'status not PROCESSING or DELIVERED or DELIVERY',
            ];
        }
    }

    public function created(array $data): array
    {
        $log = $this->log;

        $log->debug('created data', [$data]);

        $order = Order::where('order_id', $data['orderId'])->first();

        if ($order) {
            $log->error('order already created', [$order]);

            return [
                'success' => false,
                'error' => 'order already created',
                'code' => 1,
            ];
        }

        $shop = isset($data['shop_id']) ? \App\Models\Shop::find($data['shop_id']) : null;
        $service = new YmService($shop);

        $isSandbox = ($shop && $shop->is_sandbox) || !empty($data['fake']);

        if ($isSandbox) {
            $order_full_info = $data['order_full_info'] ?? [];
            $items = data_get($order_full_info, 'items', []);
            $client_info = $data['client_info'] ?? [];
            $log->debug('sandbox mode: using fake order data', ['order_full_info' => $order_full_info, 'client_info' => $client_info]);
        } else {
            try {
                $order_full_info = $service->getOrder(orderId: $data['orderId'], campaignId: $data['campaignId']);
                $items = data_get($order_full_info, 'items');
                $log->debug('order_full_info', [$order_full_info]);
            } catch (ConnectionException $e) {
                $log->error('order_full_info', [
                    'exception' => $e->getMessage(),
                ]);

                return [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }

            try {
                $client_info = $service->getOrderBuyerInfo(orderId: $data['orderId'], campaignId: $data['campaignId']);
                $log->debug('client_info', [$client_info]);
            } catch (ConnectionException $e) {
                $log->error('getOrderBuyerInfo', [
                    'exception' => $e->getMessage(),
                ]);

                return [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        try {
            $user = UserController::getByYmUserId($client_info['id']);
            if ($user) {
                $log->debug('user found by ym_user_id', [$user]);
            } else {
                $log->debug('user not found by ym_user_id', [$client_info]);
            }

        } catch (\Exception $exception) {
            $log->error('getByYmUserId, but continue process', [
                'exception' => $exception->getMessage(),
            ]);
        }

        $is_manual_sync = data_get($data, 'is_manual_sync', false);

        if (! $is_manual_sync) {
            try {
                $chat_id = $service->newChat($data['orderId']);
                $log->debug('chat_id YM', [$chat_id]);
            } catch (ConnectionException $e) {
                $log->error('newChat', [
                    'exception' => $e->getMessage(),
                ]);
            }

            if (isset($chat_id)) {
                try {
                    $service->sendMessage($chat_id, view('chat.start_message', ['shop' => $shop])->render());
                    $log->debug('success send YM Message');
                } catch (ConnectionException $e) {
                    $log->error('sendMessage YM', [
                        'exception' => $e->getMessage(),
                    ]);
                }
            }
        }

        try {
            $new_order = Order::create([
                'order_id' => $data['orderId'],
                'uuid' => Str::uuid()->toString(),
                'info' => $order_full_info,
                'client_info' => $client_info,
                'chat_id' => $chat_id ?? null,
                'user_id' => $user->id ?? null,
                'shop_id' => $data['shop_id'] ?? null,
                'business_id' => $shop ? $shop->business_id : null,
                'campaign_id' => $shop ? $shop->campaign_id : ($data['campaignId'] ?? null),
                'is_test' => data_get($order_full_info, 'fake', false),
                'progress_id' => 2, // В обработке
            ]);

            // ⛓️ Sovereign Ledger: Record the initial order receipt (Yandex)
            app(\App\Services\LedgerService::class)->record($new_order->shop, 'ORDER_RECEIVE', $new_order, [
                'external_id' => $data['orderId'],
                'channel' => 'yandex',
                'is_test' => data_get($order_full_info, 'fake', false),
                'client_email' => $client_info['email'] ?? null,
                'client_phone' => $client_info['phone'] ?? null,
            ]);

            $order_id = $new_order->id;

            $new_order->comments()->create([
                'user_id' => null,
                'comment' => 'Заказ получен из Яндекс.Маркета'.(data_get($order_full_info, 'fake') ? ' (ТЕСТ)' : ''),
            ]);

            if (data_get($order_full_info, 'fake')) {
                $new_order->comments()->create([
                    'user_id' => null,
                    'comment' => '⚠️ Внимание! Это тестовый заказ Яндекс.Маркета (Sandbox). Реальная закупка товара производиться не будет.',
                ]);
            }
        } catch (\Exception $e) {

            $log->error('create order', [
                'exception' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }

        foreach ($items as $item) {

            $sku = data_get($item, 'offerId');

            if (! $sku) {
                continue;
            }

            try {
                \App\Models\Product::updateOrCreate([
                    'sku' => $sku,
                ], [
                    'price_rub' => data_get($item, 'price') * 100,
                    'price_try' => data_get($item, 'buyerPrice') * 100,
                    'name' => data_get($item, 'order_item_name'),
                    'is_manual' => 1,
                    'type_form_id' => str_starts_with($sku, 'VOUCHER-') ? 2 : 1,
                    'type' => str_starts_with($sku, 'VOUCHER-') ? 'voucher' : 'game',
                ]);
            } catch (\Exception  $e) {

                $log->error('update product error, but continue', [
                    'exception' => $e->getMessage(),
                    'item' => $item,
                ]);

                continue;
            }
        }

        $log->debug('created', [
            'order_id' => $order_id,
        ]);

        return [
            'success' => true,
            'order_id' => $order_id,
        ];
    }

    public function arbitrageFinished(array $data): array
    {
        $log = $this->log;
        $log->debug('arbitrageFinished data', [$data]);

        $order = Order::where('order_id', $data['orderId'])->first();

        if (! $order) {
            $log->error('order not found for arbitrageFinished', [$data['orderId']]);

            return ['success' => false, 'error' => 'order not found'];
        }

        $decision = $data['decision'] ?? 'UNKNOWN';

        $order->update([
            'dispute_decision' => $decision,
        ]);

        $order->comments()->create([
            'user_id' => null,
            'comment' => '⚖️ Арбитраж по заказу завершен. '.'Решение: '.$decision,
        ]);

        $log->info('arbitrageFinished success');

        return ['success' => true];
    }
}
