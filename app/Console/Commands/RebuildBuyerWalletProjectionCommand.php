<?php

namespace App\Console\Commands;

use App\Services\Continuity\ProjectionRebuildRegistryService;
use App\Services\Projections\BuyerWalletProjectionService;
use Illuminate\Console\Command;

class RebuildBuyerWalletProjectionCommand extends Command
{
    protected $signature = 'marketplace:rebuild-buyer-wallets
        {--user= : Rebuild one buyer wallet user projection}
        {--dry-run : Calculate without writing wallet_accounts}
        {--json : Output machine-readable JSON}';

    protected $description = 'Rebuild buyer wallet account balances from wallet ledger entries.';

    public function handle(BuyerWalletProjectionService $projection, ProjectionRebuildRegistryService $registry): int
    {
        $registry->ensureDefaults();

        $result = $projection->rebuild(
            userId: $this->option('user') ? (int) $this->option('user') : null,
            dryRun: (bool) $this->option('dry-run'),
        );

        if (! $this->option('dry-run') && $result['status'] === 'OK') {
            $registry->markRebuilt(
                projectionName: 'buyer_wallet_projection',
                sourceRevision: $result['source_revision'],
                metadata: [
                    'accounts_processed' => $result['accounts_processed'],
                    'accounts_updated' => $result['accounts_updated'],
                ],
            );
        }

        $this->output($result);

        return $result['status'] === 'OK' ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param array<string, mixed> $result
     */
    private function output(array $result): void
    {
        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return;
        }

        $this->line('Buyer wallet projection rebuild: '.$result['status']);
        $this->line('accounts_processed: '.$result['accounts_processed']);
        $this->line('accounts_updated: '.$result['accounts_updated']);
        $this->line('anomalies: '.count($result['anomalies']));
    }
}
