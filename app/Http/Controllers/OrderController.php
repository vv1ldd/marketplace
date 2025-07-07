<?php

namespace App\Http\Controllers;

use App\Helpers\GenerateSecureCode;
use App\Helpers\SendMessage;
use App\Http\Services\TelegramService;
use App\Http\Services\YmService;
use App\Models\Order;
use App\Models\OrderItems;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
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

        if ($order->status !== 'NEW') {
            $log->error('current order status not NEW', [$order]);

            return [
                'success' => false,
                'error' => 'current order status not NEW',
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

                $insert_data[] = [
                    'key' => $key,
                    'order_id' => $order->id,
                    'activate_till' => now()->addYear()->format('Y-m-d'),
                    'sku' => data_get($item, 'offerId'),
                    'count' => data_get($item, 'count'),
                    'created_at' => now(),
                    'updated_at' => now(),
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

            try {

                $order = Order::where('order_id', $data['orderId'])->first();

                $message = SendMessage::tg($order);

                (new TelegramService())->sendMessage($message);
            } catch (ConnectionException $e) {
                $log->error('Telegram error', [$e->getMessage()]);
            }

            return [
                'success' => true,
            ];

        } else if ($data['status'] === 'DELIVERED') {

            $log->info('status DELIVERED');

            $order->update([
                'status' => 'DELIVERED',
                'sub_status' => $data['substatus']
            ]);

            return [
                'success' => true,
            ];

        } else {

            $log->error('status not PROCESSING or DELIVERED');

            return [
                'success' => false,
                'error' => 'status not PROCESSING',
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
            $order_id = Order::create([
                'order_id' => $data['orderId'],
                'uuid' => Str::uuid()->toString(),
                'info' => $order_full_info,
                'client_info' => $client_info,
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
