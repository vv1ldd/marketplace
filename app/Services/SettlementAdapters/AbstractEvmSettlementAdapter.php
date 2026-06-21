<?php

namespace App\Services\SettlementAdapters;

use App\Contracts\SettlementAdapter;
use App\Models\IdentityBinding;
use App\Models\VaultIdentity;
use App\Services\EvmWalletPreviewEnricher;
use App\Services\SettlementAdapters\Concerns\VerifiesEvmUsdcTransferProof;
use App\Services\SettlementAuditEventRecorder;
use App\Services\SettlementNetworkRegistry;
use App\Services\VerificationEventRecorder;
use App\Services\WalletBindingService;
use App\Support\EvmErc20TransferProofVerifier;
use App\Support\EvmRpcClient;
use App\Support\SettlementAdapterConfig;
use App\Support\SettlementAdapterHealthCodes;
use App\Support\SettlementNetwork;

abstract class AbstractEvmSettlementAdapter implements SettlementAdapter
{
    use VerifiesEvmUsdcTransferProof;

    public function __construct(
        private readonly SettlementNetworkRegistry $settlementNetworks,
        private readonly WalletBindingService $bindings,
        private readonly EvmWalletPreviewEnricher $walletPreviewEnricher,
        private readonly SettlementAuditEventRecorder $auditEvents,
        private readonly EvmRpcClient $rpc,
        private readonly EvmErc20TransferProofVerifier $erc20TransferProofVerifier,
        private readonly VerificationEventRecorder $verificationEvents,
    ) {}

    abstract protected function networkKey(): string;

    protected function erc20TransferProofVerifier(): EvmErc20TransferProofVerifier
    {
        return $this->erc20TransferProofVerifier;
    }

    protected function walletBindings(): WalletBindingService
    {
        return $this->bindings;
    }

    protected function verificationEvents(): VerificationEventRecorder
    {
        return $this->verificationEvents;
    }

    protected function settlementAuditEvents(): SettlementAuditEventRecorder
    {
        return $this->auditEvents;
    }

    public function adapterKey(): string
    {
        return $this->networkKey();
    }

    public function mode(): string
    {
        return SettlementAdapterConfig::mode($this->networkKey());
    }

    public function isEnabled(): bool
    {
        return SettlementAdapterConfig::isEnabled($this->networkKey());
    }

    public function allowsWrite(): bool
    {
        return SettlementAdapterConfig::allowsWrite($this->networkKey());
    }

    public function verifyAttachment(VaultIdentity $vault, IdentityBinding $binding): array
    {
        $networkKey = $this->networkKey();

        if ($binding->binding_key !== $networkKey || ! $binding->isVerified()) {
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
        $networkKey = $this->networkKey();
        $verification = $this->verifyAttachment($vault, $binding);
        if ($verification['valid'] !== true) {
            return [
                'observed' => false,
                'reason' => $verification['reason'] ?? 'attachment_invalid',
                'observation_state' => SettlementAdapterHealthCodes::BALANCE_UNAVAILABLE,
            ];
        }

        $network = $this->settlementNetworks->network($networkKey);
        $preview = $this->settlementNetworks->adapter($networkKey)->walletPreview([
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
                'adapter' => $networkKey,
                'mode' => $this->mode(),
                'address' => $binding->binding_value_normalized,
                'coins' => $coins,
                'capabilities' => $preview['capabilities'] ?? [],
            ];
        }

        $this->auditEvents->recordBalanceRead(
            identityId: (string) $vault->anchor_address,
            vaultId: (string) $vault->id,
            source: $networkKey,
        );

        return [
            'observed' => true,
            'observation_state' => 'live',
            'adapter' => $networkKey,
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
        $networkKey = $this->networkKey();
        $network = $this->settlementNetworks->network($networkKey);
        $failures = [];

        $checks = [
            'adapter_registered' => true,
            'adapter_enabled' => $this->isEnabled(),
            'mode' => $this->mode(),
            'crypto_rails_enabled' => $this->settlementNetworks->cryptoRailsEnabled(),
            'rpc_configured' => is_string($network->rpcUrl) && $network->rpcUrl !== '',
            'rpc_enabled' => $network->rpcEnabled,
            'rpc_reachable' => false,
            'chain_id_matches' => false,
        ];

        if (! $checks['adapter_enabled']) {
            $failures[] = SettlementAdapterHealthCodes::ADAPTER_DISABLED;
        }

        if ($checks['adapter_enabled'] && ! $checks['crypto_rails_enabled']) {
            $failures[] = SettlementAdapterHealthCodes::CRYPTO_RAILS_DISABLED;
            $failures[] = SettlementAdapterHealthCodes::BALANCE_UNAVAILABLE;
        }

        if ($checks['rpc_configured'] && $checks['rpc_enabled']) {
            try {
                $chainId = $this->rpc->getChainId((string) $network->rpcUrl);
            } catch (\Throwable) {
                $chainId = null;
            }

            $checks['rpc_chain_id'] = $chainId;
            $checks['expected_chain_id'] = $network->chainId;
            $checks['rpc_reachable'] = $chainId !== null;
            $checks['chain_id_matches'] = $chainId === $network->chainId;

            if (! $checks['rpc_reachable']) {
                $failures[] = SettlementAdapterHealthCodes::RPC_ERROR;
            } elseif (! $checks['chain_id_matches']) {
                $failures[] = SettlementAdapterHealthCodes::CHAIN_ID_MISMATCH;
                $failures[] = SettlementAdapterHealthCodes::RPC_ERROR;
            }
        } elseif ($checks['adapter_enabled'] && $checks['crypto_rails_enabled']) {
            $failures[] = SettlementAdapterHealthCodes::BALANCE_UNAVAILABLE;
        }

        if ($checks['adapter_enabled']
            && $checks['rpc_enabled']
            && $checks['rpc_reachable']
            && $checks['chain_id_matches']) {
            $lastBalanceRead = $this->auditEvents->lastBalanceReadForAdapter($networkKey);
            $staleHours = SettlementAdapterConfig::staleObservationHours($networkKey);

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
                    && ($checks['rpc_enabled'] === false || ($checks['rpc_reachable'] && $checks['chain_id_matches']))
                    && ! in_array(SettlementAdapterHealthCodes::STALE_OBSERVATION, $failures, true)));

        return [
            'status' => $healthy ? SettlementAdapterHealthCodes::PASS : SettlementAdapterHealthCodes::FAIL,
            'healthy' => $healthy,
            'adapter' => $networkKey,
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
        $rpcReady = $network->rpcEnabled
            && is_string($network->rpcUrl)
            && $network->rpcUrl !== '';

        if (! $rpcReady) {
            return SettlementAdapterHealthCodes::BALANCE_UNAVAILABLE;
        }

        $settlementCoins = array_values(array_filter(
            $coins,
            static fn (array $coin): bool => in_array(strtoupper($coin['symbol']), ['USDC', 'USDT'], true),
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
