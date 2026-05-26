<?php

require '/Users/w1ld/Documents/GitHub/new/marketplace/vendor/autoload.php';
$app = require_once '/Users/w1ld/Documents/GitHub/new/marketplace/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\LegalEntity;
use App\Models\SovereignLedger;
use App\Services\LedgerService;
use App\Services\L1StateService;
use App\Services\L1ClearingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;

echo "=========================================================\n";
echo "⛓️ SOVEREIGN L1 LEDGER PROCUREMENT & CLEARING INTEGRATION TEST\n";
echo "=========================================================\n\n";

DB::beginTransaction();
// Also use transaction for api_wildflow_dev connection to make it a pure clean sandbox!
DB::connection('mysql')->beginTransaction();

try {
    // 1. Resolve / Create a B2B Partner Legal Entity
    $inn = strval(rand(1000000000, 9999999999));
    $partner = LegalEntity::create([
        'inn' => $inn,
        'name' => 'Meanly B2B Corp L1 Sandbox #' . rand(1, 10000),
        'balance' => 0.00,
        'available_balance' => 0.00,
        'reserved_balance' => 0.00,
        'currency' => 'RUB',
        'financial_secret' => 'PARTNER_SUPER_SECRET_KEY'
    ]);
    echo "🟢 Resolved Legal Entity (Partner): '{$partner->name}' (ID: #{$partner->id})\n";

    // Clean up previous ledger blocks for this entity in the sandbox
    SovereignLedger::where('legal_entity_id', $partner->id)->delete();

    // 2. Preload 5 encrypted vouchers into the Aggregator's database pool
    $sku = 'L1-STEAM-500-RUB';
    DB::table('api_wildflow_dev.local_vouchers')->where('service_sku', $sku)->delete();

    $vouchersData = [
        ['code' => 'L1-STEAM-1111-AAAA', 'serial' => 'L1-SN001'],
        ['code' => 'L1-STEAM-2222-BBBB', 'serial' => 'L1-SN002'],
        ['code' => 'L1-STEAM-3333-CCCC', 'serial' => 'L1-SN003'],
    ];

    $vault = app(\App\Services\VaultTransitService::class);
    foreach ($vouchersData as $v) {
        DB::table('api_wildflow_dev.local_vouchers')->insert([
            'service_sku' => $sku,
            'code' => $vault->encrypt($v['code']),
            'code_hash' => $vault->computeBlindIndex($v['code']),
            'serial' => $v['serial'],
            'expiry_date' => '2029-12-31',
            'face_value' => 500.00,
            'currency' => 'RUB',
            'is_used' => false,
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
    echo "🟢 Preloaded " . count($vouchersData) . " secure AES-256 encrypted cards into 'local_vouchers' pool.\n";

    // 3. Grant L1 sovereign balance via DEPOSIT_INTENT_CLEARED
    $ledger = app(LedgerService::class);
    $l1State = app(L1StateService::class);
    $clearing = app(L1ClearingService::class);

    $ledger->record(
        null,
        'DEPOSIT_INTENT_CLEARED',
        $partner,
        ['amount' => 5000.00, 'currency' => 'RUB', 'details' => 'Consortium bank clearing wire top-up'],
        $partner,
        'DID:CONSORTIUM_BANK | WireRef#98765'
    );
    echo "🟢 Cleared bank wire: Credited 5000.00 RUB directly to Partner L1 Chain Ledger.\n";

    $balInit = $l1State->reconstructBalance($partner);
    echo "   - Initial L1 Balance: Available = {$balInit['available_balance']} RUB, Reserved = {$balInit['reserved_balance']} RUB\n";

    // 4. Dispatch a checkout request directly onto L1 (places hold on 2 cards = 1000 RUB B2B cost)
    echo "\n⚡ [L1 TRANSACTION 1] Dispatching checkout request directly onto Sovereign Ledger...\n";
    $refCode = 'ORDER_L1_SANDBOX_' . uniqid();
    $clearing->dispatchOrderRequest($partner, $sku, 2, 1000.00, $refCode);

    $balHold = $l1State->reconstructBalance($partner);
    echo "🟢 FINANCE_HOLD Block Recorded Successfully:\n";
    echo "   - New L1 Balance: Available = {$balHold['available_balance']} RUB, Reserved = {$balHold['reserved_balance']} RUB\n";

    // 5. Run the Aggregator L1 Validator clearing queue
    echo "\n⚡ [L1 VALIDATOR DAEMON] Listening to L1 chain and processing clearing queue...\n";
    $clearingResult = $clearing->processClearingQueue();

    echo "🟢 L1 Validator Queue Completed:\n";
    echo "   - Processed success: " . $clearingResult['processed'] . "\n";
    echo "   - Processed fail: " . $clearingResult['failed'] . "\n";
    
    // 6. Retrieve stock replenish delivery block
    $replenishBlock = SovereignLedger::where('legal_entity_id', $partner->id)
        ->where('event_type', 'STOCK_REPLENISH')
        ->orderBy('id', 'desc')
        ->first();

    echo "\n📦 Sovereign Delivery Block Recovered from On-Chain Payload:\n";
    echo "   - Order Reference: " . $replenishBlock->payload['reference_code'] . "\n";
    echo "   - Clearing Status: " . $replenishBlock->payload['order_status'] . "\n";
    
    echo "   - Cryptographically Secured Cards delivered in Block:\n";
    foreach ($replenishBlock->payload['vouchers'] as $index => $c) {
        $decryptedCode = $vault->decrypt($c['code']);
        echo "     [" . ($index + 1) . "] Encrypted: " . substr($c['code'], 0, 30) . "...\n";
        echo "         -> Secure Decrypted: " . $decryptedCode . " (Serial: " . $c['serial'] . ")\n";
    }

    // 7. Verify final reconciled balance
    $integrityCheck = app(LedgerService::class)->verifyLegalEntityIntegrity($partner);
    echo "\n🔎 Reconciling final balance states:\n";
    echo "   - L1 Chain Integrity secured: " . ($integrityCheck['valid'] ? 'YES 🟢' : 'NO ❌') . "\n";
    if (!$integrityCheck['valid']) {
        echo "     Integrity Errors:\n";
        foreach ($integrityCheck['errors'] as $err) {
            echo "       * " . $err . "\n";
        }
    }
    
    $balFinal = $l1State->reconstructBalance($partner);
    echo "   - Final L1 Balance: Available = {$balFinal['available_balance']} RUB, Reserved = {$balFinal['reserved_balance']} RUB\n";

    if ($balHold['available_balance'] === 4000.00 && $balHold['reserved_balance'] === 1000.00 &&
        $balFinal['available_balance'] === 4000.00 && $balFinal['reserved_balance'] === 0.00 &&
        $clearingResult['processed'] === 1) {
        echo "\n🎉 SUCCESS: The non-classical Sovereign L1 Ledger Procurement & Clearance is 100% SECURED, verified, and operational!\n";
    } else {
        echo "\n❌ FAILURE: Ledger calculations or clearing state mismatch.\n";
    }

} catch (\Exception $e) {
    echo "\n❌ Error occurred during L1 Clearing test: " . $e->getMessage() . "\n";
} finally {
    DB::connection('mysql')->rollBack();
    DB::rollBack();
    echo "\n🧹 L1 Consortium sandbox rolled back. Databases are clean.\n";
}
