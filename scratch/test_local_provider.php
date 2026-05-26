<?php

require '/Users/w1ld/Documents/GitHub/new/api-wildflow-dev/vendor/autoload.php';
$app = require_once '/Users/w1ld/Documents/GitHub/new/api-wildflow-dev/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\LocalVoucher;
use App\Models\Catalog;
use App\Models\Provider;
use App\Managers\CodeProviderManager;
use Illuminate\Support\Facades\DB;

echo "=========================================================\n";
echo "📦 SOVEREIGN INVENTORY (LOCAL WAREHOUSE) PROVIDER TEST\n";
echo "=========================================================\n\n";

DB::beginTransaction();

try {
    // 1. Ensure sovereign provider is registered in the database
    $provider = Provider::updateOrCreate(
        ['type' => 'sovereign'],
        [
            'name' => 'Meanly Sovereign Warehouse',
            'is_active' => true,
            'credentials' => [],
            'settings' => []
        ]
    );
    echo "🟢 Registered 'sovereign' Provider inside 'providers' table.\n";

    // 2. Preload 5 test vouchers
    $sku = 'TEST-LOCAL-VOUCHER';
    LocalVoucher::where('service_sku', $sku)->delete(); // Clean up old

    $vouchersData = [
        ['code' => 'MNLY-VCHR-1111-AAAA', 'serial' => 'SN001', 'expiry_date' => '2028-12-31'],
        ['code' => 'MNLY-VCHR-2222-BBBB', 'serial' => 'SN002', 'expiry_date' => '2028-12-31'],
        ['code' => 'MNLY-VCHR-3333-CCCC', 'serial' => 'SN003', 'expiry_date' => '2028-12-31'],
        ['code' => 'MNLY-VCHR-4444-DDDD', 'serial' => 'SN004', 'expiry_date' => '2028-12-31'],
        ['code' => 'MNLY-VCHR-5555-EEEE', 'serial' => 'SN005', 'expiry_date' => '2028-12-31'],
    ];

    $vault = app(\App\Services\VaultTransitService::class);
    foreach ($vouchersData as $v) {
        LocalVoucher::create([
            'service_sku' => $sku,
            'code' => $vault->encrypt($v['code']),
            'code_hash' => $vault->computeBlindIndex($v['code']),
            'serial' => $v['serial'],
            'expiry_date' => $v['expiry_date'],
            'face_value' => 10.00,
            'currency' => 'USD',
            'is_used' => false
        ]);
    }
    echo "🟢 Preloaded " . count($vouchersData) . " digital cards/vouchers into 'local_vouchers' table.\n";

    // 3. Create a matching catalog product in our catalogs
    $catalog = Catalog::updateOrCreate(
        [
            'provider' => 'sovereign',
            'service_sku' => $sku
        ],
        [
            'sku' => 'STEAM-10-USD',
            'data' => [
                'name' => 'Meanly Steam Gift Card $10',
                'min_price' => 10.00,
                'max_price' => 10.00,
                'currency' => 'USD',
                'brand' => 'Steam',
                'region' => 'US',
                'category' => 'Gaming',
                'is_available' => true
            ]
        ]
    );
    echo "🟢 Configured catalog product 'Meanly Steam Gift Card $10' with provider = 'sovereign'.\n";

    // 4. Resolve the sovereign driver
    $manager = app(CodeProviderManager::class);
    $driver = $manager->driver('sovereign');

    echo "\n🔎 1. Checking Availability and Stock...\n";
    $avail = $driver->checkAvailability($sku, 3);
    echo "   - Available for 3 items: " . ($avail['available'] ? 'YES 🟢' : 'NO ❌') . "\n";
    echo "   - Current Stock: " . $avail['stock'] . "\n";

    echo "\n⚡ 2. Placing an Order for 3 Vouchers...\n";
    $orderRef = 'TST_' . uniqid();
    $orderResult = $driver->createOrder([
        'sku' => $sku,
        'quantity' => 3,
        'reference_code' => $orderRef,
        'terminal_id' => 1234,
        'terminal_pin' => '5678'
    ]);

    echo "   - Order Status: " . $orderResult['status'] . "\n";
    echo "   - Vendor Order ID: " . $orderResult['order_id'] . "\n";
    echo "   - Vouchers Acquired:\n";
    foreach ($orderResult['results'] as $index => $card) {
        echo "     [" . ($index + 1) . "] Code: " . $card['pin_code'] . " (Serial: " . $card['serial'] . ")\n";
    }

    echo "\n🔎 3. Verifying Remaining Stock...\n";
    $availAfter = $driver->checkAvailability($sku, 1);
    echo "   - Remaining Unused Stock: " . $availAfter['stock'] . " vouchers\n";

    if ($availAfter['stock'] === 2 && count($orderResult['results']) === 3) {
        echo "\n🎉 SUCCESS: Local Warehouse Provider handles order lifecycle flawlessly with atomicity and full transparency!\n";
    } else {
        echo "\n❌ FAILURE: Stock allocation or ordering mismatch.\n";
    }

} catch (\Exception $e) {
    echo "\n❌ Error occurred during test: " . $e->getMessage() . "\n";
} finally {
    DB::rollBack();
    echo "\n🧹 Sandbox rolled back. Databases are clean.\n";
}
