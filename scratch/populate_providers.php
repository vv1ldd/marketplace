<?php

require '/Users/w1ld/Documents/GitHub/new/marketplace/vendor/autoload.php';
$app = require_once '/Users/w1ld/Documents/GitHub/new/marketplace/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    DB::table('api_wildflow_dev.providers')->truncate();

    DB::table('api_wildflow_dev.providers')->insert([
        [
            'name' => 'Meanly Sovereign Warehouse',
            'type' => 'sovereign',
            'is_active' => true,
            'credentials' => null,
            'settings' => null,
            'created_at' => now(),
            'updated_at' => now()
        ],
        [
            'name' => 'Fazercards Digital Supplier',
            'type' => 'fazercards',
            'is_active' => true,
            'credentials' => json_encode(['api_key' => 'FAZER_SECRET_PROD_KEY']),
            'settings' => null,
            'created_at' => now(),
            'updated_at' => now()
        ],
        [
            'name' => 'EZPin Gateway',
            'type' => 'ezpin',
            'is_active' => true,
            'credentials' => json_encode(['client_id' => 'EZ_CLIENT_001', 'client_secret' => 'EZ_SECRET_KEY']),
            'settings' => null,
            'created_at' => now(),
            'updated_at' => now()
        ]
    ]);

    echo "🟢 Successfully populated active providers in api_wildflow_dev database!\n";
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
