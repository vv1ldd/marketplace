<?php

// 🧪 B2B Catalog Integration Test Suite

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\PartnerDashboardController;
use App\Models\Seller;
use App\Models\Product;

// 1. Authenticate as our main merchant admin user
$seller = Seller::where('email', 'admin@admin.com')->first();
if (!$seller) {
    $seller = Seller::first();
}

if (!$seller) {
    echo "❌ [ERROR] No seller user found in DB.\n";
    exit(1);
}
Auth::login($seller);
echo "👤 [TEST] Operating as partner seller: {$seller->email} (ID: {$seller->id})\n\n";

$controller = new PartnerDashboardController();

// 2. Fetch all products (page 1)
echo "📦 [TEST] Invoking getCatalogData (status: all, page: 1)...\n";
$request = Request::create('/merchant/dashboard/catalog/data', 'GET', [
    'page' => 1,
    'status' => 'all',
    'search' => ''
]);

$response = $controller->getCatalogData($request);
$data = json_decode($response->getContent(), true);

if (isset($data['success']) && $data['success']) {
    echo "✅ [SUCCESS] getCatalogData returned successfully! Total Catalog: {$data['total']}\n";
    if (count($data['products']) > 0) {
        $p = $data['products'][0];
        echo "   👉 Sample Product: ID #{$p['id']} | SKU: {$p['sku']} | Name: {$p['name']} | Price: {$p['price_rub']} ₽ | Active: " . ($p['is_active'] ? 'YES' : 'NO') . "\n\n";
    } else {
        echo "   👉 Catalog is currently empty.\n\n";
    }
} else {
    echo "❌ [FAILED] getCatalogData invocation returned error: " . json_encode($data) . "\n";
    exit(1);
}

// 3. Test searching by name
if (count($data['products']) > 0) {
    $searchTerm = substr($data['products'][0]['name'], 0, 8);
    echo "🔍 [TEST] Searching catalog products by term: '{$searchTerm}'...\n";
    $searchRequest = Request::create('/merchant/dashboard/catalog/data', 'GET', [
        'page' => 1,
        'status' => 'all',
        'search' => $searchTerm
    ]);

    $searchResponse = $controller->getCatalogData($searchRequest);
    $searchData = json_decode($searchResponse->getContent(), true);

    if (isset($searchData['success']) && $searchData['success']) {
        echo "✅ [SUCCESS] Search matched {$searchData['total']} product(s)!\n";
        foreach ($searchData['products'] as $sp) {
            echo "   👉 Found SKU: {$sp['sku']} | Name: {$sp['name']}\n";
        }
        echo "\n";
    } else {
        echo "❌ [FAILED] Search returned error: " . json_encode($searchData) . "\n";
    }
}

// 4. Test toggling product active status
if (count($data['products']) > 0) {
    $targetId = $data['products'][0]['id'];
    $targetSku = $data['products'][0]['sku'];
    $initialActive = $data['products'][0]['is_active'];

    echo "⚡ [TEST] Toggling product status for ID #{$targetId} (SKU: {$targetSku})...\n";
    $toggleResponse = $controller->toggleProductStatus($targetId);
    $toggleData = json_decode($toggleResponse->getContent(), true);

    if (isset($toggleData['success']) && $toggleData['success']) {
        echo "✅ [SUCCESS] Toggle executed successfully! New status active: " . ($toggleData['is_active'] ? 'YES' : 'NO') . " | Msg: {$toggleData['message']}\n";
        
        // Assert state changed
        if ($toggleData['is_active'] === $initialActive) {
            echo "❌ [FAILED] Product state did not flip!\n";
            exit(1);
        }

        // Toggle back to initial state to keep DB clean
        echo "⚡ [TEST] Reverting status toggle back to initial state...\n";
        $revertResponse = $controller->toggleProductStatus($targetId);
        $revertData = json_decode($revertResponse->getContent(), true);
        echo "✅ [SUCCESS] Reverted status back. Status active: " . ($revertData['is_active'] ? 'YES' : 'NO') . "\n";
        
    } else {
        echo "❌ [FAILED] Toggle returned error: " . json_encode($toggleData) . "\n";
        exit(1);
    }
}

echo "\n🏁 [FINISH] B2B Catalog integration tests finished successfully!\n";
