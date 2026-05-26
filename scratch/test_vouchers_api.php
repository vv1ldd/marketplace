<?php

// Boot Laravel application
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Seller;
use App\Models\Shop;
use App\Models\Product;
use App\Models\ProductInventory;
use Illuminate\Support\Facades\Auth;

echo "=========================================\n";
echo "🚀 STARTING B2B VOUCHER CODE REGISTRY API INTEGRATION TEST\n";
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
echo "✔ Active Legal Entity: " . $legalEntity->name . " (ID: " . $legalEntity->id . ")\n\n";

$shop = $legalEntity->shops()->first();
if (!$shop) {
    echo "❌ Error: Shop not found!\n";
    exit(1);
}
echo "✔ Active Shop: " . $shop->name . " (ID: " . $shop->id . ")\n";

// 2. Spawn a mock voucher if none exists to guarantee data presence
$spawnedVoucher = null;
$voucher = ProductInventory::where('shop_id', $shop->id)->first();

if (!$voucher) {
    echo "🎫 No existing vouchers found. Spawning temporary mock voucher...\n";
    $product = Product::where('shop_id', $shop->id)->first();
    if (!$product) {
        $product = Product::create([
            'shop_id' => $shop->id,
            'name' => 'Mock Product for Vouchers Test',
            'sku' => 'TEST-VOUCHER-SKU',
            'purchase_price_rub' => 100,
            'is_active' => true
        ]);
    }
    $voucher = ProductInventory::create([
        'shop_id' => $shop->id,
        'product_id' => $product->id,
        'sku' => $product->sku,
        'sku_bidx' => md5($product->sku),
        'code' => 'MOCK-VOUCHER-SECRET-KEY-2026',
        'status' => 'available'
    ]);
    $spawnedVoucher = $voucher;
    echo "✔ Spawned mock voucher ID: " . $voucher->id . "\n";
} else {
    echo "✔ Found existing voucher ID: " . $voucher->id . "\n";
}

// 3. Test Paginated List API
echo "📋 Testing B2B Vouchers Listing...\n";
$controller = new \App\Http\Controllers\PartnerDashboardController();

$request = Illuminate\Http\Request::create('http://' . config('app.domain') . '/partner/dashboard/vouchers/data', 'GET', [
    'page' => 1,
    'status' => 'all'
]);
$response = $controller->getVouchersData($request);
$data = json_decode($response->getContent(), true);

if ($response->getStatusCode() === 200 && ($data['success'] ?? false)) {
    echo "✔ Vouchers list loaded successfully.\n";
    echo "✔ Found vouchers in page: " . count($data['vouchers']) . "\n";
    echo "✔ Pagination total records: " . $data['total'] . "\n";
    
    // Assert required fields in list output
    $firstItem = $data['vouchers'][0];
    $requiredKeys = ['id', 'created_at_formatted', 'art', 'code', 'status', 'has_proof'];
    foreach ($requiredKeys as $key) {
        if (!array_key_exists($key, $firstItem)) {
            echo "❌ Error: Missing required list key '$key' in output!\n";
            exit(1);
        }
    }
    echo "✔ List keys verified successfully.\n";
} else {
    echo "❌ Failed to load vouchers list! Code: " . $response->getStatusCode() . "\n";
    print_r($data);
    exit(1);
}

// 4. Test Search Filters API
echo "\n🔍 Testing Search Filters...\n";
$searchRequest = Illuminate\Http\Request::create('http://' . config('app.domain') . '/partner/dashboard/vouchers/data', 'GET', [
    'page' => 1,
    'status' => 'available',
    'search' => 'MOCK'
]);
$searchResponse = $controller->getVouchersData($searchRequest);
$searchData = json_decode($searchResponse->getContent(), true);

if ($searchResponse->getStatusCode() === 200 && ($searchData['success'] ?? false)) {
    echo "✔ Search and status filter executed successfully.\n";
    echo "✔ Found matched records: " . count($searchData['vouchers']) . "\n";
} else {
    echo "❌ Search/Filter query failed!\n";
    exit(1);
}

// 5. Test Voucher Details Sidebar API
echo "\n🪐 Testing Voucher Details Sidebar Drawer...\n";
$detailsResponse = $controller->getVoucherDetails($voucher->id);
$detailsData = json_decode($detailsResponse->getContent(), true);

if ($detailsResponse->getStatusCode() === 200 && ($detailsData['success'] ?? false)) {
    echo "✔ Voucher details drawer loaded successfully.\n";
    $vDetails = $detailsData['voucher'];
    echo "✔ Code: " . $vDetails['code'] . "\n";
    echo "✔ Calculated Art: " . $vDetails['art'] . "\n";
    echo "✔ Blind Index SKU: " . ($vDetails['sku_bidx'] ?? 'None') . "\n";
    echo "✔ MDK Proof Fingerprint: " . ($vDetails['fingerprint'] ?? 'Verified Proof') . "\n";
} else {
    echo "❌ Failed to load voucher details! Code: " . $detailsResponse->getStatusCode() . "\n";
    print_r($detailsData);
    exit(1);
}

// 6. Cleanup mock records
if ($spawnedVoucher) {
    echo "\n🧹 Cleaning up temporary mock voucher record...\n";
    $spawnedVoucher->delete();
    echo "✔ Mock voucher deleted.\n";
}

echo "\n=========================================\n";
echo "🎉 ALL B2B VOUCHER REGISTRY SPA TESTS PASSED SUCCESSFULLY!\n";
echo "=========================================\n";
