<?php

namespace App\Console\Commands;

use App\Http\Controllers\Ym\MainController;
use App\Http\Services\YmService;
use App\Models\Order\Order;
use App\Models\Settings;
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
        $shops = \App\Models\Shop::where('is_active', true)->get();

        if ($shops->isEmpty()) {
            $log->info('No active shops found');
            // Fallback for one legacy shop if shops table is empty? 
            // For now, let's assume we started using shops table.
        }

        foreach ($shops as $shop) {
            $log->info("Checking orders for shop: {$shop->name} (Campaign: {$shop->campaign_id})");
            
            $ymService = new YmService($shop);
            $ym_controller = new MainController();

            try {
                $new_orders = $ymService->getNewOrders();
            } catch (ConnectionException $e) {
                $log->error("getNewOrders error for shop {$shop->name}", [$e->getMessage()]);
                continue;
            }

            if (empty($new_orders)) {
                continue;
            }

            $log->info("Found " . count($new_orders) . " new orders for shop {$shop->name}");

            foreach ($new_orders as $order) {
                $order_id = data_get($order, 'id');
                $order_exist = Order::where('order_id', $order_id)->where('shop_id', $shop->id)->exists();

                if ($order_exist) {
                    continue;
                }

                $log->debug('Processing new order', ['shop' => $shop->name, 'order_id' => $order_id]);

                $request = new Request();
                $request->merge([
                    'notificationType' => 'ORDER_CREATED',
                    'orderId' => $order_id,
                    'campaignId' => $shop->campaign_id,
                    'shop_id' => $shop->id, // Passing shop_id to controller
                ]);

                try {
                    $response = $ym_controller->notification($shop->notification_token, $request);
                } catch (\Exception $e) {
                    $log->error('Error ORDER_CREATED', ['shop' => $shop->name, 'e' => $e->getMessage(), 'order_id' => $order_id]);
                    continue;
                }

                if ($response->status() === 200) {
                    $request = new Request();
                    $request->merge([
                        'notificationType' => 'ORDER_STATUS_UPDATED',
                        'orderId' => $order_id,
                        'campaignId' => $shop->campaign_id,
                        'shop_id' => $shop->id,
                        'status' => 'PROCESSING',
                        'substatus' => 'STARTED',
                    ]);

                    try {
                        $ym_controller->notification($shop->notification_token, $request);
                    } catch (\Exception $e) {
                        $log->error('Error ORDER_STATUS_UPDATED', ['shop' => $shop->name, 'e' => $e->getMessage()]);
                    }
                }
            }
        }
    }
}
