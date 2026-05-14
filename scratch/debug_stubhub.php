<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\CardImageService;
use App\Models\WildflowCatalog;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

$service = app(CardImageService::class);
$manager = new ImageManager(new Driver());

$sku = 'VOUCHER-GC-STUBHUB-US-100USD-RTL-887';
$item = WildflowCatalog::where('sku', $sku)->first();

if (!$item) die("Product not found\n");

echo "Debugging logo for: " . $item->sku . "\n";
$logoPath = $service->resolveLogoPath($item);
echo "Original Logo Path: $logoPath\n";

if ($logoPath) {
    $slug = 'debug_stubhub';
    $enhanced = $service->enhanceLogoWithAI($logoPath, $slug);
    echo "Enhanced Logo Path: $enhanced\n";
    
    if ($enhanced && file_exists($enhanced)) {
        // Copy to public for web view if needed
        copy($enhanced, public_path('debug_logo_result.png'));
        echo "Check public/debug_logo_result.png\n";
    }
}
