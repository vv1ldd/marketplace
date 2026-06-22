<?php

namespace App\Services;

use App\Support\SettlementNetwork;
use App\Support\TonApiClient;

class TonWalletPreviewEnricher
{
    public function __construct(
        private readonly TonApiClient $ton,
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

        $apiReady = $network->rpcEnabled
            && is_string($network->rpcUrl)
            && $network->rpcUrl !== '';
        $nativeSymbol = strtoupper((string) ($network->nativeSymbol ?? 'TON'));

        foreach ($preview['coins'] ?? [] as $index => $coin) {
            if (! is_array($coin)) {
                continue;
            }

            if (strtoupper((string) ($coin['symbol'] ?? '')) !== $nativeSymbol) {
                continue;
            }

            $preview['coins'][$index]['precision'] = 9;
            $preview['coins'][$index]['transferable'] = false;

            if (! $apiReady) {
                $preview['coins'][$index]['status'] = 'balance_unavailable';
                $preview['coins'][$index]['note'] = $network->label.' balance refresh requires API access.';

                continue;
            }

            try {
                $amount = $this->ton->getBalanceTon((string) $network->rpcUrl, $walletAddress);
                if ($amount === null) {
                    $preview['coins'][$index]['status'] = 'balance_unavailable';
                    $preview['coins'][$index]['note'] = 'Could not refresh '.$nativeSymbol.' balance right now.';

                    continue;
                }
            } catch (\Throwable) {
                $preview['coins'][$index]['status'] = 'balance_unavailable';
                $preview['coins'][$index]['note'] = 'Could not refresh '.$nativeSymbol.' balance right now.';

                continue;
            }

            $preview['coins'][$index]['amount'] = $amount;
            $preview['coins'][$index]['display_amount'] = $this->displayAmount($amount, $nativeSymbol);
            $preview['coins'][$index]['status'] = 'live';
            unset($preview['coins'][$index]['note']);
        }

        $preview['wallet']['tier'] = 'bound';
        $preview['contract']['mode'] = 'bound_preview';
        $preview['capabilities'] = array_merge(
            is_array($preview['capabilities'] ?? null) ? $preview['capabilities'] : [],
            [
                'can_view_coins' => true,
                'next_action' => $apiReady ? 'VIEW_BOUND_WALLET' : 'NETWORK_RPC_REQUIRED',
            ],
        );

        return $preview;
    }

    private function displayAmount(string $amount, string $symbol): string
    {
        $trimmed = rtrim(rtrim($amount, '0'), '.');

        return ($trimmed === '' ? '0' : $trimmed).' '.strtoupper($symbol);
    }
}
