<?php

namespace Tests\Feature;

use App\Models\IdentityBinding;
use App\Models\User;
use App\Models\VaultIdentity;
use App\Services\StorefrontTokenService;
use App\Support\EvmPersonalSignVerifier;
use Elliptic\EC;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontWalletBindingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_wallet_summary_bindings_and_assets_are_split_resources(): void
    {
        $entityAddress = 'sl1e_a00000000000000000000000000000000000001';
        $token = $this->vaultToken($entityAddress);

        $this->withToken($token)
            ->getJson('/api/storefront/v1/wallet')
            ->assertOk()
            ->assertJsonPath('contract.name', 'storefront-vault-wallet')
            ->assertJsonPath('identity.entity_l1_address', $entityAddress)
            ->assertJsonPath('settlement_networks.default', 'simple-layer-1')
            ->assertJsonPath('vault.anchor_network_key', 'simple-layer-1')
            ->assertJsonStructure(['vault' => ['id']]);

        $this->withToken($token)
            ->getJson('/api/storefront/v1/wallet/bindings')
            ->assertOk()
            ->assertJsonPath('contract.name', 'storefront-vault-wallet-bindings')
            ->assertJsonPath('items', [])
            ->assertJsonStructure(['vault_id']);

        $this->withToken($token)
            ->getJson('/api/storefront/v1/wallet/assets')
            ->assertOk()
            ->assertJsonPath('contract.network', 'simple-layer-1')
            ->assertJsonPath('settlement_network.key', 'simple-layer-1')
            ->assertJsonPath('network_wallets', [])
            ->assertJsonPath('bound_wallets', [])
            ->assertJsonMissingPath('identity')
            ->assertJsonMissingPath('settlement_networks');
    }

    public function test_authenticated_identity_can_create_and_revoke_manual_wallet_binding(): void
    {
        $this->withCommerceCryptoRailsEnabled();
        $entityAddress = 'sl1e_'.str_repeat('b', 39);
        $user = User::factory()->create([
            'entity_l1_address' => $entityAddress,
        ]);
        $token = $this->vaultToken($entityAddress);
        $address = '0xabcdefabcdefabcdefabcdefabcdefabcdefabcd';

        $this->withToken($token)
            ->postJson('/api/storefront/v1/wallet/bindings', [
                'binding_type' => 'wallet',
                'binding_key' => 'polygon',
                'binding_value' => $address,
                'verification_method' => 'manual',
            ])
            ->assertCreated()
            ->assertJsonPath('binding.binding_type', 'wallet')
            ->assertJsonPath('binding.binding_key', 'polygon')
            ->assertJsonPath('binding.binding_value', strtolower($address))
            ->assertJsonPath('binding.verification_state', IdentityBinding::STATE_PENDING)
            ->assertJsonPath('binding.verification_method', IdentityBinding::METHOD_MANUAL)
            ->assertJsonStructure(['binding' => ['vault_id']]);

        $vaultId = VaultIdentity::query()->where('owner_user_id', $user->id)->value('id');

        $bindingId = (int) $this->withToken($token)
            ->getJson('/api/storefront/v1/wallet/bindings')
            ->assertOk()
            ->json('items.0.id');

        $this->withToken($token)
            ->deleteJson('/api/storefront/v1/wallet/bindings/'.$bindingId)
            ->assertOk()
            ->assertJsonPath('binding.verification_state', IdentityBinding::STATE_REVOKED);

        $this->assertDatabaseHas('identity_bindings', [
            'id' => $bindingId,
            'vault_id' => $vaultId,
            'verification_state' => IdentityBinding::STATE_REVOKED,
        ]);
    }

    public function test_wallet_binding_rejects_invalid_evm_address(): void
    {
        $this->withCommerceCryptoRailsEnabled();
        $entityAddress = 'sl1e_'.str_repeat('c', 39);
        User::factory()->create([
            'entity_l1_address' => $entityAddress,
        ]);

        $this->withToken($this->vaultToken($entityAddress))
            ->postJson('/api/storefront/v1/wallet/bindings', [
                'binding_type' => 'wallet',
                'binding_key' => 'polygon',
                'binding_value' => 'not-an-address',
            ])
            ->assertUnprocessable();
    }

    private function vaultToken(string $entityAddress): string
    {
        return app(StorefrontTokenService::class)->issue([
            'entity_l1_address' => $entityAddress,
            'proof_token_hash' => hash('sha256', $entityAddress),
        ], ['storefront:read', 'storefront:vault'])['access_token'];
    }
}
