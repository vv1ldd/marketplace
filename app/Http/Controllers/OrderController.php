<?php

namespace App\Http\Controllers;

use App\Helpers\GenerateSecureCode;
use App\Http\Services\YmService;
use App\Jobs\SendTelegramJob;
use App\Models\Order\Order;
use App\Models\Order\OrderItems;
use App\Models\PlayStation\PlayStationAlt;
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
    }

    public function createdFromWoo(array $order, array $items)
    {
        $log = $this->log;

        $log->debug('createFromWoo data', ['order' => $order, 'items' => $items]);

        dd($order, $items);

        try {
            $order_id = Order::create([
                'order_id' => $data['orderId'],
                'uuid' => Str::uuid()->toString(),
                'info' => $order_full_info,
                'client_info' => $client_info,
                'chat_id' => $chat_id ?? null,
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

            $service = new YmService();

            foreach ($order_info['items'] as $item) {

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

        $service = new YmService();

        try {
            $order_full_info = $service->getOrder(campaignId: $data['campaignId'], orderId: $data['orderId']);
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
                'user_id' => $user->id ?? null
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

        $log->debug('created', [
            'order_id' => $order_id
        ]);

        return [
            'success' => true,
            'order_id' => $order_id
        ];
    }
}
