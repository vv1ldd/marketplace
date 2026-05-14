<?php

namespace App\Console\Commands;

use App\Models\ProviderProduct;
use App\Models\WildflowCatalog;
use App\Services\WildflowService;
use App\Services\VaultTransitService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class HealOutOfStockItems extends Command
{
    protected $signature = 'app:heal-oos-items {--limit=30}';
    protected $description = 'Periodically checks inactive items for inventory replenishment and restores them.';

    public function handle()
    {
        $limit = (int)$this->option('limit');
        $this->info("🏥 Starting Out-of-Stock Healing Session. Limit: {$limit} items.");

        // Fetch inactive products that should potentially have stock
        // We take the least recently updated ones first to give all a fair rotation
        $inactiveProducts = ProviderProduct::where('is_active', false)
            ->orderBy('updated_at', 'asc')
            ->limit($limit)
            ->get();

        if ($inactiveProducts->isEmpty()) {
            $this->info("✨ No inactive items found to inspect. Everything is clean!");
            return Command::SUCCESS;
        }

        $wfService = new WildflowService();
        $vault = app(VaultTransitService::class);
        $healedCount = 0;

        $this->info("Found " . $inactiveProducts->count() . " candidates. Commencing verification...");

        foreach ($inactiveProducts as $pp) {
            $this->output->write("🔍 Checking [{$pp->name}] ... ");
            
            try {
                $serviceSku = $vault->decrypt($pp->sku);
                
                // Quick Check
                $availability = $wfService->checkAvailability(
                    service_sku: (string)$serviceSku
                );

                if ($availability['available']) {
                    // 🎉 IT HAS RETURNED!
                    $this->info("🟢 IN STOCK! Restoring...");
                    
                    // 1. Enable Proxy Product
                    $pp->update([
                        'is_active' => true,
                        'updated_at' => now()
                    ]);

                    // 2. Find and Enable underlying catalog item
                    $searchSku = !empty($pp->market_sku) ? $pp->market_sku : $pp->sku;
                    WildflowCatalog::where('sku', $searchSku)->update(['is_active' => true]);
                    
                    $healedCount++;
                    
                    Log::info("HealOOS: Automatically restored product back to showcase due to detected stock.", [
                        'product_id' => $pp->id,
                        'name' => $pp->name
                    ]);
                } else {
                    // Still dead. Update updated_at to rotate it to the end of queue next time!
                    $this->line("🔴 Still out of stock.");
                    $pp->touch(); 
                }
            } catch (\Exception $e) {
                $this->error("❌ Error: " . $e->getMessage());
                $pp->touch(); // skip for now
            }
            
            // Tiny sleep to be nice to the API rate limits
            usleep(200000); 
        }

        $this->info("🏁 Session completed. Restored {$healedCount} items back to life.");
        return Command::SUCCESS;
    }
}
