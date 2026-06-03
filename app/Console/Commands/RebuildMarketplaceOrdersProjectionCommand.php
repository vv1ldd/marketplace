<?php

namespace App\Console\Commands;

use App\Services\Continuity\ProjectionRebuildRegistryService;
use App\Services\Projections\MarketplaceOrdersProjectionService;
use Illuminate\Console\Command;

class RebuildMarketplaceOrdersProjectionCommand extends Command
{
    protected $signature = 'marketplace:rebuild-orders
        {--order= : Rebuild one order primary key projection}
        {--dry-run : Calculate without writing orders}
        {--json : Output machine-readable JSON}';

    protected $description = 'Rebuild denormalized marketplace order financial/progress projection fields.';

    public function handle(MarketplaceOrdersProjectionService $projection, ProjectionRebuildRegistryService $registry): int
    {
        $registry->ensureDefaults();

        $result = $projection->rebuild(
            orderId: $this->option('order') ? (int) $this->option('order') : null,
            dryRun: (bool) $this->option('dry-run'),
        );

        if (! $this->option('dry-run')) {
            $registry->markRebuilt(
                projectionName: 'marketplace_orders_projection',
                sourceRevision: $result['source_revision'],
                metadata: [
                    'orders_processed' => $result['orders_processed'],
                    'orders_updated' => $result['orders_updated'],
                ],
            );
        }

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            $this->line('Marketplace orders projection rebuild: '.$result['status']);
            $this->line('orders_processed: '.$result['orders_processed']);
            $this->line('orders_updated: '.$result['orders_updated']);
        }

        return self::SUCCESS;
    }
}
