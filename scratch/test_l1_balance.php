<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\LegalEntity;
use App\Models\User;
use App\Models\Brand;
use App\Services\LedgerService;
use App\Services\L1StateService;
use Illuminate\Support\Str;

echo "=========================================================\n";
echo "🚀 L1 SOVEREIGN STATE RECOVERY ENGINE TEST\n";
echo "=========================================================\n\n";

// 1. Setup a sandbox user
$email = 'l1-test-' . Str::random(8) . '@meanly.test';
$user = User::create([
    'first_name' => 'L1',
    'last_name' => 'Tester',
    'email' => $email,
    'password' => Hash::make(Str::random(32)),
    'password_login_enabled' => false,
]);
$user->assignRole('b2b_partner');

$brand = Brand::first();

// 2. Setup sandbox LegalEntity
$testInn = '77' . strval(rand(1000000000, 9999999999));
$entity = LegalEntity::create([
    'brand_id' => $brand->id,
    'user_id' => $user->id,
    'name' => 'L1 Test Corporation',
    'inn' => $testInn,
    'status' => 'active',
    'is_active' => true,
    'balance' => 0.00,
    'currency' => 'RUB'
]);

echo "🟢 Created Sandbox LegalEntity (ID: {$entity->id}, INN: {$testInn})\n\n";

// 3. Simulate and Record Events
$ledger = app(LedgerService::class);
$stateService = app(L1StateService::class);

echo "1. Simulating real cash inflows and freezes...\n";

// Event A: Deposit 10,000 RUB
echo "   - Block 1: Deposit +10,000 RUB...\n";
$ledger->record(
    shop: null,
    eventType: 'DEPOSIT_INTENT_CLEARED',
    entity: $entity,
    payload: ['amount' => 10000.00, 'currency' => 'RUB'],
    legalEntity: $entity
);

// Event B: Freeze 2,500 RUB for a JIT purchase
echo "   - Block 2: Freeze -2,500 RUB (Hold)...\n";
$ledger->record(
    shop: null,
    eventType: 'FINANCE_HOLD',
    entity: $entity,
    payload: ['amount_rub' => 2500.00],
    legalEntity: $entity
);

// Event C: Deposit 5,000 RUB
echo "   - Block 3: Deposit +5,000 RUB...\n";
$ledger->record(
    shop: null,
    eventType: 'DEPOSIT_INTENT_CLEARED',
    entity: $entity,
    payload: ['amount' => 5000.00, 'currency' => 'RUB'],
    legalEntity: $entity
);

// Event D: Freeze 1,200 RUB for another purchase
echo "   - Block 4: Freeze -1,200 RUB (Hold)...\n";
$ledger->record(
    shop: null,
    eventType: 'FINANCE_HOLD',
    entity: $entity,
    payload: ['amount_rub' => 1200.00],
    legalEntity: $entity
);

// Event E: Release Block 2 (Order failed at aggregator level, refund)
echo "   - Block 5: Release Hold +2,500 RUB...\n";
$ledger->record(
    shop: null,
    eventType: 'FINANCE_RELEASE_HOLD',
    entity: $entity,
    payload: ['amount_rub' => 2500.00],
    legalEntity: $entity
);

echo "\n2. Reconstructing balance state from L1 sovereign ledger...\n";

DB::beginTransaction();
try {
    $integrity = app(LedgerService::class)->verifyLegalEntityIntegrity($entity);
    if (!$integrity['valid']) {
        echo "❌ Ledger Integrity Check Failed with errors:\n";
        print_r($integrity['errors']);
    }
    
    $state = $stateService->reconstructBalance($entity);

    echo "\n=========================================================\n";
    echo "🎯 L1 RECOVERY RESULTS:\n";
    echo "=========================================================\n";
    echo "   - Blocks Processed: " . $state['blocks_processed'] . "\n";
    echo "   - L1 Cryptographic Integrity secured: " . ($state['integrity_secured'] ? 'YES 🟢' : 'NO ❌') . "\n";
    echo "   - Reconstructed Available Balance: " . $state['available_balance'] . " RUB\n";
    echo "   - Reconstructed Reserved Balance: " . $state['reserved_balance'] . " RUB\n";
    echo "   - Reconstructed Total Balance: " . $state['total_balance'] . " RUB\n";
    echo "=========================================================\n\n";

    // Expected:
    // Deposits: 10000 + 5000 = 15000
    // Holds: -2500 - 1200 = -3700
    // Releases: +2500
    // Available balance: 15000 - 3700 + 2500 = 13800
    // Reserved balance: 2500 + 1200 - 2500 = 1200
    // Total balance: 13800 + 1200 = 15000
    
    if ($state['available_balance'] === 13800.0 && $state['reserved_balance'] === 1200.0) {
        echo "🎉 SUCCESS: L1 Balance state restored with absolute, penny-perfect mathematical precision!\n";
    } else {
        echo "❌ FAILURE: Restored balance does not match expectations.\n";
    }

} catch (\Exception $e) {
    echo "❌ Reconstitution failed: " . $e->getMessage() . "\n";
} finally {
    DB::rollBack();
    // Clean up sandbox entries
    \App\Models\SovereignLedger::where('legal_entity_id', $entity->id)->delete();
    $entity->delete();
    $user->delete();
    echo "\n🧹 Sandbox clean up complete. Databases are pristine.\n";
}
