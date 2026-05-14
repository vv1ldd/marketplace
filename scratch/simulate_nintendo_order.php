<?php

use App\Jobs\ProcessYmNotification;
use App\Models\Shop;
use App\Models\Product;
use Illuminate\Support\Facades\Log;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// --- Configuration ---
$shopId = 1;
$sku = 'WF-NINTENDO-10-USD-US-C60-JZ0L';
$orderId = 'SIM-' . time();
$campaignId = '123456';

echo "🚀 Starting Simulation for Order: $orderId\n";

$shop = Shop::find($shopId);
if (!$shop) {
    die("❌ Shop $shopId not found\n");
}

$product = Product::where('sku', $sku)->where('shop_id', $shopId)->first();
if (!$product) {
    die("❌ Product $sku not found in shop $shopId\n");
}

echo "📦 Target Product: {$product->name} (Price: {$product->price_rub} RUB)\n";

// --- 1. Manually create Order Record (Bypassing API check) ---
echo "📝 Step 1: Creating manual Order record...\n";
$order = \App\Models\Order\Order::create([
    'order_id' => $orderId,
    'uuid' => \Illuminate\Support\Str::uuid()->toString(),
    'shop_id' => $shopId,
    'status' => 'RESERVED',
    'sub_status' => 'RESERVED',
    'progress_id' => 1,
    'is_test' => true,
    'client_info' => ['email' => 'test@example.com', 'id' => 12345],
    'info' => [
        'items' => [
            [
                'id' => 'item-1',
                'offerId' => $sku,
                'count' => 1,
                'price' => $product->price_rub / 100,
                'buyerPrice' => $product->price_rub / 100,
            ]
        ]
    ]
]);

if ($order) {
    echo "✅ Order record created in DB.\n";
} else {
    die("❌ Failed to create order record.\n");
}

// --- 3. Simulate ORDER_STATUS_UPDATED to PROCESSING ---
echo "💸 Step 2: Simulating ORDER_STATUS_UPDATED (PROCESSING) -> Voucher linked, but HOLD remains...\n";
$updateData = [
    'notificationType' => 'ORDER_STATUS_UPDATED',
    'orderId' => $orderId,
    'status' => 'PROCESSING',
    'substatus' => 'STARTED',
    'campaignId' => $campaignId,
];

$updateJob = new ProcessYmNotification($updateData);
try {
    $updateJob->handle();
    echo "✅ Order processed. Voucher linked. (Check Finance: reserved_balance should still have the money)\n";
} catch (\Exception $e) {
    echo "❌ Step 2 Error: " . $e->getMessage() . "\n";
}

// --- 4. Simulate ACTIVATION (Redeem) ---
echo "🔑 Step 3: Simulating CUSTOMER ACTIVATION (Redeem) -> This triggers ACTUAL CAPTURE...\n";
$orderItem = \App\Models\Order\OrderItems::where('order_id', $order->id)->first();
$customer = \App\Models\Customer::first() ?? \App\Models\Customer::create(['email' => 'test@example.com']);

// We use the dev demo mode to avoid hitting real Wildflow for code issuance
$order->update(['info' => array_merge($order->info, ['dev_async_redeem_demo' => true])]);

$redeemJob = new \App\Jobs\ProcessRedeemWildflowPurchase($orderItem->id, $customer->id, false);
try {
    $redeemJob->handle(app(\App\Services\RedeemFallbackPurchaseService::class));
    echo "🎯 Activation complete. Check Finance: reserved_balance should be DECREASED now!\n";
} catch (\Exception $e) {
    echo "❌ Step 3 Error: " . $e->getMessage() . "\n";
}

echo "\n🏁 Full Cycle Simulation Complete!\n";
echo "Check Finance page and Order comments (there should be two: hold and capture).\n";
