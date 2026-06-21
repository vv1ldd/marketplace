<?php

namespace App\Console\Commands;

use App\Services\SettlementAdapterRegistry;
use App\Services\SettlementNetworkRegistry;
use App\Support\BitcoinMessageSignVerifier;
use Illuminate\Console\Command;

class MeanlyBitcoinBindingReadinessCommand extends Command
{
    protected $signature = 'meanly:bitcoin-binding-readiness {--json : Output machine-readable JSON}';

    protected $description = 'Verify Vault Bitcoin connect prerequisites (crypto rails, adapter, BIP-322 verifier runtime)';

    /** @var array<int, array{name:string,status:string,detail:string}> */
    private array $checks = [];

    public function handle(
        SettlementNetworkRegistry $networks,
        SettlementAdapterRegistry $adapters,
        BitcoinMessageSignVerifier $signVerifier,
    ): int {
        $this->record(
            'Commerce crypto rails',
            $networks->cryptoRailsEnabled() ? 'pass' : 'fail',
            $networks->cryptoRailsEnabled()
                ? 'COMMERCE_CRYPTO_RAILS_ENABLED=true'
                : 'Set COMMERCE_CRYPTO_RAILS_ENABLED=true to expose Bitcoin in Vault.',
        );

        $this->record(
            'Bitcoin settlement adapter',
            config('settlement_adapters.bitcoin.enabled') ? 'pass' : 'warn',
            config('settlement_adapters.bitcoin.enabled')
                ? 'SETTLEMENT_ADAPTER_BITCOIN_ENABLED=true ('.config('settlement_adapters.bitcoin.mode', 'read_only').')'
                : 'Enable SETTLEMENT_ADAPTER_BITCOIN_ENABLED=true for BTC balance observation after connect.',
        );

        $this->record(
            'BIP-322 verifier runtime',
            $signVerifier->bip322RuntimeAvailable() ? 'pass' : 'fail',
            $signVerifier->bip322RuntimeAvailable()
                ? 'node + scripts/verify-bitcoin-message.cjs + bip322-js are available (MetaMask Bitcoin connect).'
                : 'Run: cd scripts && npm ci --omit=dev. MetaMask Bitcoin ownership verification requires this runtime.',
        );

        try {
            $health = $adapters->healthCheck('bitcoin');
            $healthy = ($health['healthy'] ?? false) === true;
            $this->record(
                'Bitcoin adapter health',
                $healthy ? 'pass' : 'warn',
                $healthy
                    ? 'Adapter health check passed.'
                    : 'Failures: '.implode(', ', (array) ($health['failures'] ?? ['unknown'])),
            );
        } catch (\Throwable $exception) {
            $this->record('Bitcoin adapter health', 'warn', $exception->getMessage());
        }

        $this->render();

        return collect($this->checks)->contains(fn (array $check): bool => $check['status'] === 'fail')
            ? self::FAILURE
            : self::SUCCESS;
    }

    private function record(string $name, string $status, string $detail): void
    {
        $this->checks[] = [
            'name' => $name,
            'status' => $status,
            'detail' => $detail,
        ];
    }

    private function render(): void
    {
        if ($this->option('json')) {
            $this->line(json_encode([
                'status' => collect($this->checks)->contains(fn (array $check): bool => $check['status'] === 'fail')
                    ? 'fail'
                    : 'pass',
                'checks' => $this->checks,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return;
        }

        $this->info('Bitcoin Vault binding readiness');
        $this->table(['Check', 'Status', 'Detail'], $this->checks);

        if (collect($this->checks)->contains(fn (array $check): bool => $check['status'] === 'fail')) {
            $this->error('Bitcoin connect is NOT ready. Fix failed checks, then retry Connect in Vault.');
        } else {
            $this->info('Bitcoin connect prerequisites look ready.');
        }
    }
}
