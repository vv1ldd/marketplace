<?php

namespace App\Services\ManagedWallet;

use App\Models\IdentityBinding;
use App\Models\VaultIdentity;
use App\Models\VaultManagedWalletKey;
use App\Services\WalletBindingService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ManagedWalletProvisioner
{
    public function __construct(
        private readonly WalletBindingService $bindings,
        private readonly ManagedKeyMaterialGeneratorRegistry $generators,
        private readonly ManagedWalletSecretValidator $secretValidator,
    ) {}

    public function isEnabledForNetwork(string $networkKey): bool
    {
        if (! (bool) config('managed_wallets.enabled', false)) {
            return false;
        }

        return (bool) config('managed_wallets.networks.'.$networkKey, false);
    }

    /**
     * @return list<string>
     */
    public function enabledNetworkKeys(): array
    {
        if (! (bool) config('managed_wallets.enabled', false)) {
            return [];
        }

        $keys = [];
        foreach (array_keys((array) config('managed_wallets.networks', [])) as $networkKey) {
            if ($this->isEnabledForNetwork((string) $networkKey)) {
                $keys[] = (string) $networkKey;
            }
        }

        return $keys;
    }

    /**
     * @return list<IdentityBinding>
     */
    public function bootstrapDefaultInstruments(VaultIdentity $vault): array
    {
        if (! (bool) config('managed_wallets.enabled', false)) {
            return [];
        }

        if (! (bool) config('managed_wallets.auto_provision_on_vault', true)) {
            return [];
        }

        return DB::transaction(function () use ($vault): array {
            $vault = VaultIdentity::query()->lockForUpdate()->findOrFail($vault->id);

            if ($this->bindings->listForVault($vault, IdentityBinding::TYPE_WALLET)->isNotEmpty()) {
                return [];
            }

            $created = [];
            $evmMaterial = null;

            foreach ($this->bootstrapNetworkKeys() as $networkKey) {
                if ($this->bindings->findActiveWalletBinding($vault, $networkKey) instanceof IdentityBinding) {
                    continue;
                }

                try {
                    $protocol = $this->bindings->resolveWalletNetwork($networkKey)->protocol;

                    if ($protocol === 'evm') {
                        $evmMaterial ??= $this->generators->forProtocol('evm')->generate();
                        $material = $evmMaterial;
                    } else {
                        $material = $this->generators->forProtocol($protocol)->generate();
                    }

                    $created[] = $this->persistBinding(
                        $vault,
                        $networkKey,
                        $material,
                        'managed_wallet_v0_bootstrap',
                    );
                } catch (\Throwable $exception) {
                    report($exception);
                }
            }

            return $created;
        });
    }

    /**
     * @return list<string>
     */
    private function bootstrapNetworkKeys(): array
    {
        $fundingKeys = array_values(array_filter(
            (array) config('managed_wallets.bootstrap_funding_networks', []),
            fn ($networkKey) => is_string($networkKey) && $networkKey !== '',
        ));

        $order = $fundingKeys !== []
            ? $fundingKeys
            : array_values(array_filter(
                (array) config('managed_wallets.bootstrap_network_order', []),
                fn ($networkKey) => is_string($networkKey) && $networkKey !== '',
            ));

        if ($order === []) {
            $order = ['polygon', 'base', 'ethereum'];
        }

        return array_values(array_filter(
            $order,
            fn (string $networkKey): bool => $this->isEnabledForNetwork($networkKey),
        ));
    }

    public function provision(VaultIdentity $vault, string $networkKey): IdentityBinding
    {
        $networkKey = trim($networkKey);

        if (! $this->isEnabledForNetwork($networkKey)) {
            throw ValidationException::withMessages([
                'binding_key' => 'Managed wallet provisioning is not enabled for this network.',
            ]);
        }

        $network = $this->bindings->resolveWalletNetwork($networkKey);

        if ($this->bindings->findActiveWalletBinding($vault, $networkKey) instanceof IdentityBinding) {
            throw ValidationException::withMessages([
                'binding_key' => 'An active wallet binding already exists for this transport layer.',
            ]);
        }

        $generator = $this->generators->forProtocol($network->protocol);
        $keyMaterial = $generator->generate();

        return $this->persistBinding($vault, $networkKey, $keyMaterial, 'managed_wallet_v0');
    }

    public function importFromSecret(
        VaultIdentity $vault,
        string $networkKey,
        string $address,
        string $secret,
        string $secretFormat,
    ): IdentityBinding {
        $networkKey = trim($networkKey);

        if (! $this->isEnabledForNetwork($networkKey)) {
            throw ValidationException::withMessages([
                'binding_key' => 'Managed wallet import is not enabled for this network.',
            ]);
        }

        $network = $this->bindings->resolveWalletNetwork($networkKey);

        if ($this->bindings->findActiveWalletBinding($vault, $networkKey) instanceof IdentityBinding) {
            throw ValidationException::withMessages([
                'binding_key' => 'An active wallet binding already exists for this transport layer.',
            ]);
        }

        $validated = $this->secretValidator->validate(
            $network->protocol,
            $address,
            $secret,
            $secretFormat,
        );

        $keyMaterial = [
            'address' => $validated['address'],
            'secret' => $validated['secret'],
            'secret_format' => $validated['secret_format'],
            'key_reference' => (string) Str::uuid(),
        ];

        return $this->persistBinding($vault, $networkKey, $keyMaterial, 'managed_wallet_import_v0');
    }

    /**
     * @param  array{
     *     address: string,
     *     secret: string,
     *     secret_format: string,
     *     key_reference: string
     * }  $keyMaterial
     */
    private function persistBinding(
        VaultIdentity $vault,
        string $networkKey,
        array $keyMaterial,
        string $provisioning,
    ): IdentityBinding {
        return DB::transaction(function () use ($vault, $networkKey, $keyMaterial, $provisioning): IdentityBinding {
            if ($this->bindings->findActiveWalletBinding($vault, $networkKey) instanceof IdentityBinding) {
                throw ValidationException::withMessages([
                    'binding_key' => 'An active wallet binding already exists for this transport layer.',
                ]);
            }

            $binding = $this->bindings->createVerifiedWalletBinding(
                vault: $vault,
                networkKey: $networkKey,
                address: $keyMaterial['address'],
                verificationMethod: IdentityBinding::METHOD_VAULT_KEY,
                metadata: [
                    'managed_key_reference' => $keyMaterial['key_reference'],
                    'provisioning' => $provisioning,
                    'secret_format' => $keyMaterial['secret_format'],
                ],
                bindingSource: IdentityBinding::SOURCE_MANAGED,
            );

            $existingManagedKey = VaultManagedWalletKey::query()
                ->where('vault_id', $vault->id)
                ->where('key_reference', $keyMaterial['key_reference'])
                ->first();

            if (! $existingManagedKey instanceof VaultManagedWalletKey) {
                VaultManagedWalletKey::query()->create([
                    'vault_id' => $vault->id,
                    'identity_binding_id' => $binding->id,
                    'network_key' => $networkKey,
                    'address_normalized' => $binding->binding_value_normalized,
                    'key_reference' => $keyMaterial['key_reference'],
                    'encrypted_secret' => Crypt::encryptString($keyMaterial['secret']),
                ]);
            }

            return $binding->refresh();
        });
    }
}
