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
                // Проверяем — был ли заказ уже обработан
                $alreadyProcessed = WooSyncedOrder::where('woo_order_id', $order->ID)->exists();

                if ($alreadyProcessed) {
                    $log->info("Пропущен заказ #{$order->ID} (уже обработан)");
                    continue;
                }

                // --- тут твоя логика обработки ---
                $log->info("Новый заказ: #{$order->ID}");
                $log->debug("Тело заказа", ['order' => $order]);

                // Записываем, что заказ обработан
                WooSyncedOrder::create([
                    'woo_order_id' => $order->ID,
                ]);
            }
        }


        return CommandAlias::SUCCESS;
    }
}
