<?php

namespace Tests\Feature;

use App\Models\BindingEvent;
use App\Models\IdentityBinding;
use App\Models\User;
use App\Models\VaultIdentity;
use App\Models\VaultManagedWalletKey;
use App\Services\StorefrontTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class StorefrontManagedWalletProvisioningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withCommerceCryptoRailsEnabled();
        $this->withManagedWalletsEnabled();
    }

    #[Test]
    public function managed_provisioning_creates_identity_binding_not_managed_wallet_entity(): void
    {
        $entityAddress = 'sl1e_'.str_repeat('d', 39);
        $user = User::factory()->create(['entity_l1_address' => $entityAddress]);
        $token = $this->vaultToken($entityAddress);

        $binding = $this->withToken($token)
            ->postJson('/api/storefront/v1/wallet/bindings/managed', [
                'binding_key' => 'polygon',
            ])
            ->assertCreated()
            ->assertJsonPath('binding.binding_type', 'wallet')
            ->assertJsonPath('binding.binding_key', 'polygon')
            ->assertJsonPath('binding.binding_source', IdentityBinding::SOURCE_MANAGED)
            ->assertJsonPath('binding.verification_state', IdentityBinding::STATE_VERIFIED)
            ->assertJsonPath('binding.verification_method', IdentityBinding::METHOD_VAULT_KEY)
            ->json('binding');

        $vaultId = VaultIdentity::query()->where('owner_user_id', $user->id)->value('id');

        $this->assertDatabaseHas('identity_bindings', [
            'id' => $binding['id'],
            'vault_id' => $vaultId,
            'binding_key' => 'polygon',
            'binding_source' => IdentityBinding::SOURCE_MANAGED,
            'verification_method' => IdentityBinding::METHOD_VAULT_KEY,
            'verification_state' => IdentityBinding::STATE_VERIFIED,
        ]);

        $this->assertDatabaseHas('vault_managed_wallet_keys', [
            'vault_id' => $vaultId,
            'identity_binding_id' => $binding['id'],
            'network_key' => 'polygon',
            'address_normalized' => $binding['binding_value'],
        ]);

        $this->assertDatabaseHas('binding_events', [
            'vault_id' => $vaultId,
            'identity_binding_id' => $binding['id'],
            'binding_type' => IdentityBinding::TYPE_WALLET,
            'binding_key' => 'polygon',
            'event_type' => BindingEvent::TYPE_WALLET_BOUND,
            'verification_method' => IdentityBinding::METHOD_VAULT_KEY,
        ]);
    }

    #[Test]
    public function managed_binding_survives_relogin_and_cache_replay(): void
    {
        $entityAddress = 'sl1e_'.str_repeat('e', 39);
        User::factory()->create(['entity_l1_address' => $entityAddress]);
        $token = $this->vaultToken($entityAddress);

        $provisioned = $this->withToken($token)
            ->postJson('/api/storefront/v1/wallet/bindings/managed', ['binding_key' => 'polygon'])
            ->assertCreated()
            ->json('binding');

        $reloginToken = $this->vaultToken($entityAddress);
        $afterRelogin = $this->withToken($reloginToken)
            ->getJson('/api/storefront/v1/wallet/bindings')
            ->assertOk()
            ->json('items.0');

        $this->assertSame($provisioned['id'], $afterRelogin['id']);
        $this->assertSame($provisioned['binding_value'], $afterRelogin['binding_value']);
        $this->assertSame(IdentityBinding::SOURCE_MANAGED, $afterRelogin['binding_source']);

        Cache::flush();

        $postCacheToken = $this->vaultToken($entityAddress);
        $afterCache = $this->withToken($postCacheToken)
            ->getJson('/api/storefront/v1/wallet/bindings')
            ->assertOk()
            ->json('items.0');

        $this->assertSame($provisioned['id'], $afterCache['id']);
        $this->assertSame($provisioned['binding_value'], $afterCache['binding_value']);
        $this->assertSame(IdentityBinding::SOURCE_MANAGED, $afterCache['binding_source']);
    }

    #[Test]
    public function second_managed_provision_is_rejected_when_binding_already_exists(): void
    {
        $entityAddress = 'sl1e_'.str_repeat('f', 39);
        User::factory()->create(['entity_l1_address' => $entityAddress]);
        $token = $this->vaultToken($entityAddress);

        $this->withToken($token)
            ->postJson('/api/storefront/v1/wallet/bindings/managed', ['binding_key' => 'polygon'])
            ->assertCreated();

        $this->withToken($token)
            ->postJson('/api/storefront/v1/wallet/bindings/managed', ['binding_key' => 'polygon'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['binding_key']);

        $this->assertSame(
            1,
            IdentityBinding::query()
                ->where('binding_key', 'polygon')
                ->where('binding_source', IdentityBinding::SOURCE_MANAGED)
                ->count(),
        );

        $this->assertSame(1, VaultManagedWalletKey::query()->where('network_key', 'polygon')->count());
    }

    #[Test]
    public function managed_provisioning_is_disabled_without_feature_flag(): void
    {
        config(['managed_wallets.enabled' => false]);

        $entityAddress = 'sl1e_'.str_repeat('1', 39);
        User::factory()->create(['entity_l1_address' => $entityAddress]);
        $token = $this->vaultToken($entityAddress);

        $this->withToken($token)
            ->postJson('/api/storefront/v1/wallet/bindings/managed', ['binding_key' => 'polygon'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['binding_key']);

        $this->withToken($token)
            ->getJson('/api/storefront/v1/wallet')
            ->assertOk()
            ->assertJsonPath('capabilities.managed_wallets_enabled', false)
            ->assertJsonPath('capabilities.can_provision_managed_wallet', false);
    }

    #[Test]
    public function wallet_summary_exposes_managed_wallet_capability_when_enabled(): void
    {
        $entityAddress = 'sl1e_'.str_repeat('2', 39);
        User::factory()->create(['entity_l1_address' => $entityAddress]);
        $token = $this->vaultToken($entityAddress);

        $this->withToken($token)
            ->getJson('/api/storefront/v1/wallet')
            ->assertOk()
            ->assertJsonPath('capabilities.managed_wallets_enabled', true)
            ->assertJsonPath('capabilities.can_provision_managed_wallet', true)
            ->assertJsonPath('capabilities.managed_wallet_networks', ['polygon']);
    }

    #[Test]
    public function managed_provisioning_supports_additional_enabled_evm_networks(): void
    {
        config([
            'managed_wallets.networks.ethereum' => true,
            'managed_wallets.networks.base' => true,
        ]);

        $entityAddress = 'sl1e_'.str_repeat('3', 39);
        User::factory()->create(['entity_l1_address' => $entityAddress]);
        $token = $this->vaultToken($entityAddress);

        foreach (['polygon', 'ethereum', 'base'] as $networkKey) {
            $binding = $this->withToken($token)
                ->postJson('/api/storefront/v1/wallet/bindings/managed', [
                    'binding_key' => $networkKey,
                ])
                ->assertCreated()
                ->assertJsonPath('binding.binding_key', $networkKey)
                ->assertJsonPath('binding.binding_source', IdentityBinding::SOURCE_MANAGED)
                ->json('binding');

            $this->assertDatabaseHas('vault_managed_wallet_keys', [
                'identity_binding_id' => $binding['id'],
                'network_key' => $networkKey,
            ]);
        }

        $this->withToken($token)
            ->getJson('/api/storefront/v1/wallet')
            ->assertOk()
            ->assertJsonPath('capabilities.managed_wallet_networks', ['polygon', 'ethereum', 'base']);
    }

    #[Test]
    public function managed_provisioning_rejects_networks_disabled_in_config(): void
    {
        config(['managed_wallets.networks.ethereum' => false]);

        $entityAddress = 'sl1e_'.str_repeat('4', 39);
        User::factory()->create(['entity_l1_address' => $entityAddress]);
        $token = $this->vaultToken($entityAddress);

        $this->withToken($token)
            ->postJson('/api/storefront/v1/wallet/bindings/managed', ['binding_key' => 'ethereum'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['binding_key']);
    }

    private function vaultToken(string $entityAddress): string
    {
        return app(StorefrontTokenService::class)->issue([
            'entity_l1_address' => $entityAddress,
            'proof_token_hash' => hash('sha256', $entityAddress),
        ], ['storefront:read', 'storefront:vault'])['access_token'];
    }
}
