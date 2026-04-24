<?php

namespace App\Http\Controllers;

use App\Helpers\GenerateSecureCode;
use App\Helpers\NormalizePhone;
use App\Http\Services\YmService;
use App\Jobs\SendTelegramJob;
use App\Models\Order\Order;
use App\Models\Order\OrderItems;
use App\Models\PlayStation\PlayStationAlt;
use App\Models\Settings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Mail;
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

    public function createdFromWoo(array $order, array $items, string $connection): array
    {
        $log = $this->log;

        $log->debug('createFromWoo data', ['order' => $order, 'items' => $items, 'connection' => $connection]);

        $client_info = [
            'email' => $order['billing_email'],
            'phone' => NormalizePhone::normalize($order['billing_phone']),
            'lastName' => $order['billing_last_name'],
            'firstName' => $order['billing_first_name'],
        ];

        try {
            $user = UserController::getByPhone($order["billing_phone"]);
            if ($user) {
                $log->debug('user found by phone', [$user]);
            } else {
                $log->debug('user not found by phone', [$client_info]);
            }

        } catch (\Exception $exception) {
            $log->error('error getByPhone, but continue process', [
                'exception' => $exception->getMessage(),
            ]);
        }

        if (empty($user)) {
            try {
                $user = UserController::updateOrCreate($order["billing_phone"], [
                    'email' => $order["billing_email"],
                    'last_name' => $order["billing_last_name"],
                    'first_name' => $order["billing_first_name"],
                ]);
                $log->debug('user created', [$user]);
            } catch (\Exception $exception) {
                $log->error('error updateOrCreate user, but continue process', [
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        $order_full_info = [
            'order' => $order,
            'items' => $items,
            'connection' => $connection
        ];

        $woo_order_id = $order['order_id'] . '-' . $connection;

        try {
            $order_id = Order::create([
                'order_id' => $woo_order_id,
                'uuid' => Str::uuid()->toString(),
                'status' => 'wc-processing',
                'sub_status' => 'wc-processing',
                'info' => $order_full_info,
                'client_info' => $client_info,
                'user_id' => $user->id ?? null
            ])->id;
        } catch (\Exception $e) {

            $log->error('create order error', [
                'exception' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }

        $log->debug('order created', [$order_id]);

        $log->info('order items creating');

        if (empty($items)) {

            $log->error('items is empty');

            return [
                'success' => false,
                'error' => 'items is empty',
            ];
        }

        foreach ($items as $item) {

            try {
                $key = GenerateSecureCode::generate();
            } catch (RandomException $e) {
                $log->error($e->getMessage());

                return [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }

            $keys_data[] = [
                'key' => $key,
                'name' => data_get($item, "order_item_name"),
            ];

            $sku = data_get($item, 'meta._sku');

            if (!$sku) {

                $log->info('create TEMP sku');

                $sku = 'temp' . '-' . Str::random(10) . '-' . $connection;

                PlayStationAlt::create([
                    'sku' => $sku,
                    'region_id' => '0f63f19f-fb73-4e9f-8f77-5a51d0d70009',
                    'base_price' => data_get($item, 'product._line_subtotal') * 100,
                    'price_with_discount' => data_get($item, 'product._line_total') * 100,
                    'name' => data_get($item, "order_item_name"),
                    'is_manual' => 1,
                    'woo_price_rub' => data_get($item, 'product._line_total') * 100,
                    'woo_price_try' => data_get($item, 'meta._price_try') * 100,
                ]);

            } else {

                $log->info('search product by sku');

                $product = PlayStationAlt::where('sku', $sku)->first();

                if (!$product) {
                    $log->info('product not found by sku');

                    PlayStationAlt::create([
                        'sku' => $sku,
                        'region_id' => '0f63f19f-fb73-4e9f-8f77-5a51d0d70009',
                        'base_price' => data_get($item, 'product._line_subtotal') * 100,
                        'price_with_discount' => data_get($item, 'product._line_total') * 100,
                        'name' => data_get($item, "order_item_name"),
                        'is_manual' => 1,
                        'woo_price_rub' => data_get($item, 'product._line_total') * 100,
                        'woo_price_try' => data_get($item, 'meta._price_try') * 100,
                    ]);

                    $log->debug('product created by sku', [$sku]);
                } else {
                    $log->debug('product found by sku', [$product]);

                    PlayStationAlt::where('sku', $sku)->update([
                        'woo_price_rub' => data_get($item, 'product._line_total') * 100,
                        'woo_price_try' => data_get($item, 'meta._price_try') * 100,
                    ]);
                }
            }

            $insert_data[] = [
                'key' => $key,
                'uuid' => Str::uuid()->toString(),
                'order_id' => $order_id,
                'activate_till' => now()->addYear()->format('Y-m-d'),
                'sku' => $sku,
                'count' => data_get($item, 'product._qty', 1),
                'created_at' => now(),
                'updated_at' => now(),
            ];

        }

        $log->debug('keys', [$keys_data]);
        $log->debug('insert_data', [$insert_data]);

//        $order = Order::where('order_id', $order_id)->first();

        try {

            \DB::beginTransaction();

            OrderItems::insert($insert_data);

//            $service->provideOrderDigitalCodes(keys: $keys_data, campaignId: $data['campaignId'], orderId: $data['orderId']);

//            $order->update([
//                'status' => 'PROCESSING',
//                'sub_status' => $data['substatus']
//            ]);

            \DB::commit();

        } catch (\Exception $e) {

            \DB::rollBack();

            $log->error('provideOrderDigitalCodes', [$e->getMessage()]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }

        try {

            $log->info('send instruction to smtp');

            Mail::send('instruction_with_code', [
                'keys_data' => $keys_data,
                'first_name' => $order['billing_first_name'],
                'order_id' => $order_id,
            ], function ($message) use ($order) {

                $from_name = Settings::get('SMTP_FROM_NAME', 'Магазин 1GROS');
                $subject = Settings::get('SMTP_SUBJECT', 'Ваш код активации');

                $message->to($order['billing_email'])
                    ->from(config('mail.from.address'), $from_name)
                    ->subject($subject);
            });

        } catch (\Exception $e) {

            $log->error('send instruction to smtp error', [$e->getMessage()]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }

        $log->info('success');

        SendTelegramJob::dispatchSync(order_id: $woo_order_id, status: 'new');

        return [
            'success' => true,
            'order_id' => $order_id,
        ];
    }

    /**
     * @param array $data
     * @return array|true[]
     */
    public function updated(array $data): array
    {
        $log = $this->log;

        $log->debug('updated data', [$data]);

        $order = Order::where('order_id', $data['orderId'])
            ->first();

        if (!$order) {
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
                'code' => 1
            ];
        }

        if ($data['status'] === 'PROCESSING') {

            $log->info('status PROCESSING');

            $order_info = $order->info;

            $keys_data = [];
            $insert_data = [];

            $service = new YmService($order->shop);

            foreach ($order_info['items'] as $item) {

                try {
                    $key = GenerateSecureCode::generate($order->shop?->voucher_prefix);
                } catch (RandomException $e) {
                    $log->error($e->getMessage());

                    return [
                        'success' => false,
                        'error' => $e->getMessage(),
                    ];
                }

                $keys_data[] = [
                    'id' => data_get($item, 'id'),
                    'activate_till' => now()->addYear()->format('Y-m-d'),
                    'slip' => view('instruction')->render(),
                    'codes' => [$key]
                ];

                $type_form_id = PlayStationAlt::where("sku", data_get($item, 'offerId'))->value('type_form_id');

                $insert_data[] = [
                    'key' => $key,
                    'uuid' => Str::uuid()->toString(),
                    'order_id' => $order->id,
                    'activate_till' => now()->addYear()->format('Y-m-d'),
                    'sku' => data_get($item, 'offerId'),
                    'count' => data_get($item, 'count'),
                    'created_at' => now(),
                    'updated_at' => now(),
                    'type_form_id' => $type_form_id
                ];
            }

            $log->debug('keys', [$keys_data]);
            $log->debug('insert_data', [$insert_data]);

            try {

                \DB::beginTransaction();

                OrderItems::insert($insert_data);

                $service->provideOrderDigitalCodes(keys: $keys_data, campaignId: $data['campaignId'], orderId: $data['orderId']);

                $order->update([
                    'status' => 'PROCESSING',
                    'sub_status' => $data['substatus']
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
                    $service->sendMessage($order->chat_id, view('chat.finish_message')->render());
                    $log->debug('success send YM finish Message');
                } catch (ConnectionException $e) {
                    $log->error('sendMessage finish', [
                        'exception' => $e->getMessage(),
                    ]);
                }
            }

            $log->info('success');

            SendTelegramJob::dispatchSync(order_id: $data['orderId'], status: 'new');

            return [
                'success' => true,
            ];

        } else if ($data['status'] === 'DELIVERY' || $data['status'] === 'DELIVERED') {

            $log->info('status DELIVERED or DELIVERY');

            $order->update([
                'status' => $data['status'],
                'sub_status' => $data['substatus']
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

    /**
     * @param array $data
     * @return array
     */
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
                'code' => 1
            ];
        }

        $shop = isset($data['shop_id']) ? \App\Models\Shop::find($data['shop_id']) : null;
        $service = new YmService($shop);

        try {
            $order_full_info = $service->getOrder(campaignId: $data['campaignId'], orderId: $data['orderId']);
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
            $client_info = $service->getOrderBuyerInfo(campaignId: $data['campaignId'], orderId: $data['orderId']);
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
                $service->sendMessage($chat_id, view('chat.start_message')->render());
                $log->debug('success send YM Message');
            } catch (ConnectionException $e) {
                $log->error('sendMessage YM', [
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        try {
            $order_id = Order::create([
                'order_id' => $data['orderId'],
                'uuid' => Str::uuid()->toString(),
                'info' => $order_full_info,
                'client_info' => $client_info,
                'chat_id' => $chat_id ?? null,
                'user_id' => $user->id ?? null,
                'shop_id' => $data['shop_id'] ?? null,
            ])->id;
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

            if(!$sku) {
                continue;
            }

            try {
                PlayStationAlt::updateOrCreate([
                    'sku' => $sku,
                ], [
                    'region_id' => '0f63f19f-fb73-4e9f-8f77-5a51d0d70009',
                    'base_price' => data_get($item, 'price') * 100,
                    'price_with_discount' => data_get($item, 'buyerPrice') * 100,
                    'name' => data_get($item, "order_item_name"),
                    'is_manual' => 1,
                    'type_form_id' => str_starts_with($sku, 'VOUCHER-') ? 2 : 1
                ]);
            } catch (\Exception  $e) {

                $log->error('update product error, but continue', [
                    'exception' => $e->getMessage(),
                    'item' => $item
                ]);

                continue;
            }
        }

        $log->debug('created', [
            'order_id' => $order_id
        ]);

        return [
            'success' => true,
            'order_id' => $order_id
        ];
    }
}
