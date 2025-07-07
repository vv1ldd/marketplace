<?php

namespace App\Console\Commands;

use App\Http\Controllers\Ym\MainController;
use App\Http\Services\YmService;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CheckNewOrderFromYM extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ps:check-new-order-from-y-m';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $log = \Log::channel('ym_check_orders')->withContext(['log_id' => Str::random(8),]);

        $ymService = new YmService();

        $ym_controller = new MainController();

//        $log->info('check new orders');

        try {
            $new_orders = $ymService->getNewOrders();
        } catch (ConnectionException $e) {
            $log->error('getNewOrders error', [$e->getMessage()]);
            return;
        }

        $campaign_id = config('services.ym.campaign_id', 143486522);

//        $log->debug('check new orders', [$new_orders, $campaign_id])

        if (empty($new_orders)) {
//            $log->info('no new orders');
            return;
        } else {
            $log->debug('new orders', [$new_orders]);
        }

        foreach ($new_orders as $order) {

            $order_id = data_get($order, 'id');

            $order_exist = Order::where('order_id', $order_id)->exists();

            if ($order_exist) {
                $log->debug('order already exist', [$order_id]);
                continue;
            }

            $log->debug('new order', [$order_id]);

            $request = new Request();

            $request->merge([
                'notificationType' => 'ORDER_CREATED',
                'orderId' => $order_id,
                'campaignId' => $campaign_id,
            ]);

            $log->debug('ORDER_CREATED request data', [$request->all()]);

            $response = $ym_controller->notification($request);

            $log->debug('ORDER_CREATED response', [$response->body()]);

            if ($response->status() !== 200) {
                $log->error('Error order created from YM', ['response' => $response->body(), 'request' => $request->all()]);
                continue;
            }

            $request = new Request();

            $request->merge([
                'notificationType' => 'ORDER_STATUS_UPDATED',
                'orderId' => $order_id,
                'campaignId' => $campaign_id,
                'status' => 'PROCESSING',
                'substatus' => 'STARTED',
            ]);

            $log->debug('ORDER_STATUS_UPDATED request data', [$request->all()]);

            $response = $ym_controller->notification($request);

            $log->debug('ORDER_STATUS_UPDATED response', [$response->body()]);

            if ($response->status() !== 200) {
                $log->error('Error order status updated from YM', ['response' => $response->body(), 'request' => $request->all()]);
                continue;
            }

            $log->debug('new order created', [$order_id]);
        }
    }
}
