<?php

namespace App\Console\Commands\PlayStation;

use App\Models\PlayStation\PlayStationAlt;
use App\Models\PlayStation\PlayStationRegion;
use App\Models\Product;
use Illuminate\Console\Command;

class SyncPsUsBundles extends Command
{
    protected $signature = 'ps:sync-us-bundles';

    protected $description = 'Create product bundles for PlayStation US using available USD Gift Cards';

    public function handle()
    {
        $region = PlayStationRegion::where('slug', 'US')->first();
        if (!$region) {
            $this->error("Region US not found in play_station_regions table.");
            return;
        }

        $this->info("Fetching US PlayStation products...");

        $items = PlayStationAlt::where('region_id', $region->id)
            ->where('price_with_discount', '>', 0)
            ->get();

        if ($items->isEmpty()) {
            $this->warn("No items found for US region.");
            return;
        }

        $this->info("Fetching available USD Gift Cards...");
        $service = app(\App\Services\PsBundleService::class);
        
        $count = 0;
        foreach ($items as $item) {
            $service->syncGameBundle($item->sku, $region->id);
            $count++;
            if ($count % 50 === 0) {
                $this->info("Processed $count items...");
            }
        }

        $this->info("Successfully synced $count US PlayStation bundles!");
    }
}
