<?php

// Boot Laravel application
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Seller;
use App\Models\LegalEntity;
use App\Models\SovereignLedger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

echo "=========================================\n";
echo "🚀 STARTING B2B FINANCE & BILLING API INTEGRATION TEST\n";
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

// 2. Fetch original balances to restore later
$origAvailable = (float) $legalEntity->available_balance;
$origReserved = (float) $legalEntity->reserved_balance;
$origTotal = (float) $legalEntity->balance;
echo "✔ Original Balances: Available: $origAvailable ₽, Held: $origReserved ₽, Capital: $origTotal ₽\n\n";

// 3. Test B2B Finance Get Data API
echo "📋 Testing B2B Finance Data Fetch...\n";
$controller = new \App\Http\Controllers\PartnerDashboardController();

$request = Illuminate\Http\Request::create('http://' . config('app.domain') . '/partner/dashboard/finance/data', 'GET', [
    'page' => 1,
    'status' => 'all'
]);
$response = $controller->getFinanceData($request);
$data = json_decode($response->getContent(), true);

if ($response->getStatusCode() === 200 && ($data['success'] ?? false)) {
    echo "✔ Finance balances and ledger list loaded successfully.\n";
    echo "✔ Available from API: " . $data['balances']['available_formatted'] . "\n";
    echo "✔ Capital from API: " . $data['balances']['total_formatted'] . "\n";
    echo "✔ Found ledger records in page: " . count($data['transactions']) . "\n";
    
    if (count($data['transactions']) > 0) {
        $firstTx = $data['transactions'][0];
        $requiredKeys = ['id', 'event_type', 'event_type_formatted', 'amount', 'amount_formatted', 'description', 'trigger_source', 'fingerprint', 'created_at_formatted'];
        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $firstTx)) {
                echo "❌ Error: Missing required transaction list key '$key' in output!\n";
                exit(1);
            }
        }
        echo "✔ Operation ledger keys verified successfully.\n";
    }
} else {
    echo "❌ Failed to fetch B2B finance data! Code: " . $response->getStatusCode() . "\n";
    print_r($data);
    exit(1);
}

// 4. Test Simulating a Deposit of 25,000 RUB
echo "\n💰 Testing Simulated Account Replenishment (Deposit: 25 000 ₽)...\n";
$depositRequest = Illuminate\Http\Request::create('http://' . config('app.domain') . '/partner/dashboard/finance/deposit', 'POST', [
    'amount' => 25000
]);

$depositResponse = $controller->simulateDeposit($depositRequest);
$depositData = json_decode($depositResponse->getContent(), true);

$spawnedLedger = null;

if ($depositResponse->getStatusCode() === 200 && ($depositData['success'] ?? false)) {
    echo "✔ Simulated deposit API returned success!\n";
    
    // Refresh legal entity and assert balance increase
    $legalEntity->refresh();
    $newAvailable = (float) $legalEntity->available_balance;
    $newTotal = (float) $legalEntity->balance;
    
    echo "✔ New Available Balance after deposit: $newAvailable ₽ (Expected: " . ($origAvailable + 25000) . " ₽)\n";
    echo "✔ New Total Capital after deposit: $newTotal ₽ (Expected: " . ($origTotal + 25000) . " ₽)\n";
    
    if (abs($newAvailable - ($origAvailable + 25000)) > 0.01) {
        echo "❌ Error: Available balance did not increment correctly!\n";
        exit(1);
    }
    if (abs($newTotal - ($origTotal + 25000)) > 0.01) {
        echo "❌ Error: Total balance did not increment correctly!\n";
        exit(1);
    }
    echo "✔ Balance increments confirmed.\n";

    // Assert Sovereign Ledger Transaction was recorded
    $spawnedLedger = SovereignLedger::where('legal_entity_id', $legalEntity->id)
        ->where('event_type', 'FINANCE_DEPOSIT')
        ->orderBy('id', 'desc')
        ->first();
        
    if ($spawnedLedger) {
        echo "✔ Sovereign Ledger recorded the event FINANCE_DEPOSIT successfully!\n";
        echo "✔ Fingerprint (Verification Proof): " . $spawnedLedger->fingerprint . "\n";
        echo "✔ Trigger Source Authority: " . $spawnedLedger->trigger_source . "\n";
        
        // Assert SHA-256 validity
        if (strlen($spawnedLedger->fingerprint) !== 64) {
            echo "❌ Error: Sovereign Ledger verification proof fingerprint is not a valid SHA-256 hash!\n";
            exit(1);
        }
        echo "✔ Ledger proof integrity validated successfully.\n";
    } else {
        echo "❌ Error: FINANCE_DEPOSIT event was not found in the Sovereign Ledger table!\n";
        exit(1);
    }
} else {
    echo "❌ B2B Deposit Simulation Failed! Code: " . $depositResponse->getStatusCode() . "\n";
    print_r($depositData);
    exit(1);
}

// 5. Test Filters and Searches with the new record
echo "\n🔍 Testing Finance Data Filters & Searches...\n";
$filterRequest = Illuminate\Http\Request::create('http://' . config('app.domain') . '/partner/dashboard/finance/data', 'GET', [
    'page' => 1,
    'status' => 'credit',
    'search' => 'Симуляционное пополнение'
]);
$filterResponse = $controller->getFinanceData($filterRequest);
$filterData = json_decode($filterResponse->getContent(), true);

if ($filterResponse->getStatusCode() === 200 && ($filterData['success'] ?? false)) {
    echo "✔ Finance search and status query filters executed successfully.\n";
    echo "✔ Matched search log count: " . count($filterData['transactions']) . "\n";
    if (count($filterData['transactions']) > 0) {
        echo "✔ Found newly created deposit: " . $filterData['transactions'][0]['description'] . " (" . $filterData['transactions'][0]['amount_formatted'] . ")\n";
    } else {
        echo "❌ Error: Could not find the newly created deposit transaction using search filters!\n";
        exit(1);
    }
} else {
    echo "❌ Search filter API failed!\n";
    exit(1);
}

// 6. Cleanup mock records to preserve db state
echo "\n🧹 Restoring database state & cleaning up test records...\n";
DB::transaction(function() use ($legalEntity, $origAvailable, $origTotal, $spawnedLedger) {
    // Restore legal entity original balances
    DB::table('legal_entities')
        ->where('id', $legalEntity->id)
        ->update([
            'available_balance' => $origAvailable,
            'balance' => $origTotal
        ]);
        
    // Delete the spawned ledger record
    if ($spawnedLedger) {
        $spawnedLedger->delete();
    }
});
echo "✔ Restored original available balance: $origAvailable ₽\n";
echo "✔ Restored original capital balance: $origTotal ₽\n";
echo "✔ Deleted temporary Sovereign Ledger transaction record.\n";

echo "\n=========================================\n";
echo "🎉 ALL B2B FINANCE & BILLING SPA TESTS PASSED SUCCESSFULLY!\n";
echo "=========================================\n";
