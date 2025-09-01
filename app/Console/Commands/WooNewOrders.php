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

            $orders = $db_connection->table('wp_posts')->where('post_type', '=', 'shop_order')
                ->where('post_date', '>=', now()->subHours(2))
                ->get();

            foreach ($orders as $order) {
                $alreadyProcessed = WooSyncedOrder::where('woo_order_id', $order->ID)->where('connection', $connection)->exists();

                if ($alreadyProcessed) {
                    $log->info("Пропущен заказ #{$order->ID} (уже обработан)");
                    continue;
                }

                $log->info("Новый заказ: #{$order->ID}");

                $items = $db_connection->table('wp_woocommerce_order_items')
                    ->where('order_id', $order->ID)
                    ->get();

                foreach ($items as $item) {
                    $meta = $db_connection
                        ->table('wp_woocommerce_order_itemmeta')
                        ->where('order_item_id', $item->order_item_id)
                        ->pluck('meta_value', 'meta_key'); // сразу key => value

                    $item->meta = $meta;
                }

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
