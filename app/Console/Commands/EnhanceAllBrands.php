<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Brand;
use App\Models\WildflowCatalog;
use App\Services\CardImageService;
use Illuminate\Support\Facades\Storage;

class EnhanceAllBrands extends Command
{
    protected $signature = 'brands:enhance {--limit= : Limit the number of brands} {--force : Force enhancement even if already exists}';
    protected $description = 'Pre-enhance all brand logos using Neural AI and ImageMagick';

    public function handle(CardImageService $service)
    {
        $brandIds = WildflowCatalog::whereNotNull('brand_id')
            ->distinct()
            ->pluck('brand_id');

        $brands = Brand::whereIn('id', $brandIds)->get();
        
        if ($this->option('limit')) {
            $brands = $brands->take((int)$this->option('limit'));
        }

        $this->info("Found " . $brands->count() . " active brands to enhance.");

        foreach ($brands as $brand) {
            $this->info("Processing {$brand->name}...");
            
            // Find a sample item to resolve logo
            $item = WildflowCatalog::where('brand_id', $brand->id)->first();
            if (!$item) continue;

            try {
                $path = $service->resolveLogoPath($item);
                
                if ($path && file_exists($path)) {
                    $this->comment("  Enhanced logo: " . basename($path));
                    
                    // Update brand logo field to point to the enhanced version
                    // We want the path relative to public/, e.g. storage/brands/enhanced/slug.png
                    $relativePath = str_replace(public_path() . '/', '', $path);
                    
                    if ($brand->logo !== $relativePath) {
                        $brand->logo = $relativePath;
                        $brand->save();
                        $this->info("  Updated brand record with new logo path.");
                    }
                } else {
                    $this->warn("  Could not resolve logo for {$brand->name}");
                }
            } catch (\Exception $e) {
                $this->error("  Error processing {$brand->name}: " . $e->getMessage());
            }
        }

        $this->info("Enhancement process completed.");
    }
}
