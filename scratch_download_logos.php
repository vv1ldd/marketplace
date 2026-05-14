<?php

use App\Models\Brand;
use App\Models\WildflowCatalog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$brands = Brand::all();
$total = $brands->count();
$success = 0;
$failed = 0;
$skipped = 0;

echo "Starting logo download for $total brands...\n";

foreach ($brands as $index => $brand) {
    $slug = $brand->slug;
    if (!$slug) {
        $skipped++;
        continue;
    }

    // 1. BEST EFFORT: Find a generic Gift Card or Wallet item first (highest chance for a clean logo)
    $item = WildflowCatalog::where('brand_id', $brand->id)
        ->where(function($query) use ($brand) {
            $query->where('data', 'like', '%Gift Card%')
                  ->orWhere('data', 'like', '%Wallet%')
                  ->orWhere('data', 'like', '%Top-up%')
                  ->orWhere('data', 'like', '%Recharge%')
                  ->orWhere('data', 'like', '%' . $brand->name . '%');
        })
        ->where(function($query) {
            $query->whereNotNull('image')
                  ->orWhere('data', 'like', '%http%');
        })
        ->first();

    // 2. FALLBACK: Any item if no generic card found
    if (!$item) {
        $item = WildflowCatalog::where('brand_id', $brand->id)
            ->where(function($query) {
                $query->whereNotNull('image')
                      ->orWhere('data', 'like', '%http%');
            })
            ->first();
    }

    if (!$item) {
        $skipped++;
        continue;
    }

    // Extract URL
    $data = $item->data;
    $url = data_get($data, 'image') 
        ?? data_get($data, 'data.image')
        ?? data_get($data, 'data.product.image')
        ?? $item->image;

    if (!$url || !str_starts_with($url, 'http')) {
        $skipped++;
        continue;
    }

    try {
        $url = urldecode($url);
        echo "[$index/$total] Downloading for $slug: $url ... ";
        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept' => 'image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
        ])->timeout(15)->get($url);

        if ($response->successful()) {
            $contentType = $response->header('Content-Type');
            $ext = match($contentType) {
                'image/png' => 'png',
                'image/jpeg', 'image/jpg' => 'jpg',
                'image/svg+xml' => 'svg',
                'image/webp' => 'webp',
                'image/gif' => 'gif',
                default => pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'png'
            };

            $filename = "media/logos/{$slug}.{$ext}";
            Storage::disk('public')->put($filename, $response->body());
            
            $dbPath = "storage/" . $filename;
            $updateData = [
                'logo' => $dbPath, // Backward compatibility
                'logo_source' => $dbPath
            ];
            
            if ($ext === 'png') $updateData['logo_png'] = $dbPath;
            if ($ext === 'svg') $updateData['logo_svg'] = $dbPath;
            
            // Update brand logo paths in DB
            $brand->update($updateData);
            
            echo "SAVED as $ext\n";
            $success++;
        } else {
            echo "FAILED (Status: " . $response->status() . ")\n";
            $failed++;
        }
    } catch (\Throwable $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
        $failed++;
    }
}

echo "\nDone!\nSuccess: $success\nFailed: $failed\nSkipped: $skipped\n";
