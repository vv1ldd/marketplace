<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Brand;
use App\Services\MappingService;
use App\Models\WildflowCatalog;

$item = WildflowCatalog::where('sku', 'VOUCHER-GC-TINDER-1MONTHGOLD-UAE-AE-25.4USD-CTLG-6704')->first();
$externalName = 'Tinder Gold';
$sku = $item->sku;
$title = 'Tinder 1MonthGold UAE';

$brandId = MappingService::resolveBrand(1, $externalName, $sku, $title);
echo "Brand ID: " . $brandId . "\n";
echo "Brand Name: " . Brand::find($brandId)?->name . "\n";
