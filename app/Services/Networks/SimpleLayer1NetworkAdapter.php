<?php

namespace App\Services\Networks;

use App\Contracts\SettlementNetworkAdapter;
use App\Models\User;
use App\Models\WalletAccount;
use App\Services\SettlementNetworkResolver;
use App\Support\SettlementNetwork;

class SimpleLayer1NetworkAdapter implements SettlementNetworkAdapter
{
    public function __construct(
        private readonly SettlementNetworkResolver $networks,
    ) {}

    public function network(): SettlementNetwork
    {
        return $this->networks->resolve('simple-layer-1');
    }

    public function walletPreview(array $identity, ?User $user = null): array
    {
        $network = $this->network();
        $mlpBalance = '0';

        if ($user instanceof User) {
            $mlpAccount = WalletAccount::query()
                ->where('user_id', $user->id)
                ->where('asset', 'MLP')
                ->first();

            if ($mlpAccount) {
                $mlpBalance = (string) $mlpAccount->available_minor;
            }
        }

        return [
            'network' => $network->toStorefrontCatalogEntry(),
            'contract' => [
                'name' => 'storefront-vault-wallet-coins',
                'version' => 'v1',
                'authority' => 'marketplace-commerce',
                'network' => $network->contractKey,
                'mode' => 'preview',
                'dto_boundary' => 'coins_are_preview_not_transfer_authority',
            ],
            'wallet' => [
                'tier' => 'premium-preview',
                'label' => 'Vault Wallet',
                'network_key' => $network->key,
                'network_label' => $network->label,
                'custody_note' => 'Vault Wallet shows SL1, MCR, and MLP bound to your identity.',
            ],
            'coins' => [
                [
                    'key' => 'simple-layer-one-preview',
                    'symbol' => 'SL1',
                    'name' => $network->label,
                    'amount' => '0',
                    'display_amount' => '0 SL1',
                    'precision' => 9,
                    'transferable' => false,
                    'status' => 'preview_only',
                    'note' => 'Native coin balance for '.$network->label.'.',
                ],
                [
                    'key' => 'meanly-credits-preview',
                    'symbol' => 'MCR',
                    'name' => 'Meanly Credits',
                    'amount' => '0',
                    'display_amount' => '0 MCR',
                    'precision' => 2,
                    'transferable' => false,
                    'status' => 'preview_only',
                    'note' => 'Credits emitted dynamically when a payment intent is executed (for resolved cashback, adjustments, and marketplace grants). No pre-emission exists.',
                ],
                [
                    'key' => 'meanly-loyalty-points-preview',
                    'symbol' => 'MLP',
                    'name' => 'Meanly Loyalty Points',
                    'amount' => $mlpBalance,
                    'display_amount' => $mlpBalance.' MLP',
                    'precision' => 0,
                    'transferable' => false,
                    'status' => 'preview_only',
                    'note' => 'Loyalty points converted to Meanly Credits (MCR) when intent payment is required. This is a loyalty unit, not a cryptocurrency.',
                ],
            ],
            'capabilities' => [
                'can_view_coins' => true,
                'can_redeem_credits' => false,
                'can_transfer_coins' => false,
                'can_convert_points' => false,
                'next_action' => 'PREVIEW_COINS',
            ],
        ];
    }

    public function traceNetworkLabel(): string
    {
        return $this->network()->traceLabel;
    }
}
