<?php

use Illuminate\Support\Facades\Auth;
use App\Models\Seller;
use App\Models\Warehouse;
use App\Http\Controllers\PartnerDashboardController;
use Illuminate\Http\Request;

// 1. Boot up Laravel environment
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$seller = Seller::where('email', 'admin@admin.com')->first();
if (!$seller) {
    $seller = Seller::first();
}

if (!$seller) {
    echo "❌ [ERROR] No seller found in database!\n";
    exit(1);
}

Auth::login($seller);
echo "\n👤 [TEST] Operating as partner seller: {$seller->email} (ID: {$seller->id})\n";

$legalEntity = $seller->legalEntities()->first();
if (!$legalEntity) {
    echo "❌ [ERROR] Legal entity not found for seller!\n";
    exit(1);
}

$shop = \App\Models\Shop::where('legal_entity_id', $legalEntity->id)->first();
if (!$shop) {
    echo "❌ [ERROR] No registered shop found for this legal entity!\n";
    exit(1);
}
$shopId = $shop->id;
echo "🏪 [TEST] Resolved Shop ID #{$shopId} (Name: {$shop->name} | Domain: {$shop->domain})\n";

$controller = new PartnerDashboardController();

// 2. Fetch Warehouses list via dynamic AJAX handler
echo "\n📦 [TEST] Invoking getWarehousesData (search: '')...\n";
$request = Request::create('/merchant/dashboard/warehouses/data', 'GET', [
    'search' => ''
]);
$response = $controller->getWarehousesData($request);
$data = json_decode($response->getContent(), true);

if (isset($data['success']) && $data['success']) {
    echo "✅ [SUCCESS] getWarehousesData returned successfully! Total count: " . $data['total'] . "\n";
    foreach ($data['warehouses'] as $w) {
        echo "   👉 Warehouse ID #{$w['id']} | Name: {$w['name']} | Shop: {$w['shop_name']} | Active: " . ($w['is_active'] ? 'YES' : 'NO') . " | Created: {$w['created_at']}\n";
    }
} else {
    echo "❌ [ERROR] getWarehousesData failed!\n";
    var_dump($response->getContent());
    exit(1);
}

// 3. Create a brand new Warehouse
echo "\n✨ [TEST] Spawning a brand new Master Warehouse...\n";
$createReq = Request::create('/merchant/dashboard/warehouses/create', 'POST', [
    'name' => 'Автоматический Тестовый Склад B2B API',
    'shop_id' => $shopId
]);

$createRes = $controller->createWarehouse($createReq);
$createData = json_decode($createRes->getContent(), true);

if (isset($createData['success']) && $createData['success']) {
    $newWhId = $createData['warehouse_id'];
    echo "✅ [SUCCESS] Master Warehouse created successfully! ID: #{$newWhId}\n";
} else {
    echo "❌ [ERROR] Warehouse creation failed!\n";
    var_dump($createRes->getContent());
    exit(1);
}

// 4. Toggle active status
echo "\n🔄 [TEST] Toggling active status of Warehouse ID #{$newWhId}...\n";
$toggleReq = Request::create("/merchant/dashboard/warehouses/{$newWhId}/toggle-active", 'POST');
$toggleRes = $controller->toggleWarehouseActive($toggleReq, $newWhId);
$toggleData = json_decode($toggleRes->getContent(), true);

if (isset($toggleData['success']) && $toggleData['success']) {
    echo "✅ [SUCCESS] Active toggled! New active state: " . ($toggleData['is_active'] ? 'YES' : 'NO') . " (Message: {$toggleData['message']})\n";
} else {
    echo "❌ [ERROR] Toggle status failed!\n";
    var_dump($toggleRes->getContent());
    exit(1);
}

// 5. Verify updated in list search
echo "\n🔍 [TEST] Searching list for newly created warehouse name...\n";
$searchReq = Request::create('/merchant/dashboard/warehouses/data', 'GET', [
    'search' => 'Автоматический Тестовый Склад'
]);
$searchRes = $controller->getWarehousesData($searchReq);
$searchData = json_decode($searchRes->getContent(), true);

if (isset($searchData['success']) && $searchData['success'] && count($searchData['warehouses']) > 0) {
    echo "✅ [SUCCESS] Found in searched warehouses! Details:\n";
    foreach ($searchData['warehouses'] as $w) {
        echo "   💬 ID #{$w['id']} | Name: {$w['name']} | Active: " . ($w['is_active'] ? 'YES' : 'NO') . "\n";
    }
} else {
    echo "❌ [ERROR] Search verification failed!\n";
    var_dump($searchRes->getContent());
    exit(1);
}

// 6. Cleanup the test warehouse
Warehouse::where('id', $newWhId)->delete();
echo "\n🧹 [CLEANUP] Deleted test Warehouse ID #{$newWhId}.\n";
echo "🏆 [FINISH] B2B Warehouses integration tests completed perfectly!\n";
