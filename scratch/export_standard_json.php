<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\WildflowCatalog;
use Illuminate\Support\Facades\File;

$outputFile = 'meanly_standard_catalog.json';
$handle = fopen($outputFile, 'w');
fwrite($handle, "[\n");

$first = true;
$count = 0;

$standardizer = new \App\Services\StandardizationService();

WildflowCatalog::with(['brand', 'region'])->chunk(500, function($items) use (&$handle, &$first, &$count, $standardizer) {
    foreach ($items as $item) {
        if (!$first) {
            fwrite($handle, ",\n");
        }
        
        $entry = $standardizer->standardizeCatalogItem($item);

        fwrite($handle, json_encode($entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $first = false;
        $count++;
    }
});

fwrite($handle, "\n]");
fclose($handle);

echo "ЭКСПОРТ ЗАВЕРШЕН:\n";
echo "-------------------\n";
echo "Всего экспортировано товаров: $count\n";
echo "Файл: $outputFile\n";
