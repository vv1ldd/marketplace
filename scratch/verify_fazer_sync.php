<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Provider;
use App\Models\WildflowCatalog;

echo "--- SYNC VERIFICATION START ---" . PHP_EOL;

$provider = Provider::where('type', 'fazer')->first();

// 1. Fetch RAW data from LOCAL kernel DB
echo "Reading from kernel DB..." . PHP_EOL;
$pdo = new PDO('sqlite:../api-wildflow-dev/database/database.sqlite');
$stmt = $pdo->prepare("SELECT data FROM catalogs WHERE provider = 'fazer' LIMIT 10");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($rows) . " raw items." . PHP_EOL;

// 2. Simulate the logic from SyncCatalogsCommand.php
$syncedCount = 0;
foreach ($rows as $row) {
    $rawData = json_decode($row['data'], true);
    
    $item = [
        'service_sku' => (string)($rawData['id'] ?? $rawData['product_id'] ?? $rawData['game_id'] ?? ''),
        'name' => $rawData['display_name'] ?? $rawData['name'] ?? 'Item',
        'min_price' => (float)($rawData['price'] ?? 0),
        'max_price' => (float)($rawData['price'] ?? 0),
        'currency' => $rawData['currency'] ?? 'USD',
        'is_available' => true,
        'raw_data' => $rawData
    ];

    // --- MARKETPLACE VALIDATION LOGIC (The fix) ---
    $hasBuyingPrice = ($item['buying_price'] ?? $item['min_price'] ?? null) !== null;
    
    if (!$hasBuyingPrice) continue;

    $catalog = WildflowCatalog::updateOrCreate(
        ['provider_id' => $provider->id, 'service_sku' => $item['service_sku']],
        [
            'sku' => 'WFC-' . substr(md5($item['service_sku']), 0, 12),
            'is_active' => true,
            'purchase_price' => $item['min_price'],
            'type' => 'catalog', // Fix mandatory field
            'data' => $item
        ]
    );
    $syncedCount++;
}

echo "Successfully synced and ACTIVATED {$syncedCount} items." . PHP_EOL;

// 3. Verify Accessor
$sample = WildflowCatalog::where('provider_id', $provider->id)->where('is_active', true)->first();
if ($sample) {
    echo "--- SAMPLE CHECK ---" . PHP_EOL;
    echo "SKU: " . $sample->service_sku . PHP_EOL;
    echo "Is Active: " . ($sample->is_active ? 'Yes' : 'No') . PHP_EOL;
    echo "Purchase Price: " . $sample->purchase_price . PHP_EOL;
    if ($sample->purchase_price > 0 && $sample->purchase_price < 99999) {
        echo "✅ SUCCESS: Price correctly resolved from Fazer structure!" . PHP_EOL;
    } else {
        echo "❌ FAILURE: Price resolution failed!" . PHP_EOL;
    }
}
