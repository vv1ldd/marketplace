<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Provider;
use App\Models\WildflowCatalog;

$provider = Provider::where('type', 'fazer')->first();

if (!$provider) {
    echo "Provider 'fazer' not found." . PHP_EOL;
    exit;
}

echo "Provider ID: " . $provider->id . PHP_EOL;
echo "Provider Name: " . $provider->name . PHP_EOL;

$lastItem = WildflowCatalog::where('provider_id', $provider->id)
    ->orderBy('updated_at', 'desc')
    ->first();

if (!$lastItem) {
    echo "No catalog items found for this provider." . PHP_EOL;
    exit;
}

echo "Last updated item SKU: " . $lastItem->sku . PHP_EOL;
echo "Last updated at: " . $lastItem->updated_at . PHP_EOL;
echo "Is Active: " . ($lastItem->is_active ? 'Yes' : 'No') . PHP_EOL;
echo "Purchase Price (via accessor): " . $lastItem->purchase_price . PHP_EOL;

echo "--- DATA ARRAY STRUCTURE ---" . PHP_EOL;
$data = $lastItem->data;
if (is_string($data)) {
    $data = json_decode($data, true);
}
print_r($data);

echo PHP_EOL . "--- STATUS SUMMARY ---" . PHP_EOL;
$activeCount = WildflowCatalog::where('provider_id', $provider->id)->where('is_active', true)->count();
$inactiveCount = WildflowCatalog::where('provider_id', $provider->id)->where('is_active', false)->count();
echo "Active: $activeCount" . PHP_EOL;
echo "Inactive: $inactiveCount" . PHP_EOL;

if ($inactiveCount > 0) {
    echo PHP_EOL . "Example Inactive Item:" . PHP_EOL;
    $inactiveItem = WildflowCatalog::where('provider_id', $provider->id)->where('is_active', false)->first();
    print_r($inactiveItem->data);
}
