<?php

use App\Models\Order\Order;
use App\Models\Shop;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$legalEntityId = 5; // CJS GROUP
$shops = Shop::where('legal_entity_id', $legalEntityId)->get();

echo "\n🦙 LAMA DATA AUDIT - CJS GROUP (LE 5) 🦙\n";
echo "========================================\n";

foreach ($shops as $shop) {
    $totalOrders = Order::where('shop_id', $shop->id)->count();
    $zeroTotals = Order::where('shop_id', $shop->id)->where('total_amount_base', 0)->count();
    $noCurrency = Order::where('shop_id', $shop->id)->whereNull('currency')->count();
    $noItems = Order::where('shop_id', $shop->id)->doesntHave('items')->count();
    
    echo sprintf(
        "Shop: [%2d] %-30s | Total: %5d | Zero Sum: %5d | No Curr: %5d | No Items: %5d\n",
        $shop->id,
        $shop->name,
        $totalOrders,
        $zeroTotals,
        $noCurrency,
        $noItems
    );
}

echo "\n--- Gaps in JSON 'info' column ---\n";
foreach ($shops as $shop) {
    // Check for order_total in various JSON paths
    $hasOrderTotalInInfo = Order::where('shop_id', $shop->id)
        ->where('total_amount_base', 0)
        ->where(function($q) {
            $q->whereNotNull('info->order->order_total')
              ->orWhereNotNull('info->total')
              ->orWhereNotNull('info->order_total');
        })
        ->count();
        
    if ($hasOrderTotalInInfo > 0) {
        echo "⚠️ Shop {$shop->id} ({$shop->name}): Found $hasOrderTotalInInfo orders with 'order_total' in JSON that are still ZERO in DB!\n";
    }
}

echo "\nAudit Complete.\n";
