<?php

namespace App\Console\Commands;

use App\Models\WildflowCatalog;
use App\Services\CardImageService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class EnhanceBrandLogos extends Command
{
    protected $signature = 'app:enhance-brand-logos {--force : Force re-enhancement of existing logos}';
    protected $description = 'Batch enhance provider logos using AI (Real-ESRGAN)';

    public function handle(CardImageService $service)
    {
        $this->info("Starting brand logo enhancement...");

        // Group by brand to avoid redundant work
        $items = WildflowCatalog::with('brand')
            ->whereNotNull('brand_id')
            ->get()
            ->unique('brand_id');

        $this->info("Found " . $items->count() . " unique brands to process.");

        $bar = $this->output->createProgressBar($items->count());
        $bar->start();

        foreach ($items as $item) {
            try {
                $brand = $item->brand;
                $slug = $brand ? Str::slug($brand->name) : 'brand-' . $item->brand_id;
                
                // 1. Resolve path (downloads if needed)
                $originalPath = $service->resolveLogoPath($item);
                
                if (!$originalPath) {
                    $bar->advance();
                    continue;
                }

                // 2. Check if already enhanced
                $enhancedPath = public_path("storage/brands/enhanced/{$slug}.png");
                if (file_exists($enhancedPath) && !$this->option('force')) {
                    $bar->advance();
                    continue;
                }

                // 3. Skip SVGs
                if (str_ends_with(strtolower($originalPath), '.svg')) {
                    $bar->advance();
                    continue;
                }

                // 4. Enhance
                $service->enhanceLogoWithAI($originalPath, $slug);
                
            } catch (\Throwable $e) {
                $this->error("\nFailed to enhance logo for brand {$item->brand_id}: " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->info("\nLogo enhancement completed!");
    }
}
