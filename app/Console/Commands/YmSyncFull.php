<?php

namespace App\Console\Commands;

use App\Models\Shop;
use App\Jobs\ImportProductsFromYM;
use Illuminate\Console\Command;

class YmSyncFull extends Command
{
    protected $signature = 'ym:sync-full';
    protected $description = 'Trigger full catalog import/refresh for all active shops to ensure parity';

    public function handle()
    {
        $this->info('Starting Full Yandex Market Synchronization for all shops...');

        $shops = Shop::where('is_active', true)->whereNotNull('api_key')->get();

        if ($shops->isEmpty()) {
            $this->info('No active shops found to sync.');
            return 0;
        }

        foreach ($shops as $shop) {
            $this->info("Dispatching full import job for shop: {$shop->name} (ID: {$shop->id})");
            
            // We use a null importToken here since it's a background automated sync
            ImportProductsFromYM::dispatch($shop);
        }

        $this->info('All sync jobs have been dispatched to the queue.');
        return 0;
    }
}
