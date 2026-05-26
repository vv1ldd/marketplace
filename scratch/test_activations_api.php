<?php

// Boot Laravel application
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Seller;
use App\Models\Shop;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\Procurement;
use Illuminate\Support\Facades\Auth;

echo "=========================================\n";
echo "🚀 STARTING B2B ACTIVATIONS API INTEGRATION TEST\n";
echo "=========================================\n\n";

// 1. Authenticate as merchant (Seller email: admin@admin.com)
$user = Seller::where('email', 'admin@admin.com')->first();
if (!$user) {
    $user = Seller::first();
}
if (!$user) {
    echo "❌ Error: Seller not found in database!\n";
    exit(1);
}
Auth::login($user);
echo "✔ Authenticated as seller: " . $user->email . "\n";

$legalEntity = $user->legalEntities()->first();
if (!$legalEntity) {
    echo "❌ Error: Legal entity profile not found!\n";
    exit(1);
}
echo "✔ Active Legal Entity: " . $legalEntity->name . " (ID: " . $legalEntity->id . ")\n";
echo "💰 Available Balance: " . $legalEntity->available_balance . " RUB\n\n";

// 2. Fetch Activations List Data
echo "📋 Testing B2B Activations Listing...\n";
$controller = new \App\Http\Controllers\PartnerDashboardController();

$request = Illuminate\Http\Request::create('http://' . config('app.domain') . '/partner/dashboard/activations/data', 'GET', [
    'page' => 1,
    'status' => 'all'
]);
$response = $controller->getActivationsData($request);
$data = json_decode($response->getContent(), true);

if ($response->getStatusCode() === 200 && ($data['success'] ?? false)) {
    echo "✔ Activations list loaded successfully.\n";
    echo "✔ Found activations count: " . count($data['activations']) . "\n";
    echo "✔ Pagination total: " . $data['total'] . "\n";
} else {
    echo "❌ Failed to load activations list! Code: " . $response->getStatusCode() . "\n";
    print_r($data);
    exit(1);
}

// 3. Find target Shop, Product, and Warehouse to test creation
$shop = Shop::where('legal_entity_id', $legalEntity->id)->first();
if (!$shop) {
    echo "❌ Error: Active shop belonging to legal entity not found!\n";
    exit(1);
}
echo "\n🎯 Targeting Shop: " . $shop->name . " (ID: " . $shop->id . ")\n";

// Test Shop Options picker loading
echo "📋 Testing Shop Options picker (Products & Warehouses)...\n";
$optResponse = $controller->getShopOptions($shop->id);
$optData = json_decode($optResponse->getContent(), true);

if ($optResponse->getStatusCode() === 200 && ($optData['success'] ?? false)) {
    echo "✔ Shop options loaded successfully.\n";
    echo "✔ Loaded products: " . count($optData['products']) . "\n";
    echo "✔ Loaded warehouses: " . count($optData['warehouses']) . "\n";
} else {
    echo "❌ Failed to load shop options! Code: " . $optResponse->getStatusCode() . "\n";
    exit(1);
}

$product = Product::where('shop_id', $shop->id)->where('is_active', true)->where('purchase_price_rub', '>', 0)->first();
if (!$product) {
    $product = Product::where('shop_id', $shop->id)->where('is_active', true)->first();
}
$warehouse = Warehouse::where('shop_id', $shop->id)->where('is_active', true)->first();

if (!$product || !$warehouse) {
    echo "❌ Error: Active product or warehouse not found for testing activation creation!\n";
    exit(1);
}

$origPrice = $product->purchase_price_rub;
$product->purchase_price_rub = 50000; // Mock 500.00 RUB
$product->save();

echo "🎯 Targeting Product: " . $product->name . " (SKU: " . $product->sku . ", Price: " . ($product->purchase_price_rub / 100) . " RUB)\n";
echo "🎯 Targeting Warehouse: " . $warehouse->name . " (ID: " . $warehouse->id . ")\n\n";

// 4. Test Balance Validation Rule (Create request with excessive count)
echo "💸 Testing Balance Enforcement Validation (buying 999999 items)...\n";
$exceedRequest = Illuminate\Http\Request::create('http://' . config('app.domain') . '/partner/dashboard/activations/create', 'POST', [
    'shop_id' => $shop->id,
    'product_id' => $product->id,
    'warehouse_id' => $warehouse->id,
    'count' => 999999
]);
$exceedResponse = $controller->createActivation($exceedRequest);
$exceedData = json_decode($exceedResponse->getContent(), true);

if ($exceedResponse->getStatusCode() === 400 && str_contains($exceedData['error'] ?? '', 'Недостаточно средств')) {
    echo "✔ Balance validation enforcement successfully caught excessive request: " . $exceedData['error'] . "\n";
} else {
    echo "❌ Failed balance validation check! Response: " . $exceedResponse->getStatusCode() . "\n";
    print_r($exceedData);
    exit(1);
}

// 5. Test Successful Creation of Pending Activation request
echo "\n🚀 Creating Valid Activation Request (Count: 1)...\n";
$createRequest = Illuminate\Http\Request::create('http://' . config('app.domain') . '/partner/dashboard/activations/create', 'POST', [
    'shop_id' => $shop->id,
    'product_id' => $product->id,
    'warehouse_id' => $warehouse->id,
    'count' => 1
]);
$createResponse = $controller->createActivation($createRequest);
$createData = json_decode($createResponse->getContent(), true);

if ($createResponse->getStatusCode() === 200 && ($createData['success'] ?? false)) {
    $procurementId = $createData['procurement_id'];
    echo "✔ Activation request created successfully! Procurement ID: " . $procurementId . "\n";
    echo "✔ Message: " . $createData['message'] . "\n";

    // Retrieve created model from database to verify integrity
    $procurement = Procurement::find($procurementId);
    if ($procurement && $procurement->status === 'pending') {
        echo "✔ Verified database entry: Procurement Status is pending, total price = " . ($procurement->total_price / 100) . " RUB.\n";
        
        // Clean up test procurement request to keep database clean
        $procurement->delete();
        echo "🧹 Cleaned up testing procurement record from database.\n";
    } else {
        echo "❌ Procurement record database verification failed!\n";
        exit(1);
    }
} else {
    echo "❌ Failed to create activation request! Code: " . $createResponse->getStatusCode() . "\n";
    print_r($createData);
    exit(1);
}

// Restore original product price in database
$product->purchase_price_rub = $origPrice;
$product->save();
echo "🧹 Restored original product purchase price in database.\n";

echo "\n=========================================\n";
echo "🎉 ALL B2B ACTIVATIONS SPA INTEGRATION TESTS PASSED!\n";
echo "=========================================\n";
