<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\WildflowCatalog;
use App\Models\Product;

echo "=== Specific Products Check v2 ===\n";

$catalogs = WildflowCatalog::all();

echo "PlayStation TR search:\n";
foreach ($catalogs as $c) {
    if (stripos($c->title, 'PlayStation TR') !== false) {
        echo "  - ID: {$c->id}, Title: {$c->title}, SKU: {$c->sku}, Active: " . ($c->is_active ? 'YES' : 'NO') . ", Retail Price: {$c->retail_price} {$c->currency_code}\n";
    }
}

echo "\nPlaystation IN search:\n";
foreach ($catalogs as $c) {
    if (stripos($c->title, 'Playstation IN') !== false) {
        echo "  - ID: {$c->id}, Title: {$c->title}, SKU: {$c->sku}, Active: " . ($c->is_active ? 'YES' : 'NO') . ", Retail Price: {$c->retail_price} {$c->currency_code}\n";
    }
}
