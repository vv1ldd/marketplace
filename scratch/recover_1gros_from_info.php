<?php

use App\Models\Order\Order;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$shopId = 10;
$updated = 0;
$total = Order::where('shop_id', $shopId)->count();

echo "Starting recovery from 'info' column for Shop ID $shopId (Total: $total)...\n";

Order::where('shop_id', $shopId)->chunk(100, function ($orders) use (&$updated) {
    foreach ($orders as $order) {
        $info = $order->info;
        if (isset($info['order']['order_total'])) {
            $totalAmount = (float)$info['order']['order_total'];
            $order->update([
                'total_amount' => $totalAmount,
                'total_amount_base' => $totalAmount,
                'currency' => 'RUB' // 1Gros is RUB
            ]);
            $updated++;
        }
    }
});

echo "Recovery complete!\n";
echo "Orders updated: $updated / $total\n";
