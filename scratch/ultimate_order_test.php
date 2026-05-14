<?php
// BOOTSTRAP LARAVEL
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\WildflowService;

echo "🔥 LAUNCHING END-TO-END INTEGRATION TEST 🔥\n";

$service = new WildflowService();

$testReference = 'test_auto_' . time();
$sku = '0214600000203'; // Mobile Legends Global
$price = 0.2;

echo "🛒 Attempting to purchase: Mobile Legends [SKU: $sku] for $price USD...\n";
echo "🔗 Reference: $testReference\n";

try {
    $order = $service->createOrder(
        service_sku: $sku,
        order_item_id: $testReference,
        price: $price,
        quantity: 1,
        pre_order: false, // Try instant delivery!
        provider: 'ezpin'
    );

    echo "\n✅ SUCCESS!!! THE CIRCUITS ARE LIVE!!!\n";
    echo "----------------------------------------\n";
    print_r($order);
    
    echo "\n\n💡 NEXT STEP: Fetching PIN for our generated reference...\n";
    
    sleep(2); // Give vendor 2 secs to serialize the pin
    
    $cards = $service->getCards($testReference, 'ezpin');
    echo "🎯 CARDS RECEIVED:\n";
    print_r($cards);

} catch (\Exception $e) {
    echo "\n❌ ORDER FAILED OR BLOCKED:\n";
    echo $e->getMessage() . "\n";
}

echo "\n🏁 TEST CYCLE FINISHED.\n";
