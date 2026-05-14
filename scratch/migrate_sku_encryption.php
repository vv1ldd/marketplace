<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\WildflowCatalog;
use App\Models\ProviderProduct;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

echo "Starting SKU encryption migration (safe mode)...\n";

function processModel($query, $fields, $table) {
    $count = 0;
    $query->chunk(100, function ($items) use ($fields, $table, &$count) {
        foreach ($items as $item) {
            try {
                $needsSave = false;
                $dbRow = DB::table($table)->where('id', $item->id)->first();
                
                foreach ($fields as $field) {
                    $raw = $dbRow->$field;
                    if ($raw && !str_starts_with($raw, 'vlt:')) {
                        $item->$field = $raw;
                        $needsSave = true;
                    }
                }
                
                if ($needsSave) {
                    $item->save();
                    $count++;
                    if ($count % 100 == 0) echo "  Processed {$count} records in {$table}...\n";
                }
            } catch (\Exception $e) {
                echo "  [ERROR] ID {$item->id} in {$table}: " . $e->getMessage() . "\n";
            }
        }
    });
}

// 1. WildflowCatalog
echo "Processing WildflowCatalog...\n";
processModel(WildflowCatalog::query(), ['service_sku'], 'wildflow_catalogs');

// 2. ProviderProduct
echo "Processing ProviderProduct...\n";
processModel(ProviderProduct::query(), ['sku', 'market_sku'], 'provider_products');

// 3. Product
echo "Processing Product...\n";
processModel(Product::query(), ['wildflow_catalog_sku'], 'products');

echo "Migration completed successfully!\n";
