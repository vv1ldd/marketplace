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
            'ps_plus',
            'ps_store',
            '1gros_prod'
        ];

        foreach ($db_connection as $connection) {
            $db_connection = DB::connection($connection);

            $log->info("Проверка новых заказов в базе {$connection}");

            $orders = DB::table('wp_posts as p')
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
                $alreadyProcessed = WooSyncedOrder::where('woo_order_id', $order->ID)->where('connection', $connection)->exists();

                if ($alreadyProcessed) {
                    $log->info("Пропущен заказ #{$order->ID} (уже обработан)");
                    continue;
                }

                $log->info("Новый заказ: #{$order->ID}");

                // товары заказа
                $items = DB::table('wp_woocommerce_order_items as oi')
                    ->select(
                        'oi.order_item_id',
                        'oi.order_item_name as product_name',
                        DB::raw("MAX(CASE WHEN oim.meta_key = '_product_id' THEN oim.meta_value END) as product_id"),
                        DB::raw("MAX(CASE WHEN oim.meta_key = '_qty' THEN oim.meta_value END) as quantity"),
                        DB::raw("MAX(CASE WHEN oim.meta_key = '_line_total' THEN oim.meta_value END) as total_price")
                    )
                    ->join('wp_woocommerce_order_itemmeta as oim', 'oi.order_item_id', '=', 'oim.order_item_id')
                    ->where('oi.order_id', $order->ID)
                    ->groupBy('oi.order_item_id', 'oi.order_item_name')
                    ->get();


//                foreach ($items as $item) {
//                    $meta = $db_connection
//                        ->table('wp_woocommerce_order_itemmeta')
//                        ->where('order_item_id', $item->order_item_id)
//                        ->pluck('meta_value', 'meta_key');
//
//                    $item->meta = $meta;
//
//                    $item->product = $db_connection->table('wp_postmeta')
//                        ->where('post_id', $item->meta['_product_id'])
//                        ->first();
//
//                }

                $log->debug("Тело заказа", ['order' => $order, 'items' => $items]);

                WooSyncedOrder::create([
                    'woo_order_id' => $order->ID,
                    'connection' => $connection
                ]);
            }
        }


        return CommandAlias::SUCCESS;
    }
}
