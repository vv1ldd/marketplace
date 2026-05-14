<?php

use App\Models\ProviderProduct;
use App\Models\WildflowCatalog;
use App\Services\VaultTransitService;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "🧪 [LABORATORY TEST STARTED]\n";

$vault = app(VaultTransitService::class);
$row = ProviderProduct::find(671);

echo "1️⃣ INITIAL STATE: Item ID 671 active = " . ($row->is_active ? 'YES' : 'NO') . "\n";

// Mock the data exactly like SyncCatalogsCommand does
$skuBidx = $vault->computeBlindIndex($row->sku);
$marketSkuBidx = $vault->computeBlindIndex($row->market_sku);

echo "2️⃣ SIMULATING UPDATE (ACTIVATE)...\n";
ProviderProduct::updateOrCreate(
    ['provider_id' => $row->provider_id, 'sku_bidx' => $skuBidx],
    [
        'sku'            => $row->sku,
        'sku_bidx'       => $skuBidx,
        'market_sku'     => $row->market_sku,
        'market_sku_bidx'=> $marketSkuBidx,
        'is_active'      => true,
        'updated_at'     => now(),
    ]
);

$row->refresh();
echo "✅ AFTER UPDATE: Item ID 671 active = " . ($row->is_active ? 'YES' : 'NO') . "\n";

if (!$row->is_active) {
    echo "❌ FAILED: UpdateOrCreate failed to set is_active to true! Ending early.\n";
    exit;
}

echo "3️⃣ SIMULATING PROCESS DEACTIVATIONS...\n";
// Simulate receivedSkus containing ONLY this SKU
$receivedSkus = [$row->market_sku];
$receivedBidxs = array_map(fn($sku) => $vault->computeBlindIndex($sku), $receivedSkus);

echo "SIMULATION: Running whereNotIn on market_sku_bidx against list of 1 index...\n";

$affected = ProviderProduct::where('provider_id', $row->provider_id)
    ->where('is_active', true)
    ->whereNotIn('market_sku_bidx', $receivedBidxs)
    ->update(['is_active' => false]);

echo "📉 AFFECTED ROWS DEACTIVATED: $affected\n";

$row->refresh();
echo "💥 FINAL STATE OF ITEM 671: active = " . ($row->is_active ? 'YES' : 'NO') . "\n";

if ($row->is_active) {
    echo "🏆 SUCCESS: Simulation worked correctly! Deactivator DID NOT deactivate our row!\n";
} else {
    echo "🚨 BUSTED: THE DEACTIVATOR DEACTIVATED OUR ROW! WHY?!\n";
}
