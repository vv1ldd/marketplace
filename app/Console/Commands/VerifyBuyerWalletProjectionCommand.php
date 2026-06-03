<?php

namespace App\Console\Commands;

use App\Models\ProjectionRebuildRegistry;
use App\Services\Continuity\ProjectionRebuildRegistryService;
use App\Services\Projections\BuyerWalletProjectionService;
use Illuminate\Console\Command;

class VerifyBuyerWalletProjectionCommand extends Command
{
    protected $signature = 'marketplace:verify-buyer-wallets
        {--user= : Verify one buyer wallet user projection}
        {--json : Output machine-readable JSON}';

    protected $description = 'Verify buyer wallet account balances against wallet ledger entries.';

    public function handle(BuyerWalletProjectionService $projection, ProjectionRebuildRegistryService $registry): int
    {
        $registry->ensureDefaults();

        $result = $projection->verify(
            userId: $this->option('user') ? (int) $this->option('user') : null,
        );

        $verificationResult = match ($result['status']) {
            'OK' => ProjectionRebuildRegistry::RESULT_HEALTHY,
            'SOURCE_GAP' => ProjectionRebuildRegistry::RESULT_SOURCE_GAP,
            default => ProjectionRebuildRegistry::RESULT_FAILED,
        };

        $registry->markVerified(
            projectionName: 'buyer_wallet_projection',
            verificationResult: $verificationResult,
            sourceRevision: $result['source_revision'],
            metadata: [
                'accounts_checked' => $result['accounts_checked'],
                'mismatches' => $result['mismatches'],
                'anomalies' => count($result['anomalies']),
            ],
        );

        $this->outputResult($result);

        return $result['status'] === 'OK' ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param array<string, mixed> $result
     */
    private function outputResult(array $result): void
    {
        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return;
        }

        $this->line('Buyer wallet projection verification: '.$result['status']);
        $this->line('accounts_checked: '.$result['accounts_checked']);
        $this->line('mismatches: '.$result['mismatches']);
        $this->line('anomalies: '.count($result['anomalies']));
    }
}
