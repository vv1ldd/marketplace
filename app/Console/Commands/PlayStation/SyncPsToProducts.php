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
        $provider = \App\Models\Provider::where('type', 'playstation')->first();
        $tax = $provider->settings['tax'] ?? 35;
        $manual_rate = $provider->settings['currency_rate'] ?? null;

        $usdt_try = $binance_service->tickerPrice('USDTTRY');
        $usdt_rub = $binance_service->tickerPrice('USDTRUB');

        // If manual rate is provided, we should probably adjust usdt_rub or similar
        // But for PS, usually it's TRY -> RUB rate.
        // My current pricesCalc uses (try_price / usdt_try) * usdt_rub
        // Which is basically try_price * (usdt_rub / usdt_try)
        
        $effective_rate = $usdt_rub / $usdt_try;
        if ($manual_rate) {
            $effective_rate = $manual_rate;
            $this->info("Using MANUAL currency rate: $manual_rate");
        } else {
            $this->info("Current rates: USDT/TRY: $usdt_try, USDT/RUB: $usdt_rub (Effective: " . round($effective_rate, 2) . ")");
        }

        $products = [];
        foreach ($items as $item) {
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
                'base_price' => $item->base_price, // Original price in TRY
                'purchase_price' => $item->price_with_discount, // Discounted price in TRY
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
            ['name', 'description', 'base_price', 'purchase_price', 'purchase_currency', 'data', 'is_active', 'updated_at']
        );

        $this->info("Sync completed successfully!");
    }
}
