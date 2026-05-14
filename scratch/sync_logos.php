<?php

use App\Models\Brand;

$brands = Brand::whereNull('logo')->get();
$dir = public_path('img/logos');
$logoFiles = array_diff(scandir($dir), ['.', '..']);
$synced = 0;

echo "Starting Super-Sync for " . $brands->count() . " brands...\n";

foreach($brands as $brand) {
    $name = strtolower($brand->name);
    $slug = strtolower(preg_replace('/[^A-Za-z0-9]/', '', $name));
    
    foreach($logoFiles as $file) {
        $fileSlug = strtolower(pathinfo($file, PATHINFO_FILENAME));
        
        // Match logic:
        // 1. Exact match (slug)
        // 2. Name contains logo key (e.g. 'Amazon UAE' contains 'amazon')
        // 3. Special cases (Apple, itunes, etc)
        $isMatch = false;
        
        if ($slug === $fileSlug) $isMatch = true;
        elseif (str_contains($slug, $fileSlug) && strlen($fileSlug) > 3) $isMatch = true;
        elseif (str_contains($name, 'amazon') && $fileSlug === 'amazon') $isMatch = true;
        elseif (str_contains($name, 'apple') && $fileSlug === 'apple') $isMatch = true;
        elseif (str_contains($name, 'itunes') && $fileSlug === 'apple') $isMatch = true;
        elseif (str_contains($name, 'app store') && $fileSlug === 'apple') $isMatch = true;
        elseif (str_contains($name, 'psn') && $fileSlug === 'playstation') $isMatch = true;

        if($isMatch) {
            $brand->logo = "img/logos/" . $file;
            $brand->save();
            $synced++;
            echo "MATCH: {$brand->name} -> {$file}\n";
            continue 2;
        }
    }
}

echo "\nSUPER-SYNC COMPLETE!\n";
echo "New Brands Synced: {$synced}\n";
echo "Total Brands with Logo: " . Brand::whereNotNull('logo')->count() . "\n";
