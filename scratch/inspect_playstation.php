<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\WildflowCatalog;
use App\Models\Product;
use App\Models\ProviderProduct;
use Illuminate\Support\Facades\DB;

$vault = app(\App\Services\VaultTransitService::class);

echo "=== Inspecting Playstation TR TL500 & Playstation IN Rp13500 ===\n";

// Search in wildflow_catalogs
$catalogs = WildflowCatalog::all();
foreach ($catalogs as $c) {
    $title = $c->title;
    if (stripos($title, 'playstation') !== false) {
        echo "\n[WildflowCatalog ID: {$c->id}]\n";
        echo "SKU (WFC): {$c->sku}\n";
        echo "Title: {$title}\n";
        echo "Active: " . ($c->is_active ? 'YES' : 'NO') . "\n";
        echo "Retail Price: {$c->retail_price} {$c->currency_code}\n";
        echo "Purchase Price: {$c->purchase_price}\n";
        echo "Region: " . ($c->region ? $c->region->name_en : 'None') . "\n";
        try {
            echo "Decrypted service_sku: " . $c->service_sku . "\n";
        } catch (\Exception $e) {
            echo "Error decrypting service_sku: " . $e->getMessage() . "\n";
        }
    }
}

echo "\n=== Inspecting Products table ===\n";
$products = Product::all();
foreach ($products as $p) {
    if (stripos($p->name, 'playstation') !== false) {
        echo "\n[Product ID: {$p->id}]\n";
        echo "SKU: {$p->sku}\n";
        echo "Name: {$p->name}\n";
        echo "Active: " . ($p->is_active ? 'YES' : 'NO') . "\n";
        echo "Wildflow Catalog SKU: {$p->wildflow_catalog_sku}\n";
    }
}
