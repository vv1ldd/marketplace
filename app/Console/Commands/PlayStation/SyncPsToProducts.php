<?php

namespace App\Console\Commands\PlayStation;

use App\Http\Controllers\Ym\MainController as YmMainController;
use App\Http\Services\BinanceService;
use App\Models\PlayStation\PlayStationAlt;
use App\Models\Product;
use Illuminate\Console\Command;

class SyncPsToProducts extends Command
{
    protected $signature = 'ps:sync-to-products {--region_id=063101db-9ac0-4e48-a948-29fe7e3f8dec}';

    protected $description = 'Sync PlayStation catalog from PlayStationAlt to universal products table';

    public function handle()
    {
        $region_id = $this->option('region_id');
        $this->info("Syncing PlayStation products for region: $region_id");

        $items = PlayStationAlt::where('region_id', $region_id)
            ->where('price_with_discount', '>', 0)
            ->get();

        if ($items->isEmpty()) {
            $this->warn("No items found for this region.");
            return;
        }

        $binance_service = new BinanceService();
        $usdt_try = $binance_service->tickerPrice('USDTTRY');
        $usdt_rub = $binance_service->tickerPrice('USDTRUB');

        $this->info("Current rates: USDT/TRY: $usdt_try, USDT/RUB: $usdt_rub");

        $ym = new YmMainController(); // Uses default tax OR we can fetch from Shop later

        $products = [];
        foreach ($items as $item) {
            [$price_rub, $base_price_rub] = $ym->pricesCalc($item, $usdt_try, $usdt_rub);

            // Extract description from PS data if possible
            $psData = json_decode($item->data, true);
            $description = '';
            if (!empty($psData['descriptions'])) {
                foreach ($psData['descriptions'] as $desc) {
                    if ($desc['type'] === 'LONG') {
                        $description = $desc['value'];
                        break;
                    }
                }
            }

            $products[] = [
                'sku' => $item->sku,
                'name' => $item->name,
                'description' => mb_substr(strip_tags($description), 0, 3000),
                'type' => 'playstation',
                'category' => 'game',
                'price_rub' => $price_rub,
                'base_price' => $base_price_rub,
                'purchase_price' => $item->price_with_discount,
                'purchase_currency' => 'TRY',
                'data' => $item->data,
                'is_active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ];
        }

        $this->info("Upserting " . count($products) . " products...");

        Product::upsert(
            $products,
            ['sku'],
            ['name', 'description', 'price_rub', 'base_price', 'purchase_price', 'purchase_currency', 'data', 'is_active', 'updated_at']
        );

        $this->info("Sync completed successfully!");
    }
}
