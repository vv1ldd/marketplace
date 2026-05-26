<?php

// 🧪 B2B Shops Integration Test Suite

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Seller;
use App\Models\Shop;
use App\Http\Controllers\PartnerDashboardController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

// 1. Resolve active test partner user
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

// 2. Fetch all shops (page 1)
echo "🏪 [TEST] Invoking getShopsData (status: all, page: 1)...\n";
$request = Request::create('/partner/dashboard/shops/data', 'GET', [
    'page' => 1,
    'status' => 'all',
    'search' => ''
]);

$response = $controller->getShopsData($request);
$data = json_decode($response->getContent(), true);

if (isset($data['success']) && $data['success']) {
    echo "✅ [SUCCESS] getShopsData returned successfully! Total Shops: {$data['total']}\n";
    if (count($data['shops']) > 0) {
        foreach ($data['shops'] as $s) {
            echo "   👉 Shop: ID #{$s['id']} | Name: {$s['name']} | Domain: {$s['domain']} | Active: " . ($s['is_active'] ? 'YES' : 'NO') . " | Sandbox: " . ($s['is_sandbox'] ? 'YES' : 'NO') . " | Products: {$s['product_count']}\n";
        }
        echo "\n";
    } else {
        echo "   👉 No shops are registered under this legal entity.\n\n";
    }
} else {
    echo "❌ [FAILED] getShopsData invocation returned error: " . json_encode($data) . "\n";
    exit(1);
}

// 3. Test active status switch toggler
if (count($data['shops']) > 0) {
    $targetShop = $data['shops'][0];
    $targetId = $targetShop['id'];
    $initialActive = $targetShop['is_active'];

    echo "⚡ [TEST] Toggling active status for Shop ID #{$targetId}...\n";
    $activeResponse = $controller->toggleShopActive($targetId);
    $activeData = json_decode($activeResponse->getContent(), true);

    if (isset($activeData['success']) && $activeData['success']) {
        echo "✅ [SUCCESS] Active Toggle succeeded! New status: " . ($activeData['is_active'] ? 'YES' : 'NO') . " | Msg: {$activeData['message']}\n";
        
        if ($activeData['is_active'] === $initialActive) {
            echo "❌ [FAILED] Active status did not flip!\n";
            exit(1);
        }

        // Revert status
        echo "⚡ [TEST] Reverting active status back...\n";
        $revertActive = $controller->toggleShopActive($targetId);
        $revertActiveData = json_decode($revertActive->getContent(), true);
        echo "✅ [SUCCESS] Reverted active status back to: " . ($revertActiveData['is_active'] ? 'YES' : 'NO') . "\n\n";
    } else {
        echo "❌ [FAILED] Active Toggle returned error: " . json_encode($activeData) . "\n";
        exit(1);
    }

    // 4. Test sandbox status switch toggler
    $initialSandbox = $targetShop['is_sandbox'];
    echo "⚡ [TEST] Toggling sandbox status for Shop ID #{$targetId}...\n";
    $sandboxResponse = $controller->toggleShopSandbox($targetId);
    $sandboxData = json_decode($sandboxResponse->getContent(), true);

    if (isset($sandboxData['success']) && $sandboxData['success']) {
        echo "✅ [SUCCESS] Sandbox Toggle succeeded! New status: " . ($sandboxData['is_sandbox'] ? 'YES' : 'NO') . " | Msg: {$sandboxData['message']}\n";
        
        if ($sandboxData['is_sandbox'] === $initialSandbox) {
            echo "❌ [FAILED] Sandbox status did not flip!\n";
            exit(1);
        }

        // Revert status
        echo "⚡ [TEST] Reverting sandbox status back...\n";
        $revertSandbox = $controller->toggleShopSandbox($targetId);
        $revertSandboxData = json_decode($revertSandbox->getContent(), true);
        echo "✅ [SUCCESS] Reverted sandbox status back to: " . ($revertSandboxData['is_sandbox'] ? 'YES' : 'NO') . "\n\n";
    } else {
        echo "❌ [FAILED] Sandbox Toggle returned error: " . json_encode($sandboxData) . "\n";
        exit(1);
    }
}

echo "🏁 [FINISH] B2B Shops integration tests finished successfully!\n";
