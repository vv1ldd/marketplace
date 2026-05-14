<?php

namespace App\Console\Commands;

use App\Models\Shop;
use App\Models\WildflowCatalog;
use App\Services\CardImageService;
use Illuminate\Console\Command;

class GenerateCatalogCards extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:generate-cards {--shop=1} {--limit=100} {--force}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate beautiful product card images for catalog items using enhanced logos';

    /**
     * Execute the console command.
     */
    public function handle(CardImageService $service)
    {
        $shopId = $this->option('shop');
        $shop = Shop::find($shopId);

        if (!$shop) {
            $this->error("Shop with ID {$shopId} not found.");
            return 1;
        }

        $limit = (int)$this->option('limit');
        $force = $this->option('force');

        $this->info("Starting card generation for shop: {$shop->name} (ID: {$shopId})");
        $this->info("Using Force: " . ($force ? 'YES' : 'NO'));

        $query = WildflowCatalog::query();
        
        // Only items with brands often look better
        $query->whereNotNull('brand_id');
        
        $total = $query->count();
        $this->info("Total items found: {$total}");

        $bar = $this->output->createProgressBar(min($total, $limit));
        $bar->start();

        $count = 0;
        $query->orderBy('id', 'desc')->chunk(50, function($items) use ($service, $shop, $force, $bar, $limit, &$count) {
            foreach ($items as $item) {
                if ($count >= $limit) return false;

                $service->generateForCatalogItem($item, $shop, 'light', $force);
                
                $bar->advance();
                $count++;
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("Generation complete! Images saved to: public/img/card/sh_{$shopId}/");

        return 0;
    }
}
