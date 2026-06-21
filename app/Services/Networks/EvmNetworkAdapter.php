<?php

namespace App\Services\Networks;

use App\Contracts\SettlementNetworkAdapter;
use App\Contracts\SupportsMerchantDeposits;
use App\Models\LegalEntity;
use App\Models\User;
use App\Services\SettlementNetworkResolver;
use App\Support\EvmDepositAddressGenerator;
use App\Support\EvmDepositProofVerifier;
use App\Support\SettlementAdapterConfig;
use App\Support\SettlementNetwork;

class EvmNetworkAdapter implements SettlementNetworkAdapter, SupportsMerchantDeposits
{
    public function __construct(
        private readonly SettlementNetworkResolver $networks,
        private readonly EvmDepositAddressGenerator $depositAddresses,
        private readonly EvmDepositProofVerifier $depositProofs,
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

        $coins = [];

        foreach ($network->assets as $asset) {
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
                    ? $network->label.' balance is observed from chain when RPC is available.'
                    : $network->label.' balances will appear here once the settlement adapter is enabled.',
            ];
        }

        if ($network->nativeSymbol) {
            array_unshift($coins, [
                'key' => strtolower($network->key.'-native-preview'),
                'symbol' => strtoupper($network->nativeSymbol),
                'name' => $network->label.' native',
                'amount' => '0',
                'display_amount' => '0 '.strtoupper($network->nativeSymbol),
                'precision' => 18,
                'transferable' => false,
                'status' => $adapterEnabled ? 'balance_unavailable' : 'coming_soon',
                'note' => $adapterEnabled
                    ? 'Native gas token observation for '.$network->label.'.'
                    : 'Native gas token preview for '.$network->label.'.',
            ]);
        }

        return [
            'network' => $network->toStorefrontCatalogEntry(),
            'contract' => [
                'name' => 'storefront-vault-wallet-coins',
                'version' => 'v1',
                'authority' => 'marketplace-commerce',
                'network' => $network->contractKey,
                'mode' => $adapterEnabled ? $adapterMode : 'coming_soon',
                'dto_boundary' => $adapterEnabled ? 'settlement_adapter' : 'evm_adapter_pending',
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

    public function merchantDepositPayload(LegalEntity $legalEntity, float $amountRub, array $options = []): array
    {
        $network = $this->network();
        $intentId = (int) ($options['intent_id'] ?? 0);
        $depositAddress = $intentId > 0
            ? $this->depositAddresses->generate($network->key, (int) $legalEntity->id, $intentId)
            : null;

        return [
            'settlement_network' => $network->key,
            'network' => 'evm',
            'chain_id' => $network->chainId,
            'assets' => $network->assets,
            'deposit_address' => $depositAddress,
            'deposit_address_status' => $depositAddress ? 'issued' : 'pending_intent',
            'proof_verification' => 'structural',
            'expected_amount_rub' => round($amountRub, 4),
        ];
    }

    public function verifyDepositProof(array $proofPayload): array
    {
        return $this->depositProofs->verify($this->networkKey, $proofPayload);
    }
}
