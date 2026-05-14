<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\WildflowCatalog;
use App\Models\Shop;
use App\Services\CardImageService;

$item = WildflowCatalog::where('sku', 'VOUCHER-GC-OLD-NAVY-US-10USD-RTL-896')->first();
$shop = Shop::first();

if (!$item) {
    echo "Item not found\n";
    exit;
}

$service = app(CardImageService::class);
$data = $service->generateForCatalogItem($item, $shop, 'nft', true);

echo "Title: " . $data['title'] . "\n";
echo "Generated images:\n";
foreach ($data['images'] as $key => $img) {
    echo "- {$key}: " . public_path($img) . "\n";
}
