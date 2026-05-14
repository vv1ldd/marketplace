<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Brand;
use App\Models\WildflowCatalog;
use App\Services\MappingService;
use Illuminate\Support\Facades\DB;

echo "Starting Brand Cleanup and Re-mapping with REGEX...\n";

// 1. Merge Common Duplicates
$merges = [
    'PlayStation' => ['Sony', 'playstation', 'Sony Interactive Entertainment', 'PSN', 'Play station'],
    'Amazon' => ['amazon', 'AMZN'],
    'Apple' => ['itunes', 'iTunes', 'Apple Gift Card'],
    'Xbox' => ['Xbox Live', 'Xbox Game Pass', 'Xbox Game Pass ultimate'],
    'Nintendo' => ['Nintendo eShop', 'Nintendo Online', 'Nintendo ES'],
    'Google Play' => ['google play', 'Google Play Store'],
    'Roblox' => ['ROBLOX'],
    'HUAWEI' => ['HUAWEI Gift Card', 'HUAWEI Iraq'],
    'PUBG' => ['PUBG Mobile'],
    'Tinder' => ['Tinder Gold', 'Tinder Plus'],
    'Binance' => ['Binance Gift Card'],
    'Netflix' => ['netflix'],
    'Steam' => ['steam'],
];

foreach ($merges as $masterName => $aliases) {
    $masterBrand = Brand::firstOrCreate(['name' => $masterName]);
    
    foreach ($aliases as $alias) {
        $aliasBrand = Brand::where('name', $alias)->first();
        if ($aliasBrand && $aliasBrand->id !== $masterBrand->id) {
            echo "Merging '{$alias}' (ID: {$aliasBrand->id}) into '{$masterName}' (ID: {$masterBrand->id})\n";
            WildflowCatalog::where('brand_id', $aliasBrand->id)->update(['brand_id' => $masterBrand->id]);
            \App\Models\Product::where('brand_id', $aliasBrand->id)->update(['brand_id' => $masterBrand->id]);
            $aliasBrand->delete();
        }
    }
}

// 2. Re-map products
echo "Re-mapping all Wildflow products...\n";
$items = WildflowCatalog::all();
$provider = \App\Models\Provider::where('type', 'wildflow')->first();

foreach ($items as $item) {
    $data = $item->data;
    $productData = $data['data']['product'] ?? $data['data'] ?? $data['product'] ?? $data;
    
    // Improved category extraction
    $externalBrandName = null;
    if (!empty($productData['categories'])) {
        $externalBrandName = $productData['categories'][0]['name'] ?? null;
    }
    
    if (!$externalBrandName) {
        $externalBrandName = $item->category ?: 'WILDFLOW GIFTS';
    }

    $title = $data['title'] ?? $productData['title'] ?? $item['sku'];

    $brandId = MappingService::resolveBrand(
        $provider->id,
        $externalBrandName,
        $item->sku,
        $title
    );
    
    // If MappingService found something different, update it
    if ($brandId && $item->brand_id !== $brandId) {
        $item->brand_id = $brandId;
        $item->save();
    }
}

echo "Cleanup completed.\n";
