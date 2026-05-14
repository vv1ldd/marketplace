<?php

namespace App\Console\Commands;

use App\Http\Services\YmService;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class YmPullCatalog extends Command
{
    protected $signature = 'ym:pull-catalog {--shop= : Process only specific shop ID}';
    protected $description = 'Import and refresh products from Yandex Market to ensure parity';

    public function handle()
    {
        $this->info('Starting Yandex Market Catalog Pull (Sync from Yandex)...');

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
            $service = new YmService($shop);
            
            $businessId = (int)($shop->business_id ?? $shop->api_application?->client_id);
            if (!$businessId) {
                $this->error("Shop {$shop->name} is missing businessId.");
                continue;
            }

            $pageToken = null;
            $totalImported = 0;

            do {
                try {
                    $response = $service->getOffers($businessId, $pageToken);
                    $offers = $response['offerMappings'] ?? [];
                    $pageToken = $response['paging']['nextPageToken'] ?? null;

                    foreach ($offers as $item) {
                        $offer = $item['offer'] ?? [];
                        if (empty($offer['offerId'])) continue;

                        $mapping = $item['mapping'] ?? [];
                        
                        // Find or Create Product
                        $product = Product::updateOrCreate(
                            ['sku' => $offer['offerId']],
                            [
                                'name' => $offer['name'] ?? 'No Name',
                                'vendor' => $offer['vendor'] ?? null,
                                'description' => $offer['description'] ?? null,
                                'category' => $mapping['marketCategoryName'] ?? null,
                                'market_category_name' => $mapping['marketCategoryName'] ?? null,
                                'price_rub' => isset($offer['price']) ? (int)($offer['price'] * 100) : 0,
                                'ym_errors' => array_merge(
                                    $mapping['errors'] ?? [],
                                    $offer['contentErrors'] ?? []
                                ),
                                'data' => array_merge($product->data ?? [], ['ym_raw' => $item]),
                                'shop_id' => $shop->id,
                            ]
                        );

                        $totalImported++;
                    }
                    
                    $this->info("Imported {$totalImported} products...");

                } catch (\Exception $e) {
                    $this->error("Error pulling batch: " . $e->getMessage());
                    break;
                }
            } while ($pageToken);

            $this->info("Finalized shop {$shop->name}. Total: {$totalImported} products.");
        }

        $this->info('Full catalog pull completed!');
        return 0;
    }
}
