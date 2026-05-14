<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Customer;
use App\Models\Order\Order;
use App\Models\LegalEntity;
use Illuminate\Support\Facades\DB;

DB::beginTransaction();

try {
    $clients = User::clients()->get();
    echo "Starting migration of " . $clients->count() . " clients...\n";

    foreach ($clients as $user) {
        // Create Customer
        $customer = Customer::create([
            'shop_id' => $user->shop_id ?? 1,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'middle_name' => $user->middle_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'password' => $user->password,
            'ym_user_id' => $user->ym_user_id,
            'meta' => $user->meta,
            'source_site' => $user->source_site,
            'source_user_id' => $user->source_user_id,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ]);

        // Update Orders
        Order::where('user_id', $user->id)->update(['customer_id' => $customer->id]);

        // Update Legal Entities
        LegalEntity::where('user_id', $user->id)->update(['customer_id' => $customer->id]);

        echo ".";
    }

    echo "\nMigration complete. Verifying...\n";
    echo "Customers Count: " . Customer::count() . "\n";
    
    DB::commit();
    echo "Transaction Committed.\n";

} catch (\Exception $e) {
    DB::rollBack();
    echo "ERROR: " . $e->getMessage() . "\n";
}
