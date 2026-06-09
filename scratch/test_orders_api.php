<?php

use App\Models\Seller;
use App\Http\Controllers\PartnerDashboardController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// 1. Resolve active test partner user
$seller = Seller::where('email', 'admin@admin.com')->first();
if (!$seller) {
    $seller = Seller::first();
}

if (!$seller) {
    echo "❌ [FAIL] No seller user found in DB\n";
    exit(1);
}

echo "👤 [TEST] Operating as partner seller: {$seller->email} (ID: {$seller->id})\n";

// Authenticate session for controller calls
Auth::login($seller);

$controller = new PartnerDashboardController();

// 2. Validate getOrdersData
echo "\n📦 [TEST] Invoking getOrdersData API endpoint...\n";
$request = Request::create('/merchant/dashboard/orders/data', 'GET', [
    'status' => '',
    'search' => ''
]);

$response = $controller->getOrdersData($request);
$data = json_decode($response->getContent(), true);

if (isset($data['success']) && $data['success']) {
    echo "✅ [SUCCESS] getOrdersData returned success! Total orders: {$data['total']}\n";
    if (count($data['orders']) > 0) {
        $firstOrder = $data['orders'][0];
        echo "   👉 Sample Order: ID #{$firstOrder['order_id']} | Shop: {$firstOrder['shop_name']} | SKU: {$firstOrder['sku']} | Price: {$firstOrder['price_rub']} ₽ | Key: {$firstOrder['key']}\n";
        
        // 3. Validate getOrderDetails
        echo "\n👁️ [TEST] Invoking getOrderDetails API endpoint for Sample Order ID #{$firstOrder['id']}...\n";
        $detailsResponse = $controller->getOrderDetails($firstOrder['id']);
        $detailsData = json_decode($detailsResponse->getContent(), true);
        
        if (isset($detailsData['success']) && $detailsData['success']) {
            echo "✅ [SUCCESS] getOrderDetails loaded successfully!\n";
            $o = $detailsData['order'];
            echo "   👉 Client Name: {$o['buyer']['name']} | Email: {$o['buyer']['email']} | Phone: {$o['buyer']['phone']}\n";
            echo "   👉 Vouchers count: " . count($o['items']) . "\n";
            foreach ($o['items'] as $item) {
                echo "      - SKU: {$item['sku']} | Decrypted Key: {$item['key']} | Price: {$item['price_rub']} ₽\n";
            }
            echo "   👉 Audit Timeline entries: " . count($o['comments']) . "\n";
            foreach ($o['comments'] as $c) {
                echo "      - [{$c['created_at']}] {$c['text']}\n";
            }
        } else {
            echo "❌ [FAIL] getOrderDetails API returned error: " . json_encode($detailsData) . "\n";
        }
    } else {
        echo "ℹ️ [INFO] No orders exist yet to run details validation. Let's create one!\n";
    }
} else {
    echo "❌ [FAIL] getOrdersData API returned error: " . json_encode($data) . "\n";
}

// 4. Validate createSandboxOrder (Mock test order creation)
echo "\n🧪 [TEST] Invoking createSandboxOrder (Sandbox Order creation)...\n";
$sandboxRequest = Request::create('/merchant/dashboard/orders/sandbox', 'POST', [
    'sku' => 'STEAM-GIFT-100',
    'price_rub' => 5000,
    'code' => 'SANDBOX-TEST-CODE-0000'
]);

$sandboxResponse = $controller->createSandboxOrder($sandboxRequest);
$sandboxData = json_decode($sandboxResponse->getContent(), true);

if (isset($sandboxData['success']) && $sandboxData['success']) {
    echo "✅ [SUCCESS] createSandboxOrder executed successfully! Order ID: {$sandboxData['order_id']}\n";
    
    // Check if new sandbox order shows up in list
    $checkRequest = Request::create('/merchant/dashboard/orders/data', 'GET', ['status' => 'sandbox']);
    $checkResponse = $controller->getOrdersData($checkRequest);
    $checkData = json_decode($checkResponse->getContent(), true);
    
    echo "📈 [INFO] Total Sandbox Orders now: {$checkData['total']}\n";
    if (count($checkData['orders']) > 0) {
        $newSandbox = $checkData['orders'][0];
        echo "   👉 Newly Created Sandbox: ID #{$newSandbox['order_id']} | SKU: {$newSandbox['sku']} | Decrypted Key: {$newSandbox['key']}\n";
    }
} else {
    echo "❌ [FAIL] createSandboxOrder API returned error: " . json_encode($sandboxData) . "\n";
}

echo "\n🏁 [FINISH] Integration tests finished successfully!\n";
