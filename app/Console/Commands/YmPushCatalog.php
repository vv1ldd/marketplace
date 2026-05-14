<?php

namespace App\Console\Commands;

use App\Http\Services\YmService;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class YmPushCatalog extends Command
{
    protected $signature = 'ym:push-catalog {--shop= : Process only specific shop ID} {--force : Push all products regardless of update status}';
    protected $description = 'Push local product updates to Yandex Market';

    public function handle()
    {
        $this->info('Starting Yandex Market Catalog Push...');

        $shopsQuery = Shop::where('is_active', true)->whereNotNull('api_key');
        if ($this->option('shop')) {
            $shopsQuery->where('id', $this->option('shop'));
        }

        $shops = $shopsQuery->get();

        if ($shops->isEmpty()) {
            $this->error('No active shops with API credentials found.');
            return 1;
        }

        foreach ($shops as $shop) {
            $this->info("Processing shop: {$shop->name} (ID: {$shop->id})");
            
            $query = Product::whereHas('shops', function($q) use ($shop) {
                $q->where('shops.id', $shop->id);
            });

            if (!$this->option('force')) {
                $query->where(function($q) {
                    $q->whereNull('send_to_ym_at')
                      ->orWhereColumn('updated_at', '>', 'send_to_ym_at');
                });
            }

            $products = $query->get();

            if ($products->isEmpty()) {
                $this->info("No products to sync for shop {$shop->name}.");
                continue;
            }

            $this->info("Found {$products->count()} products to push.");
            
            $service = new YmService($shop);
            $categoryId = (int)($shop->ym_category_id ?? \App\Models\Settings::get('YM_CATEGORY_ID', 70301474));

            // Chunk by 500 as per Yandex API limits
            $chunks = $products->chunk(500);

            foreach ($chunks as $chunk) {
                try {
                    $offers = $chunk->map(fn($p) => ["offer" => $p->toYmOffer($categoryId, $shop->id)])->toArray();
                    
                    $service->offerMappingsUpdate($offers);

                    // Update timestamp
                    Product::whereIn('id', $chunk->pluck('id'))->update(['send_to_ym_at' => now()]);
                    
                    $this->info("Successfully pushed " . count($offers) . " products.");
                } catch (\Exception $e) {
                    $this->error("Error pushing chunk: " . $e->getMessage());
                    Log::error("YM Push Error: " . $e->getMessage(), ['shop_id' => $shop->id]);
                }
            }
        }

        $this->info('Catalog sync completed!');
        return 0;
    }
}
