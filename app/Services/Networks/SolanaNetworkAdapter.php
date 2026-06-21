<?php

namespace App\Services\Networks;

use App\Contracts\SettlementNetworkAdapter;
use App\Models\User;
use App\Services\SettlementNetworkResolver;
use App\Support\SettlementAdapterConfig;
use App\Support\SettlementNetwork;

class SolanaNetworkAdapter implements SettlementNetworkAdapter
{
    public function __construct(
        private readonly SettlementNetworkResolver $networks,
        private readonly string $networkKey,
    ) {}

    public function network(): SettlementNetwork
    {
        return $this->networks->resolve($this->networkKey);
    }

    public function walletPreview(array $identity, ?User $user = null): array
    {
        $network = $this->network();
        $adapterEnabled = SettlementAdapterConfig::isEnabled($network->key);
        $adapterMode = SettlementAdapterConfig::mode($network->key);
        $nativeSymbol = strtoupper((string) ($network->nativeSymbol ?? 'SOL'));

        $coins = [[
            'key' => strtolower($network->key.'-native-preview'),
            'symbol' => $nativeSymbol,
            'name' => $network->label.' native',
            'amount' => '0',
            'display_amount' => '0 '.$nativeSymbol,
            'precision' => 9,
            'transferable' => false,
            'status' => $adapterEnabled ? 'balance_unavailable' : 'coming_soon',
            'note' => $adapterEnabled
                ? $network->label.' balance is observed from chain when RPC is available.'
                : $network->label.' balances will appear here once the settlement adapter is enabled.',
        ]];

        foreach ($network->assets as $asset) {
            if (strtoupper($asset) === $nativeSymbol) {
                continue;
            }

            $coins[] = [
                'key' => strtolower($network->key.'-'.strtolower($asset).'-preview'),
                'symbol' => strtoupper($asset),
                'name' => $asset,
                'amount' => '0',
                'display_amount' => '0 '.strtoupper($asset),
                'precision' => 6,
                'transferable' => false,
                'status' => $adapterEnabled ? 'balance_unavailable' : 'coming_soon',
                'note' => $adapterEnabled
                    ? $network->label.' SPL balances are observed from chain when RPC is available.'
                    : $network->label.' SPL balances will appear here once the settlement adapter is enabled.',
            ];
        }

        return [
            'network' => $network->toStorefrontCatalogEntry(),
            'contract' => [
                'name' => 'storefront-vault-wallet-coins',
                'version' => 'v1',
                'authority' => 'marketplace-commerce',
                'network' => $network->contractKey,
                'mode' => $adapterEnabled ? $adapterMode : 'coming_soon',
                'dto_boundary' => $adapterEnabled ? 'settlement_adapter' : 'solana_adapter_pending',
            ],
            'wallet' => [
                'tier' => $adapterEnabled ? 'available' : 'coming-soon',
                'label' => $network->label.' Wallet',
                'network_key' => $network->key,
                'network_label' => $network->label,
                'custody_note' => $adapterEnabled
                    ? $network->label.' balances are observed from chain; vault stores attachment only.'
                    : $network->label.' support is being wired into the shared settlement layer.',
            ],
            'coins' => $coins,
            'capabilities' => [
                'can_view_coins' => $adapterEnabled,
                'can_redeem_credits' => false,
                'can_transfer_coins' => SettlementAdapterConfig::allowsWrite($network->key),
                'can_convert_points' => false,
                'next_action' => $adapterEnabled ? 'CONNECT_OR_VIEW_WALLET' : 'NETWORK_COMING_SOON',
            ],
        ];
    }

    public function traceNetworkLabel(): string
    {
        return $this->network()->traceLabel;
    }
}
