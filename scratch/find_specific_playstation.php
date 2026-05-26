<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\WildflowCatalog;
use App\Models\Product;

echo "=== Specific Products Check ===\n";

$queries = ['TL500', '13500', 'TR', 'IN', 'PlayStation TR', 'Playstation IN'];

foreach ($queries as $q) {
    echo "\nSearching for: '$q' in WildflowCatalog\n";
    $items = WildflowCatalog::all();
    $found = 0;
    foreach ($items as $item) {
        $title = $item->title;
        if (stripos($title, $q) !== false) {
            $found++;
            if ($found <= 5) {
                echo "  - ID: {$item->id}, Title: {$title}, SKU: {$item->sku}, Active: " . ($item->is_active ? 'YES' : 'NO') . ", Retail Price: {$item->retail_price} {$item->currency_code}\n";
            }
        }
    }
    echo "Total found: {$found}\n";
}

echo "\nSearching in Products:\n";
foreach ($queries as $q) {
    echo "\nSearching for: '$q' in Product\n";
    $products = Product::all();
    $found = 0;
    foreach ($products as $p) {
        if (stripos($p->name, $q) !== false || stripos($p->sku, $q) !== false) {
            $found++;
            if ($found <= 5) {
                echo "  - ID: {$p->id}, Name: {$p->name}, SKU: {$p->sku}, Active: " . ($p->is_active ? 'YES' : 'NO') . ", Catalog SKU: {$p->wildflow_catalog_sku}\n";
            }
        }
    }
    echo "Total found: {$found}\n";
}
