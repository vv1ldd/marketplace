<?php
use App\Models\WildflowCatalog;

$item = WildflowCatalog::where('brand_id', '!=', null)
    ->where('title', 'like', '%Old Navy%')
    ->first();

if (!$item) {
    echo "Item not found\n";
    exit;
}

echo "Brand: " . ($item->brand?->name ?? 'N/A') . "\n";
echo "Logo URL: " . ($item->brand_logo_url ?? 'EMPTY') . "\n";
echo "Slug: " . ($item->brand?->slug ?? 'N/A') . "\n";
