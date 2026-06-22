<?php

namespace App\Services;

use App\Support\SettlementNetwork;
use App\Support\SolanaRpcClient;

class SolanaWalletPreviewEnricher
{
    public function __construct(
        private readonly SolanaRpcClient $solana,
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

        $rpcReady = $network->rpcEnabled && is_string($network->rpcUrl) && $network->rpcUrl !== '';
        $nativeSymbol = strtoupper((string) ($network->nativeSymbol ?? 'SOL'));
        $tokenMints = $this->tokenMints($network->key);

        foreach ($preview['coins'] ?? [] as $index => $coin) {
            if (! is_array($coin)) {
                continue;
            }

            $symbol = strtoupper((string) ($coin['symbol'] ?? ''));
            $isNative = $symbol === $nativeSymbol;
            $token = $tokenMints[$symbol] ?? null;

            if (! $isNative && ! is_array($token)) {
                continue;
            }

            $decimals = $isNative ? 9 : (int) ($token['decimals'] ?? 6);
            $preview['coins'][$index]['precision'] = $decimals;
            $preview['coins'][$index]['transferable'] = false;

            if (! $rpcReady) {
                $preview['coins'][$index]['status'] = 'balance_unavailable';
                $preview['coins'][$index]['note'] = $network->label.' balance refresh requires RPC.';

                continue;
            }

            try {
                if ($isNative) {
                    $amount = $this->solana->getBalanceSol((string) $network->rpcUrl, $walletAddress);
                } else {
                    $amount = $this->solana->getSplTokenBalance(
                        (string) $network->rpcUrl,
                        $walletAddress,
                        (string) ($token['token_mint'] ?? ''),
                        $decimals,
                    );
                }

                if ($amount === null) {
                    $preview['coins'][$index]['status'] = 'balance_unavailable';
                    $preview['coins'][$index]['note'] = 'Could not refresh '.$symbol.' balance right now.';

                    continue;
                }
            } catch (\Throwable) {
                $preview['coins'][$index]['status'] = 'balance_unavailable';
                $preview['coins'][$index]['note'] = 'Could not refresh '.$symbol.' balance right now.';

                continue;
            }

            $preview['coins'][$index]['amount'] = $amount;
            $preview['coins'][$index]['display_amount'] = $this->displayAmount($amount, $symbol);
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
     * @return array<string, array{token_mint: string, decimals: int}>
     */
    private function tokenMints(string $networkKey): array
    {
        $proofs = config('verification_proofs.usdc_transfer.'.$networkKey);
        if (! is_array($proofs)) {
            return [];
        }

        $asset = strtoupper((string) ($proofs['asset'] ?? 'USDC'));

        return [
            $asset => [
                'token_mint' => (string) ($proofs['token_mint'] ?? ''),
                'decimals' => (int) ($proofs['decimals'] ?? 6),
            ],
        ];
    }

    private function displayAmount(string $amount, string $symbol): string
    {
        $trimmed = rtrim(rtrim($amount, '0'), '.');

        return ($trimmed === '' ? '0' : $trimmed).' '.strtoupper($symbol);
    }
}
