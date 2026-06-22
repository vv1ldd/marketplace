<?php

namespace App\Services\SettlementAdapters;

use App\Contracts\SettlementAdapter;
use App\Models\IdentityBinding;
use App\Models\VaultIdentity;
use App\Services\SettlementAdapters\Concerns\DeclinesSettlementProofVerification;
use App\Services\SettlementAuditEventRecorder;
use App\Services\SettlementNetworkRegistry;
use App\Services\TonWalletPreviewEnricher;
use App\Services\WalletBindingService;
use App\Support\SettlementAdapterConfig;
use App\Support\SettlementAdapterHealthCodes;
use App\Support\SettlementNetwork;
use App\Support\TonApiClient;
use Illuminate\Support\Facades\Http;

class TonSettlementAdapter implements SettlementAdapter
{
    use DeclinesSettlementProofVerification;

    private const ADAPTER_KEY = 'ton';

    public function __construct(
        private readonly SettlementNetworkRegistry $settlementNetworks,
        private readonly WalletBindingService $bindings,
        private readonly TonWalletPreviewEnricher $walletPreviewEnricher,
        private readonly SettlementAuditEventRecorder $auditEvents,
        private readonly TonApiClient $ton,
    ) {}

    public function adapterKey(): string
    {
        return self::ADAPTER_KEY;
    }

    public function mode(): string
    {
        return SettlementAdapterConfig::mode(self::ADAPTER_KEY);
    }

    public function isEnabled(): bool
    {
        return SettlementAdapterConfig::isEnabled(self::ADAPTER_KEY);
    }

    public function allowsWrite(): bool
    {
        return SettlementAdapterConfig::allowsWrite(self::ADAPTER_KEY);
    }

    public function verifyAttachment(VaultIdentity $vault, IdentityBinding $binding): array
    {
        if ($binding->binding_key !== self::ADAPTER_KEY || ! $binding->isVerified()) {
            return [
                'valid' => false,
                'reason' => 'binding_not_verified',
            ];
        }

        if ((string) $binding->vault_id !== (string) $vault->id) {
            return [
                'valid' => false,
                'reason' => 'binding_vault_mismatch',
            ];
        }

        return [
            'valid' => true,
            'binding' => $this->bindings->formatBinding($binding),
        ];
    }

    public function observeBalance(VaultIdentity $vault, IdentityBinding $binding): array
    {
        $verification = $this->verifyAttachment($vault, $binding);
        if ($verification['valid'] !== true) {
            return [
                'observed' => false,
                'reason' => $verification['reason'] ?? 'attachment_invalid',
                'observation_state' => SettlementAdapterHealthCodes::BALANCE_UNAVAILABLE,
            ];
        }

        $network = $this->settlementNetworks->network(self::ADAPTER_KEY);
        $preview = $this->settlementNetworks->adapter(self::ADAPTER_KEY)->walletPreview([
            'entity_l1_address' => $vault->anchor_address,
        ]);
        $preview = $this->walletPreviewEnricher->enrich(
            $preview,
            $network,
            (string) $binding->binding_value_normalized,
        );

        $coins = $this->extractCoins($preview);
        $observationState = $this->resolveObservationState($network, $coins);

        if ($observationState !== 'live') {
            return [
                'observed' => false,
                'reason' => $observationState === SettlementAdapterHealthCodes::RPC_ERROR
                    ? SettlementAdapterHealthCodes::RPC_ERROR
                    : SettlementAdapterHealthCodes::BALANCE_UNAVAILABLE,
                'observation_state' => $observationState,
                'adapter' => self::ADAPTER_KEY,
                'mode' => $this->mode(),
                'address' => $binding->binding_value_normalized,
                'coins' => $coins,
                'capabilities' => $preview['capabilities'] ?? [],
            ];
        }

        $this->auditEvents->recordBalanceRead(
            identityId: (string) $vault->anchor_address,
            vaultId: (string) $vault->id,
            source: self::ADAPTER_KEY,
        );

        return [
            'observed' => true,
            'observation_state' => 'live',
            'adapter' => self::ADAPTER_KEY,
            'mode' => $this->mode(),
            'address' => $binding->binding_value_normalized,
            'coins' => $coins,
            'capabilities' => $preview['capabilities'] ?? [],
        ];
    }

    public function listEvents(VaultIdentity $vault, int $limit = 50): array
    {
        return $this->auditEvents
            ->listForVault((string) $vault->id, $limit)
            ->map(fn ($event) => $this->auditEvents->formatEvent($event))
            ->values()
            ->all();
    }

    public function healthCheck(): array
    {
        $network = $this->settlementNetworks->network(self::ADAPTER_KEY);
        $failures = [];

        $checks = [
            'adapter_registered' => true,
            'adapter_enabled' => $this->isEnabled(),
            'mode' => $this->mode(),
            'crypto_rails_enabled' => $this->settlementNetworks->cryptoRailsEnabled(),
            'api_configured' => is_string($network->rpcUrl) && $network->rpcUrl !== '',
            'api_enabled' => $network->rpcEnabled,
            'api_reachable' => false,
        ];

        if (! $checks['adapter_enabled']) {
            $failures[] = SettlementAdapterHealthCodes::ADAPTER_DISABLED;
        }

        if ($checks['adapter_enabled'] && ! $checks['crypto_rails_enabled']) {
            $failures[] = SettlementAdapterHealthCodes::CRYPTO_RAILS_DISABLED;
            $failures[] = SettlementAdapterHealthCodes::BALANCE_UNAVAILABLE;
        }

        if ($checks['api_configured'] && $checks['api_enabled']) {
            try {
                $response = Http::timeout(12)
                    ->acceptJson()
                    ->get(rtrim((string) $network->rpcUrl, '/').'/status');
                $checks['api_reachable'] = $response->successful();
            } catch (\Throwable) {
                $checks['api_reachable'] = false;
            }

            if (! $checks['api_reachable']) {
                $failures[] = SettlementAdapterHealthCodes::RPC_ERROR;
            }
        } elseif ($checks['adapter_enabled'] && $checks['crypto_rails_enabled']) {
            $failures[] = SettlementAdapterHealthCodes::BALANCE_UNAVAILABLE;
        }

        if ($checks['adapter_enabled']
            && $checks['api_enabled']
            && $checks['api_reachable']) {
            $lastBalanceRead = $this->auditEvents->lastBalanceReadForAdapter(self::ADAPTER_KEY);
            $staleHours = SettlementAdapterConfig::staleObservationHours(self::ADAPTER_KEY);

            if ($lastBalanceRead !== null
                && $lastBalanceRead->occurred_at !== null
                && $lastBalanceRead->occurred_at->lt(now()->subHours($staleHours))) {
                $failures[] = SettlementAdapterHealthCodes::STALE_OBSERVATION;
            }
        }

        $failures = array_values(array_unique($failures));
        $healthy = $checks['adapter_registered']
            && ($checks['adapter_enabled'] === false
                || ($checks['crypto_rails_enabled']
                    && ($checks['api_enabled'] === false || $checks['api_reachable'])
                    && ! in_array(SettlementAdapterHealthCodes::STALE_OBSERVATION, $failures, true)));

        return [
            'status' => $healthy ? SettlementAdapterHealthCodes::PASS : SettlementAdapterHealthCodes::FAIL,
            'healthy' => $healthy,
            'adapter' => self::ADAPTER_KEY,
            'mode' => $this->mode(),
            'failures' => $failures,
            'checks' => $checks,
        ];
    }

    /**
     * @param array<string, mixed> $preview
     * @return list<array{symbol: string, amount: string, display_amount: string, status: string}>
     */
    private function extractCoins(array $preview): array
    {
        $coins = [];

        foreach ($preview['coins'] ?? [] as $coin) {
            if (! is_array($coin)) {
                continue;
            }

            $coins[] = [
                'symbol' => (string) ($coin['symbol'] ?? ''),
                'amount' => (string) ($coin['amount'] ?? '0'),
                'display_amount' => (string) ($coin['display_amount'] ?? ''),
                'status' => (string) ($coin['status'] ?? 'unknown'),
            ];
        }

        return $coins;
    }

    /**
     * @param list<array{symbol: string, amount: string, display_amount: string, status: string}> $coins
     */
    private function resolveObservationState(SettlementNetwork $network, array $coins): string
    {
        $apiReady = $network->rpcEnabled
            && is_string($network->rpcUrl)
            && $network->rpcUrl !== '';

        if (! $apiReady) {
            return SettlementAdapterHealthCodes::BALANCE_UNAVAILABLE;
        }

        $nativeSymbol = strtoupper((string) ($network->nativeSymbol ?? 'TON'));
        $settlementCoins = array_values(array_filter(
            $coins,
            static fn (array $coin): bool => strtoupper($coin['symbol']) === $nativeSymbol,
        ));

        if ($settlementCoins === []) {
            return SettlementAdapterHealthCodes::BALANCE_UNAVAILABLE;
        }

        $hasLive = false;
        $hasUnavailable = false;

        foreach ($settlementCoins as $coin) {
            if ($coin['status'] === 'live') {
                $hasLive = true;
            }

            if ($coin['status'] === 'balance_unavailable') {
                $hasUnavailable = true;
            }
        }

        if ($hasUnavailable && ! $hasLive) {
            return SettlementAdapterHealthCodes::RPC_ERROR;
        }

        if ($hasLive) {
            return 'live';
        }

        return SettlementAdapterHealthCodes::BALANCE_UNAVAILABLE;
    }
}
