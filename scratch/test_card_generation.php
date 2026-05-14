<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Brand;
use App\Models\Shop;
use App\Models\WildflowCatalog;
use App\Services\CardImageService;

$brandName = 'Old Navy';
$brand = Brand::where('name', 'like', "%{$brandName}%")->first();
$shop = Shop::first(); // Just for testing

if (!$brand || !$shop) {
    echo "Brand or Shop not found\n";
    exit;
}

$item = WildflowCatalog::where('brand_id', $brand->id)->whereNotNull('data')->first();
if (!$item) {
    echo "No catalog item found for brand\n";
    exit;
}

// Ensure the brand logo URL is set to the raster one for this test
$item->brand_logo_url = "https://d1upatzsvnpphr.cloudfront.net/ezpaypin/products/images/2021/08/old-navy.jpg";

$service = app(CardImageService::class);
$result = $service->generateForCatalogItem($item, $shop, 'light', true);

echo "Card generation result:\n";
print_r($result);
echo "\nCheck the image at: " . public_path($result['images']['main']) . "\n";
