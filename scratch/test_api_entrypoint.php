<?php

require '/Users/w1ld/Documents/GitHub/new/api-wildflow-dev/vendor/autoload.php';
$app = require_once '/Users/w1ld/Documents/GitHub/new/api-wildflow-dev/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\LocalVoucher;
use App\Http\Controllers\PartnersController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

echo "=========================================================\n";
echo "📡 UNIVERSAL INTAKE API GATEWAY DYNAMIC RESOLUTION TEST\n";
echo "=========================================================\n\n";

DB::beginTransaction();

try {
    // Clean up previous test products
    LocalVoucher::whereIn('service_sku', ['PSN-USA-10', 'STEAM-EUR-20', 'PSN-TURKEY-50'])->delete();

    // 1. Build a mixed-wholesaler payload simulating three different exporter schemas!
    $payload = [
        'vouchers' => [
            // [1] G2A Wholesaler format: uses product_id, pin_code, serial_number
            [
                'product_id' => 'PSN-USA-10',
                'pin_code' => 'PSN-USA-CODE-1111',
                'serial_number' => 'PSN-USA-SR001',
                'price' => 10.00,
                'currency' => 'USD'
            ],
            // [2] EZPin Wholesaler format: uses sku, pin_code, serial
            [
                'sku' => 'STEAM-EUR-20',
                'pin_code' => 'STEAM-EUR-CODE-2222',
                'serial' => 'STEAM-EUR-SR002',
                'face_value' => 20.00,
                'currency' => 'EUR'
            ],
            // [3] Custom PlayStation primary seller format: uses product_code, voucher_code, s_n
            [
                'product_code' => 'PSN-TURKEY-50',
                'voucher_code' => 'PSN-TR-CODE-3333',
                's_n' => 'PSN-TR-SR003',
                'price' => 50.00,
                'currency' => 'TRY'
            ],
            // [4] Duplicate to test deduplication on G2A item
            [
                'product_id' => 'PSN-USA-10',
                'pin_code' => 'PSN-USA-CODE-1111', // Duplicate of [1]
                'serial_number' => 'PSN-USA-SR001-DUP'
            ],
            // [5] Invalid item with missing code to test graceful skip
            [
                'product_id' => 'PSN-USA-10',
                'serial_number' => 'INVALID-SR'
            ]
        ]
    ];

    echo "🟢 Dispatching mixed-wholesaler payload:\n";
    echo "   - Card 1: G2A Format (PlayStation USA)\n";
    echo "   - Card 2: EZPin Format (Steam EUR)\n";
    echo "   - Card 3: Reseller Format (PlayStation Turkey)\n";
    echo "   - Card 4: Duplicate of Card 1\n";
    echo "   - Card 5: Invalid (no code)\n\n";

    // 2. Mock Request
    $request = Request::create(
        '/api/v1/partners/warehouse/vouchers',
        'POST',
        [],
        [],
        [],
        [],
        json_encode($payload)
    );
    $request->headers->set('Content-Type', 'application/json');

    // 3. Resolve and call controller
    $controller = app(PartnersController::class);
    $response = $controller->importVouchers($request);
    $result = json_decode($response->getContent(), true);

    echo "📊 Gateway Response Received:\n";
    echo "   - Success: " . ($result['success'] ? 'YES 🟢' : 'NO ❌') . "\n";
    echo "   - Message: " . $result['message'] . "\n";
    echo "   - Total Successfully Imported Vouchers: " . $result['imported_total'] . "\n";
    echo "   - Duplicate Vouchers Skipped: " . $result['duplicate_skipped'] . "\n";
    echo "   - Invalid Vouchers Skipped: " . $result['invalid_skipped'] . "\n";
    echo "   - Summary by SKU:\n";
    foreach ($result['summary_by_sku'] as $sku => $count) {
        echo "     * {$sku}: {$count} vouchers\n";
    }

    // 4. Database Validation
    $usaCount = LocalVoucher::where('service_sku', 'PSN-USA-10')->count();
    $eurCount = LocalVoucher::where('service_sku', 'STEAM-EUR-20')->count();
    $trCount = LocalVoucher::where('service_sku', 'PSN-TURKEY-50')->count();

    echo "\n🔎 Verifying vault records inside database:\n";
    echo "   - [PSN-USA-10] records: {$usaCount} (Expected: 1)\n";
    echo "   - [STEAM-EUR-20] records: {$eurCount} (Expected: 1)\n";
    echo "   - [PSN-TURKEY-50] records: {$trCount} (Expected: 1)\n";

    if ($result['imported_total'] === 3 && $result['duplicate_skipped'] === 1 && $result['invalid_skipped'] === 1 && $usaCount === 1 && $eurCount === 1 && $trCount === 1) {
        echo "\n🎉 SUCCESS: Universal Wholesaler Intake Gateway handles PlayStation/multi-column aliases with penny-perfect precision!\n";
    } else {
        echo "\n❌ FAILURE: Mismatch in expected import counts or mappings.\n";
    }

} catch (\Exception $e) {
    echo "\n❌ Error occurred during dynamic import test: " . $e->getMessage() . "\n";
} finally {
    DB::rollBack();
    echo "\n🧹 Sandbox rolled back. Databases are clean.\n";
}
