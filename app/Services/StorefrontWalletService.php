<?php

namespace App\Services;

use App\Models\IdentityBinding;
use App\Models\User;
use App\Models\VaultIdentity;
use App\Services\ManagedWallet\ManagedWalletProvisioner;
use App\Support\SettlementAdapterConfig;
use Illuminate\Http\Request;

class StorefrontWalletService
{
    public function __construct(
        private readonly SettlementNetworkRegistry $settlementNetworks,
        private readonly WalletBindingService $bindings,
        private readonly VaultIdentityService $vaultIdentities,
        private readonly EvmWalletPreviewEnricher $evmWalletPreviewEnricher,
        private readonly UtxoWalletPreviewEnricher $utxoWalletPreviewEnricher,
        private readonly SolanaWalletPreviewEnricher $solanaWalletPreviewEnricher,
        private readonly TonWalletPreviewEnricher $tonWalletPreviewEnricher,
        private readonly SettlementAdapterRegistry $settlementAdapters,
        private readonly ManagedWalletProvisioner $managedWallets,
    ) {}

    /**
     * @return array{identity: array<string, mixed>, user: User|null, vault: VaultIdentity}
     */
    public function resolveContext(Request $request, bool $bootstrapInstruments = false): array
    {
        $identity = (array) $request->attributes->get('storefront_identity', []);
        $address = strtolower((string) data_get($identity, 'entity_l1_address'));
        abort_if($address === '', 403);

        $resolver = app(MarketplaceIdentityResolver::class);
        $user = $resolver->resolveFromIdentity($identity);

        $vault = $this->vaultIdentities->resolveForStorefront($identity, $user);

        if ($bootstrapInstruments
            && (bool) config('managed_wallets.enabled', false)
            && (bool) config('managed_wallets.auto_provision_on_vault', true)) {
            try {
                $user = $resolver->ensureUserFromIdentity($identity) ?? $user;
                if ($user instanceof User) {
                    if ((int) ($vault->owner_user_id ?? 0) !== (int) $user->id) {
                        $vault->forceFill(['owner_user_id' => $user->id])->save();
                    }
                    $this->managedWallets->bootstrapDefaultInstruments($vault->refresh());
                }
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        if ($user instanceof User) {
            $identity['username'] = $user->username;
            $identity['display_alias'] = $user->publicUsername() ?: ($identity['display_alias'] ?? null);
        }

        return [
            'identity' => $identity,
            'user' => $user,
            'vault' => $vault,
        ];
    }

    /**
     * @param array<string, mixed> $identity
     */
    public function walletSummary(array $identity, VaultIdentity $vault, ?User $user = null): array
    {
        $defaultNetwork = $this->settlementNetworks->defaultNetwork();

        return [
            'contract' => [
                'name' => 'storefront-vault-wallet',
                'version' => 'v1',
                'authority' => 'marketplace-commerce',
                'mode' => 'identity_first',
            ],
            'identity' => $identity,
            'vault' => [
                'id' => $vault->id,
                'label' => 'Meanly Vault',
                'kind' => $vault->vault_kind,
                'anchor_network_key' => $defaultNetwork->key,
                'entity_l1_address' => $identity['entity_l1_address'] ?? null,
            ],
            'settlement_networks' => $this->settlementNetworks->storefrontCatalog(),
            'capabilities' => [
                'can_view_bindings' => true,
                'can_manage_bindings' => $user instanceof User && $this->settlementNetworks->cryptoRailsEnabled(),
                'can_request_binding_challenge' => $user instanceof User && $this->settlementNetworks->cryptoRailsEnabled(),
                'can_submit_transfer_proofs' => $this->settlementNetworks->cryptoRailsEnabled()
                    && $this->settlementAdapters->allowsWrite($this->settlementNetworks->merchantCryptoNetworkKey()),
                'crypto_rails_enabled' => $this->settlementNetworks->cryptoRailsEnabled(),
                'managed_wallets_enabled' => (bool) config('managed_wallets.enabled', false),
                'managed_wallet_networks' => $this->managedWallets->enabledNetworkKeys(),
                'auto_provision_on_vault' => (bool) config('managed_wallets.enabled', false)
                    && (bool) config('managed_wallets.auto_provision_on_vault', true),
                'can_provision_managed_wallet' => $user instanceof User
                    && $this->settlementNetworks->cryptoRailsEnabled()
                    && (bool) config('managed_wallets.enabled', false),
                'legacy_wallet_connect_enabled' => $this->settlementNetworks->cryptoRailsEnabled()
                    && (bool) config('managed_wallets.legacy_connect_enabled', false),
                'can_view_assets' => true,
                'identity_payments_enabled' => (bool) config('identity_payments.enabled', false),
                'identity_payments_execute' => (bool) config('identity_payments.enabled', false)
                    && (bool) config('identity_payments.execute_enabled', false),
                'identity_payment_disputes_enabled' => (bool) config('identity_payments.enabled', false)
                    && (bool) config('identity_payments.disputes_enabled', false),
                'activity' => [
                    'enabled' => false,
                    'status' => 'coming_soon',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function bindingsPayload(VaultIdentity $vault): array
    {
        $items = $this->bindings->listForVault($vault, IdentityBinding::TYPE_WALLET)
            ->map(fn (IdentityBinding $binding) => $this->bindings->formatBinding($binding))
            ->values()
            ->all();

        return [
            'contract' => [
                'name' => 'storefront-vault-wallet-bindings',
                'version' => 'v1',
            ],
            'vault_id' => $vault->id,
            'items' => $items,
        ];
    }

    /**
     * @param array<string, mixed> $identity
     * @return array<string, mixed>
     */
    public function assetsPayload(array $identity, VaultIdentity $vault, ?User $user = null): array
    {
        $defaultAdapter = $this->settlementNetworks->defaultAdapter();
        $defaultKey = $this->settlementNetworks->defaultKey();
        $walletBindings = $this->bindings->listForVault($vault, IdentityBinding::TYPE_WALLET)
            ->keyBy('binding_key');

        $defaultPreview = $this->attachBinding(
            $defaultAdapter->walletPreview($identity, $user),
            null,
        );

        $networkWallets = [];
        foreach ($this->settlementNetworks->storefrontCatalog()['items'] as $networkEntry) {
            $networkKey = (string) ($networkEntry['key'] ?? '');
            if ($networkKey === '' || $networkKey === $defaultKey) {
                continue;
            }

            try {
                $preview = $this->attachBinding(
                    $this->settlementNetworks->adapter($networkKey)->walletPreview($identity, $user),
                    $walletBindings->get($networkKey),
                );
                $preview = $this->enrichNetworkPreview(
                    $networkKey,
                    $preview,
                    $vault,
                    $walletBindings->get($networkKey),
                );
                $networkWallets[] = $preview;
            } catch (\InvalidArgumentException) {
                // Skip networks without a registered adapter.
            }
        }

        return [
            ...$defaultPreview,
            'settlement_network' => $this->settlementNetworks->defaultNetwork()->toStorefrontCatalogEntry(),
            'default_network_key' => $defaultKey,
            'network_wallets' => $networkWallets,
            'bound_wallets' => $networkWallets,
        ];
    }

    /**
     * @param array<string, mixed> $preview
     * @return array<string, mixed>
     */
    private function attachBinding(array $preview, ?IdentityBinding $binding): array
    {
        $fragment = $this->bindings->bindingPreviewFragment($binding);
        if ($fragment !== null) {
            $preview['binding'] = $fragment;
            $preview['address'] = $fragment['address'];
        }

        return $preview;
    }

    /**
     * @param array<string, mixed> $preview
     * @return array<string, mixed>
     */
    private function enrichNetworkPreview(
        string $networkKey,
        array $preview,
        VaultIdentity $vault,
        ?IdentityBinding $binding,
    ): array {
        try {
            $network = $this->settlementNetworks->network($networkKey);
        } catch (\InvalidArgumentException) {
            return $preview;
        }

        if (! in_array($network->protocol, ['evm', 'utxo', 'solana', 'ton'], true)) {
            return $preview;
        }

        if ($binding instanceof IdentityBinding
            && SettlementAdapterConfig::isEnabled($networkKey)
            && $this->settlementAdapters->has($networkKey)) {
            $observation = $this->settlementAdapters->adapter($networkKey)->observeBalance($vault, $binding);
            if (($observation['observed'] ?? false) === true) {
                return $this->mergeObservationIntoPreview($preview, $observation);
            }

            return $this->mergeObservationFailureIntoPreview($preview, $observation);
        }

        $walletAddress = isset($preview['address']) && is_string($preview['address'])
            ? $preview['address']
            : null;

        if ($network->protocol === 'evm') {
            return $this->evmWalletPreviewEnricher->enrich($preview, $network, $walletAddress);
        }

        if ($network->protocol === 'solana') {
            return $this->solanaWalletPreviewEnricher->enrich($preview, $network, $walletAddress);
        }

        if ($network->protocol === 'ton') {
            return $this->tonWalletPreviewEnricher->enrich($preview, $network, $walletAddress);
        }

        return $this->utxoWalletPreviewEnricher->enrich($preview, $network, $walletAddress);
    }

    /**
     * @param array<string, mixed> $preview
     * @param array<string, mixed> $observation
     * @return array<string, mixed>
     */
    private function mergeObservationIntoPreview(array $preview, array $observation): array
    {
        $coinsBySymbol = [];
        foreach ($observation['coins'] ?? [] as $coin) {
            if (! is_array($coin)) {
                continue;
            }

            $coinsBySymbol[strtoupper((string) ($coin['symbol'] ?? ''))] = $coin;
        }

        foreach ($preview['coins'] ?? [] as $index => $coin) {
            if (! is_array($coin)) {
                continue;
            }

            $symbol = strtoupper((string) ($coin['symbol'] ?? ''));
            $observed = $coinsBySymbol[$symbol] ?? null;
            if (! is_array($observed)) {
                continue;
            }

            $preview['coins'][$index]['amount'] = $observed['amount'];
            $preview['coins'][$index]['display_amount'] = $observed['display_amount'];
            $preview['coins'][$index]['status'] = $observed['status'];
            unset($preview['coins'][$index]['note']);
        }

        if (is_array($observation['capabilities'] ?? null)) {
            $preview['capabilities'] = array_merge(
                is_array($preview['capabilities'] ?? null) ? $preview['capabilities'] : [],
                $observation['capabilities'],
            );
        }

        $preview['wallet']['tier'] = 'bound';
        $preview['contract']['mode'] = 'bound_preview';

        return $preview;
    }

    /**
     * @param array<string, mixed> $preview
     * @param array<string, mixed> $observation
     * @return array<string, mixed>
     */
    private function mergeObservationFailureIntoPreview(array $preview, array $observation): array
    {
        $coinsBySymbol = [];
        foreach ($observation['coins'] ?? [] as $coin) {
            if (! is_array($coin)) {
                continue;
            }

            $coinsBySymbol[strtoupper((string) ($coin['symbol'] ?? ''))] = $coin;
        }

        foreach ($preview['coins'] ?? [] as $index => $coin) {
            if (! is_array($coin)) {
                continue;
            }

            $symbol = strtoupper((string) ($coin['symbol'] ?? ''));
            $observed = $coinsBySymbol[$symbol] ?? null;
            if (! is_array($observed)) {
                continue;
            }

            $preview['coins'][$index]['status'] = $observed['status'];
            $preview['coins'][$index]['note'] = ($observation['reason'] ?? 'observation_failed').': observation unavailable; this is not a zero balance.';
            unset($preview['coins'][$index]['amount'], $preview['coins'][$index]['display_amount']);
        }

        $preview['wallet']['tier'] = 'bound';
        $preview['contract']['mode'] = 'observation_unavailable';
        $preview['capabilities'] = array_merge(
            is_array($preview['capabilities'] ?? null) ? $preview['capabilities'] : [],
            [
                'can_view_coins' => false,
                'next_action' => 'NETWORK_RPC_REQUIRED',
                'observation_state' => $observation['observation_state'] ?? 'balance_unavailable',
            ],
        );

        return $preview;
    }
}
