<?php

namespace App\Services;

use App\Models\IdentityBinding;
use App\Models\VaultIdentity;
use App\Support\BindingValueCanonicalizer;
use App\Support\SettlementAdapterConfig;
use App\Support\SettlementNetwork;
use App\Support\SolanaAddressCodec;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class WalletBindingService
{
    public function __construct(
        private readonly SettlementNetworkRegistry $settlementNetworks,
        private readonly BindingValueCanonicalizer $canonicalizer,
        private readonly BindingEventRecorder $bindingEvents,
        private readonly SettlementAuditEventRecorder $settlementAuditEvents,
    ) {}

    /**
     * @return Collection<int, IdentityBinding>
     */
    public function listForVault(VaultIdentity $vault, ?string $bindingType = null, bool $includeRevoked = false): Collection
    {
        $query = IdentityBinding::query()
            ->where('vault_id', $vault->id)
            ->orderByDesc('id');

        if ($bindingType !== null) {
            $query->where('binding_type', $bindingType);
        }

        if (! $includeRevoked) {
            $query->where('verification_state', '!=', IdentityBinding::STATE_REVOKED);
        }

        return $query->get();
    }

    public function findActiveWalletBinding(VaultIdentity $vault, string $networkKey): ?IdentityBinding
    {
        return IdentityBinding::query()
            ->where('vault_id', $vault->id)
            ->where('binding_type', IdentityBinding::TYPE_WALLET)
            ->where('binding_key', $networkKey)
            ->whereIn('verification_state', IdentityBinding::ACTIVE_STATES)
            ->orderByDesc('id')
            ->first();
    }

    public function findActiveBindingByNormalizedValue(
        string $bindingType,
        string $bindingKey,
        string $bindingValueNormalized,
    ): ?IdentityBinding {
        return IdentityBinding::query()
            ->where('binding_type', $bindingType)
            ->where('binding_key', $bindingKey)
            ->where('binding_value_normalized', $bindingValueNormalized)
            ->whereIn('verification_state', IdentityBinding::ACTIVE_STATES)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function createWalletBinding(
        VaultIdentity $vault,
        string $networkKey,
        string $address,
        string $verificationMethod = IdentityBinding::METHOD_MANUAL,
        array $metadata = [],
        string $verificationState = IdentityBinding::STATE_PENDING,
    ): IdentityBinding {
        $networkKey = trim($networkKey);
        $address = trim($address);
        $network = $this->resolveWalletNetwork($networkKey);
        $canonical = $this->canonicalizeWalletBindingValue($network->protocol, $address);
        $this->assertWalletAddressFormat($network->protocol, $canonical['original']);

        if ($this->findActiveWalletBinding($vault, $networkKey)) {
            throw ValidationException::withMessages([
                'binding_key' => 'An active wallet binding already exists for this transport layer.',
            ]);
        }

        $this->assertActiveBindingValueAvailable(
            bindingType: IdentityBinding::TYPE_WALLET,
            bindingKey: $networkKey,
            bindingValueNormalized: $canonical['normalized'],
            vaultId: $vault->id,
        );

        $now = now();

        $binding = IdentityBinding::query()->create([
            'vault_id' => $vault->id,
            'binding_type' => IdentityBinding::TYPE_WALLET,
            'binding_key' => $networkKey,
            'binding_value_original' => $canonical['original'],
            'binding_value_normalized' => $canonical['normalized'],
            'verification_state' => $verificationState,
            'verification_method' => $verificationMethod,
            'metadata' => array_merge($metadata, [
                'network_label' => $network->label,
                'protocol' => $network->protocol,
            ]),
            'bound_at' => $now,
            'verified_at' => $verificationState === IdentityBinding::STATE_VERIFIED ? $now : null,
        ]);

        if ($verificationState === IdentityBinding::STATE_VERIFIED) {
            $this->bindingEvents->recordWalletBound($binding, $this->bindingEventContext($metadata));

            if (SettlementAdapterConfig::isConfigured($networkKey)) {
                $this->settlementAuditEvents->recordAttachmentCreated(
                    identityId: (string) $vault->anchor_address,
                    vaultId: (string) $vault->id,
                    adapterKey: $networkKey,
                    chain: $networkKey,
                    address: (string) $binding->binding_value_normalized,
                );
            }
        }

        return $binding;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function createVerifiedWalletBinding(
        VaultIdentity $vault,
        string $networkKey,
        string $address,
        string $verificationMethod,
        array $metadata = [],
    ): IdentityBinding {
        return $this->createWalletBinding(
            vault: $vault,
            networkKey: $networkKey,
            address: $address,
            verificationMethod: $verificationMethod,
            metadata: $metadata,
            verificationState: IdentityBinding::STATE_VERIFIED,
        );
    }

    public function revokeBinding(VaultIdentity $vault, IdentityBinding $binding): IdentityBinding
    {
        if ((string) $binding->vault_id !== (string) $vault->id) {
            throw ValidationException::withMessages([
                'binding' => 'Binding does not belong to this vault identity.',
            ]);
        }

        if ($binding->verification_state === IdentityBinding::STATE_REVOKED) {
            return $binding;
        }

        $previousState = (string) $binding->verification_state;

        $binding->forceFill([
            'verification_state' => IdentityBinding::STATE_REVOKED,
            'revoked_at' => now(),
        ])->save();

        $binding->refresh();

        $this->bindingEvents->recordWalletRevoked($binding, [
            'previous_verification_state' => $previousState,
        ]);

        return $binding;
    }

    public function resolveWalletNetwork(string $networkKey): SettlementNetwork
    {
        $networkKey = trim($networkKey);

        if ($networkKey === '' || $networkKey === $this->settlementNetworks->defaultKey()) {
            throw ValidationException::withMessages([
                'binding_key' => 'Primary Vault identity already anchors Simple Layer 1.',
            ]);
        }

        if (! $this->settlementNetworks->cryptoRailsEnabled()) {
            throw ValidationException::withMessages([
                'binding_key' => 'On-chain wallet bindings are disabled. Simple commerce mode is active.',
            ]);
        }

        try {
            $network = $this->settlementNetworks->network($networkKey);
        } catch (\InvalidArgumentException) {
            throw ValidationException::withMessages([
                'binding_key' => 'Unknown wallet binding key.',
            ]);
        }

        if (in_array($network->protocol, ['evm', 'utxo', 'solana'], true) && ! $network->storefrontVisible) {
            throw ValidationException::withMessages([
                'binding_key' => 'This wallet transport layer is not available.',
            ]);
        }

        return $network;
    }

    /**
     * @return array{original: string, normalized: string}
     */
    public function canonicalizeWalletBindingValue(string $protocol, string $address): array
    {
        return $this->canonicalizer->canonicalize($protocol, $address);
    }

    /**
     * @deprecated Use canonicalizeWalletBindingValue().
     * @return array{original: string, normalized: string}
     */
    public function normalizeWalletBindingValue(string $protocol, string $address): array
    {
        return $this->canonicalizeWalletBindingValue($protocol, $address);
    }

    public function assertActiveBindingValueAvailable(
        string $bindingType,
        string $bindingKey,
        string $bindingValueNormalized,
        ?string $vaultId = null,
    ): void {
        $existing = $this->findActiveBindingByNormalizedValue(
            $bindingType,
            $bindingKey,
            $bindingValueNormalized,
        );

        if ($existing === null) {
            return;
        }

        if ($vaultId !== null && (string) $existing->vault_id === (string) $vaultId) {
            return;
        }

        throw ValidationException::withMessages([
            'binding_value' => 'This binding value is already attached to another vault identity.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function formatBinding(IdentityBinding $binding): array
    {
        return [
            'id' => $binding->id,
            'vault_id' => $binding->vault_id,
            'binding_type' => $binding->binding_type,
            'binding_key' => $binding->binding_key,
            'binding_value' => $binding->binding_value_normalized,
            'binding_value_original' => $binding->binding_value_original,
            'binding_value_normalized' => $binding->binding_value_normalized,
            'verification_state' => $binding->verification_state,
            'verification_method' => $binding->verification_method,
            'label' => data_get($binding->metadata, 'network_label', $binding->binding_key),
            'protocol' => data_get($binding->metadata, 'protocol'),
            'metadata' => $binding->metadata ?? [],
            'bound_at' => $binding->bound_at?->toJSON(),
            'verified_at' => $binding->verified_at?->toJSON(),
            'revoked_at' => $binding->revoked_at?->toJSON(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function bindingPreviewFragment(?IdentityBinding $binding): ?array
    {
        if (! $binding instanceof IdentityBinding || ! $binding->isActive()) {
            return null;
        }

        return [
            'id' => $binding->id,
            'vault_id' => $binding->vault_id,
            'binding_type' => $binding->binding_type,
            'binding_key' => $binding->binding_key,
            'address' => $binding->binding_value_normalized,
            'address_original' => $binding->binding_value_original,
            'verification_state' => $binding->verification_state,
            'verification_method' => $binding->verification_method,
            'verified_at' => $binding->verified_at?->toJSON(),
        ];
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private function bindingEventContext(array $metadata): array
    {
        $context = [];

        if (($challengeId = data_get($metadata, 'challenge_id')) !== null) {
            $context['challenge_id'] = $challengeId;
        }

        if (($nonce = data_get($metadata, 'nonce')) !== null) {
            $context['nonce'] = $nonce;
        }

        return $context;
    }

    public function assertWalletAddressFormat(string $protocol, string $address): void
    {
        if ($protocol === 'evm') {
            if (! preg_match('/^0x[a-fA-F0-9]{40}$/', $address)) {
                throw ValidationException::withMessages([
                    'binding_value' => 'EVM wallet bindings require a valid 0x address.',
                ]);
            }

            return;
        }

        if ($protocol === 'utxo') {
            if (! preg_match('/^(bc1[a-z0-9]+|[13][a-km-zA-HJ-NP-Z1-9]{25,34})$/i', $address)) {
                throw ValidationException::withMessages([
                    'binding_value' => 'Bitcoin wallet bindings require a valid mainnet address.',
                ]);
            }

            return;
        }

        if ($protocol === 'solana') {
            if (! app(SolanaAddressCodec::class)->isValidAddress($address)) {
                throw ValidationException::withMessages([
                    'binding_value' => 'Solana wallet bindings require a valid base58 address.',
                ]);
            }

            return;
        }

        if ($address === '') {
            throw ValidationException::withMessages([
                'binding_value' => 'Wallet binding value is required.',
            ]);
        }
    }
}
