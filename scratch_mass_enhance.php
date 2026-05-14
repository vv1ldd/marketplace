<?php

use App\Models\Brand;
use Illuminate\Support\Facades\Storage;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$brands = Brand::whereNotNull('logo_source')
    ->whereNull('logo_enhanced')
    ->get();

$total = $brands->count();
$success = 0;
$failed = 0;
$skipped = 0;

$binary = base_path('bin/realesrgan-ncnn-vulkan');
$models = base_path('bin/models');
$enhancedDir = storage_path('app/public/media/logos/enhanced');

if (!file_exists($enhancedDir)) {
    mkdir($enhancedDir, 0755, true);
}

echo "Starting Real-ESRGAN enhancement for $total brands...\n";

foreach ($brands as $index => $brand) {
    $sourcePath = public_path($brand->logo_source);
    $slug = $brand->slug;

    if (!file_exists($sourcePath)) {
        echo "[$index/$total] Skip $slug: File not found at $sourcePath\n";
        $skipped++;
        continue;
    }

    // Skip SVG files - they are already sharp
    if (str_ends_with(strtolower($sourcePath), '.svg')) {
        echo "[$index/$total] Skip $slug: SVG does not need enhancement\n";
        $brand->update(['logo_enhanced' => $brand->logo_source]); // Just use original
        $skipped++;
        continue;
    }

    $enhancedFilename = "{$slug}.png";
    $enhancedAbsPath = $enhancedDir . '/' . $enhancedFilename;
    $enhancedRelPath = "storage/media/logos/enhanced/{$enhancedFilename}";

    echo "[$index/$total] Enhancing $slug ... ";

    $cmd = "\"$binary\" -i " . escapeshellarg($sourcePath) . " -o " . escapeshellarg($enhancedAbsPath) . " -n realesrgan-x4plus -s 4 -f png -m " . escapeshellarg($models) . " 2>&1";
    
    exec($cmd, $output, $returnCode);

    if ($returnCode === 0 && file_exists($enhancedAbsPath)) {
        $brand->update([
            'logo_enhanced' => $enhancedRelPath,
            'logo_png' => $enhancedRelPath
        ]);
        echo "OK (2x)\n";
        $success++;
    } else {
        echo "FAILED\n";
        if (!empty($output)) {
            echo "Error: " . implode("\n", $output) . "\n";
        }
        $failed++;
    }
}

echo "\nDone!\nSuccess: $success\nFailed: $failed\nSkipped: $skipped\n";
