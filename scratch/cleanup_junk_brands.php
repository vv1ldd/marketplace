<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Brand;
use App\Models\WildflowCatalog;

echo "--- JUNK BRAND CLEANUP ---" . PHP_EOL;

$junkPatterns = ['Usd %', 'Usdc %', 'Usdt %', 'Gate.io%', 'Fazer'];

$deletedCount = 0;
foreach ($junkPatterns as $pattern) {
    $brands = Brand::where('name', 'like', $pattern)->get();
    foreach ($brands as $brand) {
        // Check if brand has any active products
        $productCount = WildflowCatalog::where('brand_id', $brand->id)->count();
        if ($productCount === 0) {
            echo "Deleting empty junk brand: {$brand->name} (ID: {$brand->id})" . PHP_EOL;
            $brand->delete();
            $deletedCount++;
        } else {
            echo "Skipping brand with products: {$brand->name} (Count: {$productCount})" . PHP_EOL;
        }
    }
}

echo "Cleanup finished. Deleted {$deletedCount} junk brands." . PHP_EOL;
