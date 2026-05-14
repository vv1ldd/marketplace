<?php

namespace App\Console\Commands;

use App\Models\WildflowCatalog;
use App\Models\Brand;
use App\Services\MappingService;
use Illuminate\Console\Command;

class NormalizeBrands extends Command
{
    protected $signature = 'app:normalize-brands {--dry-run : Only show what would be changed}';

    protected $description = 'Normalize all brands in the Wildflow catalog and merge aliases into master brands';

    public function handle()
    {
        $this->info("Starting brand normalization...");

        $items = WildflowCatalog::all();
        $total = $items->count();
        $this->info("Processing {$total} items...");

        $changed = 0;
        $bar = $this->output->createProgressBar($total);

        foreach ($items as $item) {
            $itemData = $item->data;
            $productData = data_get($itemData, 'data.product') ?? data_get($itemData, 'product') ?? $itemData;
            
            $externalBrandName = data_get($productData, 'categories.0.name') ?? data_get($itemData, 'categories.0.name') ?? 'Unknown';
            $jsonTitle = data_get($productData, 'title') ?? data_get($itemData, 'title') ?? $item->title;
            $jsonSku = data_get($productData, 'sku') ?? data_get($itemData, 'sku') ?? $item->sku;

            // Try to resolve master brand
            $brandId = MappingService::resolveBrand(
                1, // Wildflow provider ID
                $externalBrandName,
                $jsonSku,
                $jsonTitle
            );

            if ($brandId && $item->brand_id !== $brandId) {
                if (!$this->option('dry-run')) {
                    $item->brand_id = $brandId;
                }
                $changed++;
            }

            // Resolve Region
            $regionCode = data_get($productData, 'regions.0.code') ?? data_get($itemData, 'regions.0.code') ?? data_get($itemData, 'data.product.regions.0.code');
            $regionId = MappingService::resolveRegion($regionCode, $jsonTitle);
            
            if ($regionId && $item->region_id !== $regionId) {
                if (!$this->option('dry-run')) {
                    $item->region_id = $regionId;
                }
                $changed++;
            }

            // Resolve Category
            $categories = data_get($productData, 'categories', []);
            $normalizedCategory = MappingService::resolveCategory($categories);
            if ($normalizedCategory && $item->category !== $normalizedCategory) {
                if (!$this->option('dry-run')) {
                    $item->category = $normalizedCategory;
                }
                $changed++;
            }

            // Extract Redemption Metadata
            $metadata = MappingService::extractRedemptionMetadata($itemData);
            foreach ($metadata as $key => $value) {
                if ($value && $item->{$key} !== $value) {
                    if (!$this->option('dry-run')) {
                        $item->{$key} = $value;
                    }
                    $changed++;
                }
            }

            if (!$this->option('dry-run') && $item->isDirty()) {
                $item->save();
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Normalization of catalog items completed. " . ($this->option('dry-run') ? "Would have changed" : "Changed") . " {$changed} items.");

        $this->info("Processing products...");
        $products = \App\Models\Product::all();
        $totalProducts = $products->count();
        $changedProducts = 0;
        $bar = $this->output->createProgressBar($totalProducts);

        foreach ($products as $product) {
            $masterName = MappingService::normalizeBrandName($product->name . ' ' . ($product->brand?->name ?? ''));
            if ($masterName) {
                $masterBrand = Brand::firstOrCreate(['name' => $masterName]);
                if ($product->brand_id !== $masterBrand->id) {
                    if (!$this->option('dry-run')) {
                        $product->update(['brand_id' => $masterBrand->id]);
                    }
                    $changedProducts++;
                }
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();
        $this->info("Normalization of products completed. " . ($this->option('dry-run') ? "Would have changed" : "Changed") . " {$changedProducts} products.");
        
        $this->info("Cleaning up unused brands...");
        // Optionally delete brands that have no products and no catalog items
        if (!$this->option('dry-run')) {
            $unusedBrands = Brand::whereDoesntHave('products')
                ->whereDoesntHave('wildflowCatalogs')
                ->get();
            
            $count = $unusedBrands->count();
            foreach ($unusedBrands as $brand) {
                $brand->delete();
            }
            $this->info("Deleted {$count} unused brands.");
        }
    }
}
