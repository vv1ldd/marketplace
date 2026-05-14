<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\WildflowCatalog;
use App\Services\StandardizationService;

$items = WildflowCatalog::where('sku', '!=', '')->limit(20)->get();
$standardized = $items->map(fn($i) => (new StandardizationService())->standardizeCatalogItem($i));

file_put_contents('standardized_catalog.json', json_encode($standardized, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "Generated standardized_catalog.json with " . $items->count() . " items.\n";
