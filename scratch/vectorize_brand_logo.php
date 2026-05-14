<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Brand;
use App\Models\WildflowCatalog;
use App\Services\CardImageService;

$brandName = 'Old Navy';
$brand = Brand::where('name', 'like', "%{$brandName}%")->first();

if (!$brand) {
    echo "Brand not found\n";
    exit;
}

$item = WildflowCatalog::where('brand_id', $brand->id)->whereNotNull('data')->first();
if (!$item) {
    echo "No catalog item found for brand to extract logo\n";
    exit;
}

$logoUrl = "https://d1upatzsvnpphr.cloudfront.net/ezpaypin/products/images/2021/08/spotify.jpg";

if (!$logoUrl) {
    echo "No logo source found\n";
    exit;
}

$service = app(CardImageService::class);
// Reflection to access private methods for this one-time task
$reflection = new ReflectionClass($service);

$loadResource = $reflection->getMethod('loadResourceImage');
$loadResource->setAccessible(true);
$raster = $loadResource->invoke($service, $logoUrl);

if (!$raster) {
    echo "Failed to load raster image from $logoUrl\n";
    exit;
}

$toPixelSvg = $reflection->getMethod('rasterToPixelSvg');
$toPixelSvg->setAccessible(true);
$svgContent = $toPixelSvg->invoke($service, $raster);

$slug = \Illuminate\Support\Str::slug($brand->name);
$filename = "{$slug}.svg";
$savePath = public_path("img/logos/{$filename}");

if (!is_dir(public_path('img/logos'))) {
    mkdir(public_path('img/logos'), 0775, true);
}

file_put_contents($savePath, $svgContent);
echo "Saved vectorized logo to: $savePath\n";

$brand->logo = "img/logos/{$filename}";
$brand->save();
echo "Updated Brand record in database.\n";

imagedestroy($raster);
