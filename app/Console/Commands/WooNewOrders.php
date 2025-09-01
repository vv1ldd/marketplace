<?php

namespace App\Console\Commands;

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
            'ps_store',
            'ps_plus',
            '1gros_prod'
        ];

        foreach ($db_connection as $connection) {
            $db_connection = DB::connection($connection);

            $log->info("Проверка новых заказов в базе {$connection}");

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
                ->join('wp_postmeta as pm', 'pm.post_id', '=', 'p.ID')
                ->where('p.post_type', 'shop_order')
                ->where('p.post_date', '>=', now()->subHours(2))
                ->groupBy('p.ID')
                ->get();

            foreach ($orders as $order) {

                $alreadyProcessed = WooSyncedOrder::where('woo_order_id', $order->order_id)->where('connection', $connection)->exists();

                if ($alreadyProcessed) {
                    $log->info("Пропущен заказ #{$order->order_id} (уже обработан)");
                    continue;
                }

                $log->info("Новый заказ: #{$order->order_id}");

                // товары заказа
                $items = $db_connection
                    ->table('wp_woocommerce_order_items')
                    ->where('order_id', $order->order_id)
                    ->get();


                foreach ($items as $item) {
                    $meta = $db_connection
                        ->table('wp_woocommerce_order_itemmeta')
                        ->where('order_item_id', $item->order_item_id)
                        ->pluck('meta_value', 'meta_key');

                    $item->meta = $meta;

//                    $item->product = $db_connection->table('wp_postmeta')
//                        ->where('post_id', $item->meta['_product_id'])
//                        ->first();
                }

                $log->debug("Тело заказа", ['order' => $order, 'items' => $items]);

                WooSyncedOrder::create([
                    'woo_order_id' => $order->order_id,
                    'connection' => $connection
                ]);
            }
        }


        return CommandAlias::SUCCESS;
    }
}
