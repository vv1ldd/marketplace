<?php

namespace App\Http\Controllers;

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

            if ($order->items()->exists()) {
                $log->info('order already has processing items, skipping duplicate voucher issuance', [
                    'order_id' => $order->order_id,
                ]);

                $order->update([
                    'status' => 'PROCESSING',
                    'sub_status' => $data['substatus'],
                    'progress_id' => 2,
                ]);

                return [
                    'success' => true,
                    'transaction_ref' => $order->transactionReference(),
                    'source' => [
                        'channel' => 'yandex_market',
                        'order_id' => (string) $data['orderId'],
                    ],
                ];
            }

            $order_info = $order->info;

            $keys_data = [];
            $insert_data = [];

            $service = new YmService($order->shop);

            foreach ($order_info['items'] as $item) {

                $sku = (string) data_get($item, 'offerId');
                $issuance = $this->issueMarketplaceVoucherForYandexOrder($order, $item, $log);
                $inventory = $issuance['inventory'];
                $key = $issuance['voucher']; // our internal redeem token (not the real Wildflow gift-card code)

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
                    'nominal_amount' => $issuance['nominal_amount'],
                    'nominal_currency' => $issuance['nominal_currency'],
                    'count' => data_get($item, 'count'),
                    'price_rub' => data_get($item, 'price') * 100,
                    'price_try' => data_get($item, 'buyerPrice') * 100,
                    'type_form_id' => $type_form_id,
                    'purchase_status' => 'none',
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
                        'voucher_delivery' => 'yandex_market_slip',
                    ]);

                    app(\App\Services\LedgerService::class)->record($order->shop, 'VOUCHER_SLIP_ISSUED', $orderItem, [
                        'order_id' => $order->id,
                        'inventory_id' => $inventory->id,
                        'sku' => $inventory->sku,
                        'channel' => 'yandex_market',
                        'provider_code_pending' => true,
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

                // OrderItems already created above. Synthetic/manual sync keeps local state deterministic
                // without sending duplicate slips to Yandex Market.
                if (! data_get($data, 'is_manual_sync', false)) {
                    $service->provideOrderDigitalCodes(keys: $keys_data, campaignId: $data['campaignId'], orderId: $data['orderId']);
                }

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

            if (isset($order->chat_id) && $order->chat_id && method_exists($service, 'sendMessage')) {
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
                try {
                    SendTelegramJob::dispatchSync(order_id: $data['orderId'], status: 'new');
                } catch (\Throwable $telegramError) {
                    $log->warning('telegram notification skipped', [
                        'order_id' => $data['orderId'],
                        'error' => $telegramError->getMessage(),
                    ]);
                }
            }

            return [
                'success' => true,
                'transaction_ref' => $order->transactionReference(),
                'source' => [
                    'channel' => 'yandex_market',
                    'order_id' => (string) $data['orderId'],
                ],
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

    private function issueMarketplaceVoucherForYandexOrder(Order $order, array $item, mixed $log): array
    {
        $sku = (string) data_get($item, 'offerId');
        $quantity = max(1, (int) data_get($item, 'count', 1));
        $skuBidx = app(\App\Services\VaultTransitService::class)->computeBlindIndex($sku);
        $reservationReference = implode(':', [
            'ym',
            $order->order_id,
            data_get($item, 'id', 'item'),
            hash('sha256', $sku),
        ]);

        $product = \App\Models\Product::queryByOfferSku($sku)
            ->with('provider')
            ->where('shop_id', $order->shop_id)
            ->first();

        $catalog = null;
        if ($product) {
            $catalogSku = $product->wildflow_catalog_sku ?: $product->sku;
            $catalog = \App\Models\WildflowCatalog::where('sku', $catalogSku)->first();
        }

        if (! $product || ! $catalog) {
            throw new \Exception("Товар {$sku} не связан с активным Wildflow-каталогом, voucher для Яндекс.Маркета создать нельзя.");
        }

        $legalEntity = $order->shop?->legalEntity;
        if (! $legalEntity) {
            throw new \Exception("Для магазина заказа {$order->order_id} не найдено юридическое лицо.");
        }

        $nominalAmount = (float) ($catalog->retail_price ?: data_get($item, 'price', 0));
        $nominalCurrency = $catalog->currency_code ?: 'RUB';
        $rate = app(\App\Services\FinanceService::class)->getRate($nominalCurrency);
        $holdAmountRub = round($nominalAmount * $rate * $quantity, 2);

        $inventory = \App\Models\ProductInventory::where('reservation_reference', $reservationReference)->first();
        if (! $inventory) {
            $inventory = \App\Models\ProductInventory::where('shop_id', $order->shop_id)
                ->where('sku_bidx', $skuBidx)
                ->where('is_used', false)
                ->where('status', 'available')
                ->whereNull('reservation_reference')
                ->first();
        }

        if (! $inventory) {
            $voucher = \App\Services\VoucherEngine::issueDeterministic(
                issuerPrefix: $order->shop?->voucher_prefix ?: 'YM',
                sku: $sku,
                reference: $reservationReference,
                issuedAt: $order->created_at ?? now(),
            );

            $inventory = \App\Models\ProductInventory::create([
                'shop_id' => $order->shop_id,
                'warehouse_id' => \App\Models\Warehouse::where('shop_id', $order->shop_id)->where('is_main', true)->value('id'),
                'sku' => $sku,
                'nominal_amount' => $nominalAmount,
                'nominal_currency' => $nominalCurrency,
                'voucher' => $voucher,
                'is_used' => false,
                'status' => 'available',
                'expires_at' => now()->addYear(),
            ]);
        }

        if ($inventory->reservation_reference !== $reservationReference) {
            if ((float) $legalEntity->available_balance < $holdAmountRub) {
                throw new \Exception(
                    "Недостаточно средств для резервирования voucher по заказу Яндекс.Маркета. Требуется "
                    .number_format($holdAmountRub, 2).' RUB, доступно '
                    .number_format((float) $legalEntity->available_balance, 2).' RUB.'
                );
            }

            $inventory->update([
                'reservation_reference' => $reservationReference,
                'reserved_amount' => $holdAmountRub,
                'reserve_currency' => 'RUB',
                'reserved_at' => now(),
                'nominal_amount' => $inventory->nominal_amount ?: $nominalAmount,
                'nominal_currency' => $inventory->nominal_currency ?: $nominalCurrency,
            ]);

            $legalEntity->decrement('available_balance', $holdAmountRub);
            $legalEntity->increment('reserved_balance', $holdAmountRub);

            app(\App\Services\LedgerService::class)->record($order->shop, 'FINANCE_HOLD', $inventory, [
                'amount_rub' => $holdAmountRub,
                'order_id' => $order->id,
                'sku' => $sku,
                'count' => $quantity,
                'reservation_reference' => $reservationReference,
                'context' => 'yandex_market_voucher_slip',
            ]);
        }

        $log->info('Reserved marketplace voucher for Yandex slip', [
            'order_id' => $order->order_id,
            'sku' => $sku,
            'hold_amount_rub' => $holdAmountRub,
            'reservation_reference' => $reservationReference,
        ]);

        return [
            'inventory' => $inventory,
            'voucher' => $inventory->voucher,
            'nominal_amount' => $nominalAmount,
            'nominal_currency' => $nominalCurrency,
        ];
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

        $hasEmbeddedOrderInfo = ! empty($data['order_full_info']);
        $isSandbox = (($shop && $shop->is_sandbox) || ! empty($data['fake'])) && $hasEmbeddedOrderInfo;

        if ($isSandbox) {
            $order_full_info = $data['order_full_info'] ?? [];
            $items = data_get($order_full_info, 'items', []);
            $client_info = $data['client_info'] ?? [];
            if ($shop?->is_sandbox) {
                data_set($order_full_info, 'redeem_live_provider', true);
            }
            $log->debug('sandbox mode: using fake order data', ['order_full_info' => $order_full_info, 'client_info' => $client_info]);
        } else {
            try {
                $order_full_info = $service->getOrder(orderId: $data['orderId'], campaignId: $data['campaignId']);
                if ($shop?->is_sandbox && data_get($order_full_info, 'fake')) {
                    data_set($order_full_info, 'redeem_live_provider', true);
                }
                $items = data_get($order_full_info, 'items');
                $log->debug('order_full_info', [$order_full_info]);
            } catch (\Throwable $e) {
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
            } catch (\Throwable $e) {
                $client_info = data_get($order_full_info, 'buyer', []);
                $log->warning('getOrderBuyerInfo failed, using order buyer fallback', [
                    'exception' => $e->getMessage(),
                    'client_info' => $client_info,
                ]);
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
            if (method_exists($service, 'newChat')) {
                try {
                    $chat_id = $service->newChat($data['orderId']);
                    $log->debug('chat_id YM', [$chat_id]);
                } catch (ConnectionException $e) {
                    $log->error('newChat', [
                        'exception' => $e->getMessage(),
                    ]);
                }
            }

            if (isset($chat_id) && method_exists($service, 'sendMessage')) {
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
            $receiveEntry = app(\App\Services\LedgerService::class)->record($new_order->shop, 'ORDER_RECEIVE', $new_order, [
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
                    'comment' => data_get($order_full_info, 'redeem_live_provider')
                        ? '🧪 Это тестовый заказ Яндекс.Маркета. Клиентский voucher будет обменян на код через sandbox provider на /redeem.'
                        : '⚠️ Внимание! Это тестовый заказ Яндекс.Маркета (Sandbox). Реальная закупка товара производиться не будет.',
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
                $productPayload = [
                    'price_rub' => data_get($item, 'price') * 100,
                    'price_try' => data_get($item, 'buyerPrice') * 100,
                    'is_manual' => 1,
                    'type_form_id' => str_starts_with($sku, 'VOUCHER-') ? 2 : 1,
                    'type' => str_starts_with($sku, 'VOUCHER-') ? 'voucher' : 'giftcard',
                ];

                $itemName = data_get($item, 'order_item_name') ?? data_get($item, 'offerName');
                if ($itemName) {
                    $productPayload['name'] = $itemName;
                }

                \App\Models\Product::updateOrCreate([
                    'sku' => $sku,
                ], $productPayload);
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
            'transaction_ref' => $receiveEntry->transactionReference(),
            'source' => [
                'channel' => 'yandex_market',
                'order_id' => (string) $data['orderId'],
            ],
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
