<?php

namespace App\Console\Commands;

use App\Http\Controllers\OrderController;
use App\Models\Order\Order;
use App\Models\WooSyncedOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Console\Command\Command as CommandAlias;

class WooNewOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'woo-new-orders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Мониторит новые заказы из WooCommerce';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $log = Log::channel('monitor_new_orders_woo')->withContext([
            'log_id' => Str::random(8),
        ]);

        $db_connection = [
            'ps_plus',
            'ps_store',
            '1gros_prod'
        ];

        foreach ($db_connection as $connection) {
            $db_connection = DB::connection($connection);

//            $log->info("Проверка новых заказов в базе {$connection}");

            $orders = $db_connection->table('wp_posts as p')
                ->select(
                    'p.ID as order_id',
                    'p.post_date as order_date',
                    DB::raw("MAX(CASE WHEN pm.meta_key = '_billing_first_name' THEN pm.meta_value END) as billing_first_name"),
                    DB::raw("MAX(CASE WHEN pm.meta_key = '_billing_last_name' THEN pm.meta_value END) as billing_last_name"),
                    DB::raw("MAX(CASE WHEN pm.meta_key = '_billing_email' THEN pm.meta_value END) as billing_email"),
                    DB::raw("MAX(CASE WHEN pm.meta_key = '_billing_phone' THEN pm.meta_value END) as billing_phone"),
                    DB::raw("MAX(CASE WHEN pm.meta_key = '_order_total' THEN pm.meta_value END) as order_total")
                )
                ->leftJoin('wp_postmeta as pm', 'pm.post_id', '=', 'p.ID')
                ->where('p.post_type', 'shop_order')
                ->whereIn('p.post_status', ['wc-processing', 'wc-completed'])
                ->where('p.post_date', '>=', now()->subWeek())
                ->groupBy('p.ID')
                ->get();

            if ($orders->isEmpty()) {
//                $log->info("Новых заказов не найдено");
                continue;
            }

            foreach ($orders as $order) {

                $alreadyProcessed = WooSyncedOrder::where('woo_order_id', $order->order_id)->where('connection', $connection)->exists();

                if ($alreadyProcessed) {
//                    $log->info("Пропущен заказ #{$order->order_id} (уже обработан)");
                    continue;
                }

                $log->info("-------------------");

                $log->info("Новый заказ: #{$order->order_id} в {$connection}");

                // товары заказа
                $items = $db_connection
                    ->table('wp_woocommerce_order_items')
                    ->where('order_id', $order->order_id)
                    ->where('order_item_type', 'line_item')
                    ->get();

                foreach ($items as $item) {
                    $product = $db_connection
                        ->table('wp_woocommerce_order_itemmeta')
                        ->where('order_item_id', $item->order_item_id)
                        ->pluck('meta_value', 'meta_key');

                    $item->product = $product;

                    if ($product && isset($product['_product_id'], $product['_variation_id'])) {

                        $query = $db_connection->table('wp_postmeta');

                        if (isset($product['_variation_id']) && $product['_variation_id'] != '0') {
                            $query->where('post_id', $product['_variation_id']);
                        } else {
                            $query->where('post_id', $product['_product_id']);
                        }

                        $item->meta = $query
                            ->where('meta_key', '_sku')
                            ->select([
                                'meta_value as sku',
                                '_price_try as price_try'
                            ])
                            ->first();
                    }
                }

                $log->debug("Тело заказа", ['order' => $order, 'items' => $items]);

                $order_controller = new OrderController('CREATED_FROM_WOO');

                $result = $order_controller->createdFromWoo(order: (array)$order, items: $items->toArray(), connection: $connection);

                $log->debug("Результат создания заказа", ['result' => $result]);

                if ($result['success']) {

                    $log->info("Заказ успешно создан");

                    $db_connection->table('wp_posts as p')
                        ->where('ID', $order->order_id)
                        ->update([
                            'post_status' => 'wc-completed',
                            'post_modified' => now()->format('Y-m-d H:i:s'),
                            'post_modified_gmt' => now()->format('Y-m-d H:i:s'),
                        ]);

                    $log->info("Заказ в Woo обновлен");

                    Order::where('id', $result['order_id'])->update([
                        'status' => 'wc-completed',
                        'sub_status' => 'wc-completed',
                        'code_activated' => true
                    ]);

                    $log->info("Заказ в системе обновлен");

                    $log->debug("Заказа {$order->order_id} успешно обработан");
                } else {
                    $log->error("Заказа {$order->order_id} не успешно обработан");
                }

                WooSyncedOrder::create([
                    'woo_order_id' => $order->order_id,
                    'connection' => $connection,
                    'created_result' => json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                    'created_success' => $result['success']
                ]);

            }
        }


        return CommandAlias::SUCCESS;
    }
}
