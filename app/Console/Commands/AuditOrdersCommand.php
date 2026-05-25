<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class AuditOrdersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'audit:orders {--legal-entity=5 : The Legal Entity ID to audit}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Audit orders for missing totals or items';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $legalEntityId = $this->option('legal-entity');
        $shops = \App\Models\Shop::where('legal_entity_id', $legalEntityId)->get();

        $this->info("\n🦙 LAMA DATA AUDIT - CJS GROUP (LE {$legalEntityId}) 🦙\n========================================");

        foreach ($shops as $shop) {
            $totalOrders = \App\Models\Order\Order::where('shop_id', $shop->id)->count();
            $zeroTotals = \App\Models\Order\Order::where('shop_id', $shop->id)->where('total_amount_base', 0)->count();
            $noCurrency = \App\Models\Order\Order::where('shop_id', $shop->id)->whereNull('currency')->count();
            $noItems = \App\Models\Order\Order::where('shop_id', $shop->id)->doesntHave('items')->count();
            
            $this->line(sprintf(
                "Shop: [%2d] %-30s | Total: %5d | Zero Sum: %5d | No Curr: %5d | No Items: %5d",
                $shop->id,
                $shop->name,
                $totalOrders,
                $zeroTotals,
                $noCurrency,
                $noItems
            ));
        }

        $this->info("\n--- Gaps in JSON 'info' column ---");
        foreach ($shops as $shop) {
            // Check for order_total in various JSON paths
            $hasOrderTotalInInfo = \App\Models\Order\Order::where('shop_id', $shop->id)
                ->where('total_amount_base', 0)
                ->where(function($q) {
                    $q->whereNotNull('info->order->order_total')
                      ->orWhereNotNull('info->total')
                      ->orWhereNotNull('info->order_total');
                })
                ->count();
                
            if ($hasOrderTotalInInfo > 0) {
                $this->warn("⚠️ Shop {$shop->id} ({$shop->name}): Found $hasOrderTotalInInfo orders with 'order_total' in JSON that are still ZERO in DB!");
            }
        }

        $this->info("\nAudit Complete.");
    }
}
