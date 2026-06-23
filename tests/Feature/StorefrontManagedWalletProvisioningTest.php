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
    public function first_wallet_access_bootstraps_all_enabled_managed_instruments(): void
    {
        config([
            'managed_wallets.networks.polygon' => true,
            'managed_wallets.networks.base' => true,
            'managed_wallets.networks.ethereum' => true,
            'managed_wallets.networks.bitcoin' => false,
            'managed_wallets.networks.solana' => false,
            'managed_wallets.networks.ton' => false,
        ]);

        $entityAddress = 'sl1e_'.str_repeat('8', 39);
        User::factory()->create(['entity_l1_address' => $entityAddress]);
        $token = $this->vaultToken($entityAddress);

        $this->withToken($token)
            ->getJson('/api/storefront/v1/wallet')
            ->assertOk()
            ->assertJsonPath('capabilities.auto_provision_on_vault', true);

        $vaultId = VaultIdentity::query()->where('anchor_address', $entityAddress)->value('id');
        $bindings = IdentityBinding::query()
            ->where('vault_id', $vaultId)
            ->orderBy('binding_key')
            ->get();

        $this->assertSame(
            ['base', 'ethereum', 'polygon'],
            $bindings->pluck('binding_key')->sort()->values()->all(),
        );

        $polygon = $bindings->firstWhere('binding_key', 'polygon');
        $base = $bindings->firstWhere('binding_key', 'base');
        $ethereum = $bindings->firstWhere('binding_key', 'ethereum');

        $this->assertSame($polygon?->binding_value_normalized, $base?->binding_value_normalized);
        $this->assertSame($polygon?->binding_value_normalized, $ethereum?->binding_value_normalized);
        $this->assertSame(3, VaultManagedWalletKey::query()->where('vault_id', $vaultId)->count());
    }

    #[Test]
    public function first_wallet_access_bootstraps_even_when_marketplace_user_did_not_exist_yet(): void
    {
        $entityAddress = 'sl1e_'.str_repeat('3', 39);
        $token = $this->vaultToken($entityAddress);

        $this->assertNull(User::query()->where('entity_l1_address', $entityAddress)->first());

        $this->withToken($token)
            ->getJson('/api/storefront/v1/wallet')
            ->assertOk()
            ->assertJsonPath('capabilities.auto_provision_on_vault', true);

        $this->assertNotNull(User::query()->where('entity_l1_address', $entityAddress)->first());

        $vaultId = VaultIdentity::query()->where('anchor_address', $entityAddress)->value('id');
        $this->assertSame(
            1,
            IdentityBinding::query()->where('vault_id', $vaultId)->where('binding_key', 'polygon')->count(),
        );
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
            ->assertJsonPath('capabilities.legacy_wallet_connect_enabled', false)
            ->assertJsonPath('capabilities.managed_wallet_networks', ['polygon', 'bitcoin', 'solana', 'ton']);
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
            ->assertJsonPath('capabilities.managed_wallet_networks', ['polygon', 'ethereum', 'base', 'bitcoin', 'solana', 'ton']);
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

    #[Test]
    public function managed_provisioning_supports_bitcoin_and_solana_networks(): void
    {
        $entityAddress = 'sl1e_'.str_repeat('5', 39);
        User::factory()->create(['entity_l1_address' => $entityAddress]);
        $token = $this->vaultToken($entityAddress);

        foreach (['bitcoin', 'solana', 'ton'] as $networkKey) {
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
    }

    #[Test]
    public function managed_import_accepts_client_derived_solana_secret(): void
    {
        if (! function_exists('sodium_crypto_sign_keypair')) {
            $this->markTestSkipped('Solana managed wallet import requires the sodium extension.');
        }

        $entityAddress = 'sl1e_'.str_repeat('6', 39);
        User::factory()->create(['entity_l1_address' => $entityAddress]);
        $token = $this->vaultToken($entityAddress);

        $keyPair = sodium_crypto_sign_keypair();
        $secretKey = sodium_crypto_sign_secretkey($keyPair);
        $publicKey = sodium_crypto_sign_publickey($keyPair);
        $address = app(\App\Support\SolanaAddressCodec::class)->encodeAddress($publicKey);

        $binding = $this->withToken($token)
            ->postJson('/api/storefront/v1/wallet/bindings/managed/import', [
                'binding_key' => 'solana',
                'address' => $address,
                'secret' => base64_encode($secretKey),
                'secret_format' => 'solana_secret_key_base64',
            ])
            ->assertCreated()
            ->assertJsonPath('binding.binding_key', 'solana')
            ->assertJsonPath('binding.binding_value', $address)
            ->assertJsonPath('binding.binding_source', IdentityBinding::SOURCE_MANAGED)
            ->json('binding');

        $this->assertDatabaseHas('vault_managed_wallet_keys', [
            'identity_binding_id' => $binding['id'],
            'network_key' => 'solana',
            'address_normalized' => $address,
        ]);
    }

    #[Test]
    public function managed_import_accepts_client_derived_ton_secret(): void
    {
        if (! function_exists('sodium_crypto_sign_keypair')) {
            $this->markTestSkipped('TON managed wallet import requires the sodium extension.');
        }

        $words = [
            'bring', 'like', 'escape', 'health', 'chimney', 'pear',
            'whale', 'peasant', 'drum', 'beach', 'mass', 'garden',
            'riot', 'alien', 'possible', 'bus', 'shove', 'unable',
            'jar', 'anxiety', 'click', 'salon', 'canoe', 'lion',
        ];

        $keyPair = \Olifanton\Mnemonic\TonMnemonic::mnemonicToKeyPair($words);
        $wallet = new \Olifanton\Ton\Contracts\Wallets\V4\WalletV4R2(
            new \Olifanton\Ton\Contracts\Wallets\V4\WalletV4Options(publicKey: $keyPair->publicKey),
        );
        $address = $wallet->getAddress()->asWallet();
        $secret = base64_encode(\Olifanton\Interop\Bytes::arrayToBytes($keyPair->secretKey));

        $entityAddress = 'sl1e_'.str_repeat('7', 39);
        User::factory()->create(['entity_l1_address' => $entityAddress]);
        $token = $this->vaultToken($entityAddress);

        $binding = $this->withToken($token)
            ->postJson('/api/storefront/v1/wallet/bindings/managed/import', [
                'binding_key' => 'ton',
                'address' => $address,
                'secret' => $secret,
                'secret_format' => 'ton_secret_key_base64',
            ])
            ->assertCreated()
            ->assertJsonPath('binding.binding_key', 'ton')
            ->assertJsonPath('binding.binding_source', IdentityBinding::SOURCE_MANAGED)
            ->json('binding');

        $this->assertDatabaseHas('vault_managed_wallet_keys', [
            'identity_binding_id' => $binding['id'],
            'network_key' => 'ton',
        ]);
    }

    private function vaultToken(string $entityAddress): string
    {
        return app(StorefrontTokenService::class)->issue([
            'entity_l1_address' => $entityAddress,
            'proof_token_hash' => hash('sha256', $entityAddress),
        ], ['storefront:read', 'storefront:vault'])['access_token'];
    }
}
