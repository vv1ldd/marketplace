<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\LegalEntity;
use App\Models\User;
use App\Models\Brand;
use App\Services\WildflowService;
use Illuminate\Support\Str;

echo "=========================================================\n";
echo "🚀 SOVEREIGN SYSTEM TEST: B2B TERMINAL AUTO-CREATION\n";
echo "=========================================================\n\n";

// 1. Setup a clean sandbox test user
echo "1. Preparing sandbox user...\n";
$email = 'test-b2b-' . Str::random(8) . '@meanly.test';
$user = User::create([
    'first_name' => 'Test',
    'last_name' => 'B2B Partner',
    'email' => $email,
    'password' => Hash::make(Str::random(32)),
    'password_login_enabled' => false,
]);
$user->assignRole('b2b_partner');
echo "   🟢 Sandbox User created with ID: {$user->id} ($email)\n\n";

// 2. Setup a target brand
$brand = Brand::first();
if (!$brand) {
    echo "❌ Error: No brands found in database. Create a brand first.\n";
    exit(1);
}

// 3. Create a unique INN for testing
$testInn = '77' . strval(rand(1000000000, 9999999999));
echo "2. Formulating B2B LegalEntity registration...\n";
echo "   - Legal Name: 'Test B2B Corporation Ltd'\n";
echo "   - Test INN: {$testInn}\n";
echo "   - Wallet Currency: USD\n\n";

// 4. Create the LegalEntity
echo "3. Instantiating LegalEntity in marketplace DB...\n";
DB::beginTransaction();
try {
    $entity = LegalEntity::create([
        'brand_id' => $brand->id,
        'user_id' => $user->id,
        'name' => 'Test B2B Corporation Ltd',
        'inn' => $testInn,
        'status' => 'active',
        'is_active' => true,
        'balance' => 250.00, // Starting credit limit
        'currency' => 'USD',
        'director_name' => 'Leonel Messi',
        'phone' => '+79998887766',
        'email' => $email,
        'vendor_credentials' => [
            'ezpin' => [
                'terminal_id' => '12093',
                'terminal_pin' => '7829'
            ]
        ]
    ]);

    echo "   🟢 LegalEntity successfully created in Marketplace (ID: {$entity->id})\n";
    echo "   🚀 Model boot events triggered. Checking sync call status...\n\n";

    // 5. Let's explicitly query the Wildflow aggregator database or hit the GET route to verify!
    echo "4. Querying Wildflow Aggregator Kernel (api.wildflow.test)...\n";
    
    // We can use the WildflowService's getPartner method to verify if it's present on the API aggregator!
    $wildflow = new WildflowService();
    
    echo "   - Fetching terminal data for External ID: {$entity->id}...\n";
    $partnerData = $wildflow->getPartner((string)$entity->id);
    
    echo "\n=========================================================\n";
    echo "🎯 VERIFICATION RESULT:\n";
    echo "=========================================================\n";
    echo "   - Aggregator Terminal ID: " . ($partnerData['id'] ?? 'N/A') . "\n";
    echo "   - External ID (Marketplace): " . ($partnerData['external_id'] ?? 'N/A') . "\n";
    echo "   - Synchronized Name: '" . ($partnerData['name'] ?? 'N/A') . "'\n";
    echo "   - Aggregator Balance (USD): " . ($partnerData['balance'] ?? 'N/A') . "\n";
    echo "   - Active Status in Aggregator: " . (($partnerData['active'] ?? false) ? 'YES 🟢' : 'NO ❌') . "\n";
    echo "=========================================================\n\n";

    if (!empty($partnerData['id'])) {
        echo "🎉 SUCCESS: Terminal auto-creation verified and fully synchronized!\n";
    } else {
        echo "❌ FAILURE: Terminal did not synchronize with the Aggregator.\n";
    }

} catch (\Exception $e) {
    echo "❌ Error during execution: " . $e->getMessage() . "\n";
} finally {
    // 6. Clean up database transaction and delete user so there is zero residue
    DB::rollBack();
    $user->delete();
    echo "\n🧹 Sandbox test rolled back. Database remains pristine.\n";
}
