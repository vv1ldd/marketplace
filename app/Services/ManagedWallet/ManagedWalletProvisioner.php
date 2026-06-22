<?php

namespace App\Services\ManagedWallet;

use App\Models\IdentityBinding;
use App\Models\VaultIdentity;
use App\Models\VaultManagedWalletKey;
use App\Support\EvmManagedKeyGenerator;
use App\Services\WalletBindingService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManagedWalletProvisioner
{
    public function __construct(
        private readonly WalletBindingService $bindings,
        private readonly EvmManagedKeyGenerator $keyGenerator,
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

    public function provision(VaultIdentity $vault, string $networkKey): IdentityBinding
    {
        $networkKey = trim($networkKey);

        if (! $this->isEnabledForNetwork($networkKey)) {
            throw ValidationException::withMessages([
                'binding_key' => 'Managed wallet provisioning is not enabled for this network.',
            ]);
        }

        $network = $this->bindings->resolveWalletNetwork($networkKey);
        if ($network->protocol !== 'evm') {
            throw ValidationException::withMessages([
                'binding_key' => 'Managed wallet provisioning requires an EVM network.',
            ]);
        }

        if ($this->bindings->findActiveWalletBinding($vault, $networkKey) instanceof IdentityBinding) {
            throw ValidationException::withMessages([
                'binding_key' => 'An active wallet binding already exists for this transport layer.',
            ]);
        }

        $keyMaterial = $this->keyGenerator->generate();

        return DB::transaction(function () use ($vault, $networkKey, $keyMaterial): IdentityBinding {
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
                    'provisioning' => 'managed_wallet_v0',
                ],
                bindingSource: IdentityBinding::SOURCE_MANAGED,
            );

            VaultManagedWalletKey::query()->create([
                'vault_id' => $vault->id,
                'identity_binding_id' => $binding->id,
                'network_key' => $networkKey,
                'address_normalized' => $binding->binding_value_normalized,
                'key_reference' => $keyMaterial['key_reference'],
                'encrypted_secret' => Crypt::encryptString($keyMaterial['private_key_hex']),
            ]);

            return $binding->refresh();
        });
    }
}
