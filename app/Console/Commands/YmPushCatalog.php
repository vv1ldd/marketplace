<?php

namespace App\Console\Commands;

use App\Http\Services\YmService;
use App\Models\Product;
use App\Models\Settings;
use App\Models\Shop;
use App\Services\CanonicalCategoryResolver;
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
            
            $query = Product::query()->where('shop_id', $shop->id);

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
            $resolver = app(CanonicalCategoryResolver::class);
            $fallbackCategoryId = (int) ($shop->ym_category_id ?: Settings::get('YM_CATEGORY_ID', 989939));

            // Chunk by 500 as per Yandex API limits
            $chunks = $products->chunk(500);

            foreach ($chunks as $chunk) {
                try {
                    $offers = $chunk->map(function (Product $product) use ($resolver, $fallbackCategoryId, $shop) {
                        $categoryId = $resolver->yandexCategoryId($resolver->forProduct($product), $fallbackCategoryId);
                        if ((int) $product->market_category_id !== $categoryId) {
                            $product->market_category_id = $categoryId;
                            $product->save();
                        }

                        return ['offer' => $product->toYmOffer($categoryId, $shop->id)];
                    })->toArray();

                    $response = $service->offerMappingsUpdate($offers);
                    $errors = collect(data_get($response, 'results', []))
                        ->flatMap(fn (array $result): array => $result['errors'] ?? [])
                        ->count();

                    if (data_get($response, 'status') === 'ERROR' || $errors > 0) {
                        $this->warn('Yandex returned mapping errors for this chunk.');
                        $this->line(json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                    }

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
