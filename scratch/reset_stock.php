<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$stocks = App\Models\WarehouseStock::all();
$updated = 0;

foreach ($stocks as $stock) {
    $product = $stock->product;
    if ($product) {
        $count = App\Models\ProductInventory::where('warehouse_id', $stock->warehouse_id)
            ->where('sku', $product->sku)
            ->where('is_used', false)
            ->count();
        $stock->update(['count' => $count]);
        $updated++;
        echo "Updated stock for SKU {$product->sku} to {$count}\n";
    }
}

echo "Done. Updated {$updated} records.\n";
