<?php

namespace App\Console\Commands;

use App\Models\ProjectionRebuildRegistry;
use App\Services\Continuity\ProjectionRebuildRegistryService;
use App\Services\Projections\MarketplaceOrdersProjectionService;
use Illuminate\Console\Command;

class VerifyMarketplaceOrdersProjectionCommand extends Command
{
    protected $signature = 'marketplace:verify-orders
        {--order= : Verify one order primary key projection}
        {--json : Output machine-readable JSON}';

    protected $description = 'Verify denormalized marketplace order financial/progress projection fields.';

    public function handle(MarketplaceOrdersProjectionService $projection, ProjectionRebuildRegistryService $registry): int
    {
        $registry->ensureDefaults();

        $result = $projection->verify(
            orderId: $this->option('order') ? (int) $this->option('order') : null,
        );

        $registry->markVerified(
            projectionName: 'marketplace_orders_projection',
            verificationResult: $result['status'] === 'OK'
                ? ProjectionRebuildRegistry::RESULT_HEALTHY
                : ProjectionRebuildRegistry::RESULT_FAILED,
            sourceRevision: $result['source_revision'],
            metadata: [
                'orders_checked' => $result['orders_checked'],
                'mismatches' => $result['mismatches'],
            ],
        );

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            $this->line('Marketplace orders projection verification: '.$result['status']);
            $this->line('orders_checked: '.$result['orders_checked']);
            $this->line('mismatches: '.$result['mismatches']);
        }

        return $result['status'] === 'OK' ? self::SUCCESS : self::FAILURE;
    }
}
