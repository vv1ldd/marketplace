<?php

namespace App\Services;

use App\Support\EvmErc20BalanceReader;
use App\Support\SettlementNetwork;

class EvmWalletPreviewEnricher
{
    public function __construct(
        private readonly EvmErc20BalanceReader $balances,
    ) {}

    /**
     * @param array<string, mixed> $preview
     * @return array<string, mixed>
     */
    public function enrich(array $preview, SettlementNetwork $network, ?string $walletAddress): array
    {
        $walletAddress = is_string($walletAddress) ? trim($walletAddress) : '';
        if ($walletAddress === '') {
            return $preview;
        }

        $tokenContracts = $this->tokenContracts($network->key);
        $rpcReady = $network->rpcEnabled && is_string($network->rpcUrl) && $network->rpcUrl !== '';

        foreach ($preview['coins'] ?? [] as $index => $coin) {
            if (! is_array($coin)) {
                continue;
            }

            $symbol = strtoupper((string) ($coin['symbol'] ?? ''));
            $token = $tokenContracts[$symbol] ?? null;
            if (! is_array($token)) {
                continue;
            }

            $decimals = (int) ($token['decimals'] ?? 6);
            $preview['coins'][$index]['precision'] = $decimals;
            $preview['coins'][$index]['transferable'] = false;

            if (! $rpcReady) {
                $preview['coins'][$index]['status'] = 'balance_unavailable';
                $preview['coins'][$index]['note'] = $network->label.' balance refresh requires RPC.';

                continue;
            }

            try {
                $amountMinor = '0';
                $contracts = array_values(array_filter(array_merge(
                    [(string) ($token['contract'] ?? '')],
                    array_map('strval', $token['legacy_contracts'] ?? []),
                )));

                foreach ($contracts as $contract) {
                    if ($contract === '') {
                        continue;
                    }

                    $rawHex = $this->balances->balanceOf(
                        (string) $network->rpcUrl,
                        $contract,
                        $walletAddress,
                    );

                    if (! is_string($rawHex) || ! str_starts_with($rawHex, '0x')) {
                        continue;
                    }

                    $amountMinor = bcadd($amountMinor, $this->balances->formatAmount($rawHex, $decimals), $decimals);
                }
            } catch (\Throwable) {
                $preview['coins'][$index]['status'] = 'balance_unavailable';
                $preview['coins'][$index]['note'] = 'Could not refresh '.$symbol.' balance right now.';

                continue;
            }

            $amount = $amountMinor;
            $preview['coins'][$index]['amount'] = $amount;
            $preview['coins'][$index]['display_amount'] = $this->balances->displayAmount($amount, $symbol, $decimals);
            $preview['coins'][$index]['status'] = 'live';
            unset($preview['coins'][$index]['note']);
        }

        $preview['wallet']['tier'] = 'bound';
        $preview['contract']['mode'] = 'bound_preview';
        $preview['capabilities'] = array_merge(
            is_array($preview['capabilities'] ?? null) ? $preview['capabilities'] : [],
            [
                'can_view_coins' => true,
                'next_action' => $rpcReady ? 'VIEW_BOUND_WALLET' : 'NETWORK_RPC_REQUIRED',
            ],
        );

        return $preview;
    }

    /**
     * @return array<string, array{contract: string, decimals: int}>
     */
    private function tokenContracts(string $networkKey): array
    {
        $proofs = config('verification_proofs.usdc_transfer.'.$networkKey);
        if (! is_array($proofs)) {
            return [];
        }

        $asset = strtoupper((string) ($proofs['asset'] ?? 'USDC'));

        return [
            $asset => [
                'contract' => (string) ($proofs['token_contract'] ?? ''),
                'legacy_contracts' => array_values(array_filter(array_map(
                    'strval',
                    (array) ($proofs['legacy_token_contracts'] ?? []),
                ))),
                'decimals' => (int) ($proofs['decimals'] ?? 6),
            ],
        ];
    }
}
