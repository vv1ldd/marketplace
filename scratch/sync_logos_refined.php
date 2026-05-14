<?php

use App\Models\Brand;

// 1. First, clear current logos to re-sync cleanly (or just those we want to fix)
// For safety, let's just re-run the logic carefully.
$brands = Brand::all();
$dir = public_path('img/logos');
$logoFiles = array_diff(scandir($dir), ['.', '..']);
$synced = 0;

echo "Starting Refined Word-Boundary Sync...\n";

foreach($brands as $brand) {
    $name = strtolower($brand->name);
    $slug = strtolower(preg_replace('/[^A-Za-z0-9]/', '', $name));
    
    $bestMatch = null;
    
    foreach($logoFiles as $file) {
        $fileSlug = strtolower(pathinfo($file, PATHINFO_FILENAME));
        
        // Skip too short slugs for fuzzy matching
        if (strlen($fileSlug) < 3) continue;

        $isMatch = false;
        
        // Match logic:
        // 1. Exact slug match
        if ($slug === $fileSlug) {
            $isMatch = true;
        } 
        // 2. Word boundary match (e.g. "Amazon UAE" has "Amazon" as a word)
        elseif (preg_match("/\b" . preg_quote($fileSlug, '/') . "\b/i", $name)) {
            // Check if it's a false positive
            if ($fileSlug === 'riot' && str_contains($name, 'marriott')) $isMatch = false;
            elseif ($fileSlug === 'apple' && str_contains($name, 'applebee')) $isMatch = false;
            elseif ($fileSlug === 'uber' && str_contains($name, 'auberge')) $isMatch = false;
            elseif ($fileSlug === 'ebay' && str_contains($name, 'skates')) $isMatch = false;
            elseif ($fileSlug === 'esso' && str_contains($name, 'accessories')) $isMatch = false;
            else $isMatch = true;
        }
        // 3. Special cases
        elseif (str_contains($name, 'itunes') && $fileSlug === 'apple') $isMatch = true;
        elseif (str_contains($name, 'app store') && $fileSlug === 'apple') $isMatch = true;
        elseif (str_contains($name, 'psn') && $fileSlug === 'playstation') $isMatch = true;

        if($isMatch) {
            $bestMatch = "img/logos/" . $file;
            break; 
        }
    }
    
    if ($bestMatch) {
        $brand->logo = $bestMatch;
        $brand->save();
        $synced++;
    } else {
        // Clear logo if it was a false positive from previous run
        if ($brand->logo && str_contains($brand->logo, 'img/logos/')) {
            $brand->logo = null;
            $brand->save();
        }
    }
}

echo "\nREFINED SYNC COMPLETE!\n";
echo "Total Brands with Verified Logo: " . Brand::whereNotNull('logo')->count() . "\n";
