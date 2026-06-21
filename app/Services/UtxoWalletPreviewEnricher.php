<?php

namespace App\Services;

use App\Support\BitcoinRpcClient;
use App\Support\SettlementNetwork;

class UtxoWalletPreviewEnricher
{
    public function __construct(
        private readonly BitcoinRpcClient $bitcoin,
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

        foreach ($preview['coins'] ?? [] as $index => $coin) {
            if (! is_array($coin)) {
                continue;
            }

            $symbol = strtoupper((string) ($coin['symbol'] ?? ''));
            if ($symbol !== strtoupper((string) ($network->nativeSymbol ?? 'BTC'))) {
                continue;
            }

            $decimals = 8;
            $preview['coins'][$index]['precision'] = $decimals;
            $preview['coins'][$index]['transferable'] = false;

            if (! $rpcReady) {
                $preview['coins'][$index]['status'] = 'balance_unavailable';
                $preview['coins'][$index]['note'] = $network->label.' balance refresh requires RPC.';

                continue;
            }

            try {
                $amountBtc = $this->bitcoin->getAddressBalanceBtc((string) $network->rpcUrl, $walletAddress);
                if ($amountBtc === null) {
                    $preview['coins'][$index]['status'] = 'balance_unavailable';
                    $preview['coins'][$index]['note'] = 'Could not refresh '.$symbol.' balance right now.';

                    continue;
                }
            } catch (\Throwable) {
                $preview['coins'][$index]['status'] = 'balance_unavailable';
                $preview['coins'][$index]['note'] = 'Could not refresh '.$symbol.' balance right now.';

                continue;
            }

            $preview['coins'][$index]['amount'] = $amountBtc;
            $preview['coins'][$index]['display_amount'] = $this->displayAmount($amountBtc, $symbol);
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

    private function displayAmount(string $amountBtc, string $symbol): string
    {
        $trimmed = rtrim(rtrim($amountBtc, '0'), '.');

        return ($trimmed === '' ? '0' : $trimmed).' '.strtoupper($symbol);
    }
}
