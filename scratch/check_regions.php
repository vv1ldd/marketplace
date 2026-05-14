<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\WildflowCatalog;
use App\Models\MappingCountry;

$items = WildflowCatalog::all();
$uniqueCodes = [];

foreach ($items as $item) {
    $itemData = $item->data;
    $productData = data_get($itemData, 'data.product') ?? data_get($itemData, 'product') ?? $itemData;
    $regionCode = data_get($productData, 'regions.0.code') ?? data_get($itemData, 'regions.0.code') ?? data_get($itemData, 'data.product.regions.0.code');
    
    if ($regionCode) {
        $uniqueCodes[strtoupper($regionCode)] = true;
    }
}

$codes = array_keys($uniqueCodes);
sort($codes);

echo "Found " . count($codes) . " unique region codes in catalog data:\n";
print_r($codes);

$missingInDb = [];
foreach ($codes as $code) {
    if (!MappingCountry::where('code', $code)->exists()) {
        $missingInDb[] = $code;
    }
}

if (!empty($missingInDb)) {
    echo "\nCodes missing in mapping_countries table:\n";
    print_r($missingInDb);
} else {
    echo "\nAll codes from data are present in mapping_countries table.\n";
}
