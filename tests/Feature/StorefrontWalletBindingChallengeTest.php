<?php

namespace Tests\Feature;

use App\Models\BindingChallenge;
use App\Models\IdentityBinding;
use App\Models\User;
use App\Models\VaultIdentity;
use App\Services\StorefrontTokenService;
use App\Support\EvmPersonalSignVerifier;
use Elliptic\EC;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontWalletBindingChallengeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withCommerceCryptoRailsEnabled();
    }

    private const TEST_PRIVATE_KEY = 'ac0974bec39a17e36ba4a6b4d5bf038c971d058074a521d8f985e51f0e0b08161b63';

    private const TEST_WALLET_ADDRESS = '0x9926a054657433dc4181886c9877ba2c96001b0a';

    public function test_wallet_binding_challenge_and_verify_creates_verified_binding(): void
    {
        $entityAddress = 'sl1e_'.str_repeat('d', 39);
        User::factory()->create([
            'entity_l1_address' => $entityAddress,
        ]);
        $token = $this->vaultToken($entityAddress);

        $challengeResponse = $this->withToken($token)
            ->postJson('/api/storefront/v1/wallet/bindings/challenge', [
                'binding_type' => 'wallet',
                'binding_key' => 'polygon',
                'binding_value' => self::TEST_WALLET_ADDRESS,
                'verification_method' => 'signature',
            ])
            ->assertCreated()
            ->assertJsonPath('challenge.binding_key', 'polygon')
            ->assertJsonPath('challenge.binding_value', self::TEST_WALLET_ADDRESS)
            ->assertJsonStructure([
                'challenge' => ['nonce', 'message', 'expires_at', 'vault_id'],
            ]);

        $nonce = $challengeResponse->json('challenge.nonce');
        $message = $challengeResponse->json('challenge.message');
        $signature = $this->signPersonalMessage(self::TEST_PRIVATE_KEY, $message);

        $this->withToken($token)
            ->postJson('/api/storefront/v1/wallet/bindings/verify', [
                'nonce' => $nonce,
                'signature' => $signature,
            ])
            ->assertOk()
            ->assertJsonPath('binding.verification_state', IdentityBinding::STATE_VERIFIED)
            ->assertJsonPath('binding.verification_method', IdentityBinding::METHOD_SIGNATURE)
            ->assertJsonPath('binding.binding_value', self::TEST_WALLET_ADDRESS)
            ->assertJsonPath('binding.metadata.signature_scheme', 'evm_personal_sign');

        $vaultId = VaultIdentity::query()->where('anchor_address', $entityAddress)->value('id');

        $this->assertDatabaseHas('identity_bindings', [
            'vault_id' => $vaultId,
            'binding_key' => 'polygon',
            'verification_state' => IdentityBinding::STATE_VERIFIED,
            'verification_method' => IdentityBinding::METHOD_SIGNATURE,
        ]);

        $this->assertDatabaseHas('binding_challenges', [
            'nonce' => $nonce,
            'vault_id' => $vaultId,
        ]);

        $challenge = BindingChallenge::query()->where('nonce', $nonce)->first();
        $this->assertNotNull($challenge?->consumed_at);
    }

    public function test_wallet_binding_verify_stores_optional_wallet_metadata(): void
    {
        $entityAddress = 'sl1e_'.str_repeat('f', 39);
        User::factory()->create([
            'entity_l1_address' => $entityAddress,
        ]);
        $token = $this->vaultToken($entityAddress);

        $challengeResponse = $this->withToken($token)
            ->postJson('/api/storefront/v1/wallet/bindings/challenge', [
                'binding_type' => 'wallet',
                'binding_key' => 'base',
                'binding_value' => self::TEST_WALLET_ADDRESS,
            ])
            ->assertCreated();

        $nonce = $challengeResponse->json('challenge.nonce');
        $message = $challengeResponse->json('challenge.message');
        $signature = $this->signPersonalMessage(self::TEST_PRIVATE_KEY, $message);

        $this->withToken($token)
            ->postJson('/api/storefront/v1/wallet/bindings/verify', [
                'nonce' => $nonce,
                'signature' => $signature,
                'wallet_provider_id' => 'io.rabby',
                'wallet_brand' => 'Rabby',
            ])
            ->assertOk()
            ->assertJsonPath('binding.metadata.signature_scheme', 'evm_personal_sign')
            ->assertJsonPath('binding.metadata.wallet_provider_id', 'io.rabby')
            ->assertJsonPath('binding.metadata.wallet_brand', 'Rabby');
    }

    public function test_wallet_binding_verify_ignores_wallet_brand_for_signature_check(): void
    {
        $entityAddress = 'sl1e_'.str_repeat('c', 39);
        User::factory()->create([
            'entity_l1_address' => $entityAddress,
        ]);
        $token = $this->vaultToken($entityAddress);

        $challengeResponse = $this->withToken($token)
            ->postJson('/api/storefront/v1/wallet/bindings/challenge', [
                'binding_type' => 'wallet',
                'binding_key' => 'polygon',
                'binding_value' => self::TEST_WALLET_ADDRESS,
            ])
            ->assertCreated();

        $nonce = $challengeResponse->json('challenge.nonce');
        $message = $challengeResponse->json('challenge.message');
        $signature = $this->signPersonalMessage(self::TEST_PRIVATE_KEY, $message);

        $this->withToken($token)
            ->postJson('/api/storefront/v1/wallet/bindings/verify', [
                'nonce' => $nonce,
                'signature' => $signature,
                'wallet_provider_id' => 'io.metamask',
                'wallet_brand' => 'MetaMask',
            ])
            ->assertOk()
            ->assertJsonPath('binding.binding_value', self::TEST_WALLET_ADDRESS)
            ->assertJsonPath('binding.metadata.wallet_provider_id', 'io.metamask');
    }

    public function test_wallet_binding_allows_same_address_on_second_evm_network(): void
    {
        $entityAddress = 'sl1e_'.str_repeat('b', 39);
        User::factory()->create([
            'entity_l1_address' => $entityAddress,
        ]);
        $token = $this->vaultToken($entityAddress);

        foreach (['polygon', 'ethereum'] as $bindingKey) {
            $challengeResponse = $this->withToken($token)
                ->postJson('/api/storefront/v1/wallet/bindings/challenge', [
                    'binding_type' => 'wallet',
                    'binding_key' => $bindingKey,
                    'binding_value' => self::TEST_WALLET_ADDRESS,
                ])
                ->assertCreated();

            $nonce = $challengeResponse->json('challenge.nonce');
            $message = $challengeResponse->json('challenge.message');
            $signature = $this->signPersonalMessage(self::TEST_PRIVATE_KEY, $message);

            $this->withToken($token)
                ->postJson('/api/storefront/v1/wallet/bindings/verify', [
                    'nonce' => $nonce,
                    'signature' => $signature,
                    'wallet_provider_id' => $bindingKey === 'polygon' ? 'io.metamask' : 'io.rabby',
                    'wallet_brand' => $bindingKey === 'polygon' ? 'MetaMask' : 'Rabby',
                ])
                ->assertOk()
                ->assertJsonPath('binding.binding_key', $bindingKey)
                ->assertJsonPath('binding.binding_value', self::TEST_WALLET_ADDRESS);
        }

        $vaultId = VaultIdentity::query()->where('anchor_address', $entityAddress)->value('id');

        $this->assertSame(2, IdentityBinding::query()
            ->where('vault_id', $vaultId)
            ->where('binding_value_normalized', strtolower(self::TEST_WALLET_ADDRESS))
            ->count());
    }

    public function test_wallet_binding_challenge_and_verify_creates_verified_ethereum_binding(): void
    {
        $entityAddress = 'sl1e_'.str_repeat('a', 39);
        User::factory()->create([
            'entity_l1_address' => $entityAddress,
        ]);
        $token = $this->vaultToken($entityAddress);

        $challengeResponse = $this->withToken($token)
            ->postJson('/api/storefront/v1/wallet/bindings/challenge', [
                'binding_type' => 'wallet',
                'binding_key' => 'ethereum',
                'binding_value' => self::TEST_WALLET_ADDRESS,
            ])
            ->assertCreated()
            ->assertJsonPath('challenge.binding_key', 'ethereum');

        $nonce = $challengeResponse->json('challenge.nonce');
        $message = $challengeResponse->json('challenge.message');
        $signature = $this->signPersonalMessage(self::TEST_PRIVATE_KEY, $message);

        $this->withToken($token)
            ->postJson('/api/storefront/v1/wallet/bindings/verify', [
                'nonce' => $nonce,
                'signature' => $signature,
            ])
            ->assertOk()
            ->assertJsonPath('binding.verification_state', IdentityBinding::STATE_VERIFIED)
            ->assertJsonPath('binding.binding_key', 'ethereum');
    }

    public function test_wallet_binding_verify_rejects_invalid_signature(): void
    {
        $entityAddress = 'sl1e_'.str_repeat('e', 39);
        User::factory()->create([
            'entity_l1_address' => $entityAddress,
        ]);
        $token = $this->vaultToken($entityAddress);

        $nonce = $this->withToken($token)
            ->postJson('/api/storefront/v1/wallet/bindings/challenge', [
                'binding_type' => 'wallet',
                'binding_key' => 'polygon',
                'binding_value' => self::TEST_WALLET_ADDRESS,
            ])
            ->assertCreated()
            ->json('challenge.nonce');

        $this->withToken($token)
            ->postJson('/api/storefront/v1/wallet/bindings/verify', [
                'nonce' => $nonce,
                'signature' => '0x'.str_repeat('a', 130),
            ])
            ->assertUnprocessable();
    }

    public function test_evm_personal_sign_verifier_recovers_signer_address(): void
    {
        $message = "Meanly test\nNonce: abc";
        $signature = $this->signPersonalMessage(self::TEST_PRIVATE_KEY, $message);
        $recovered = app(EvmPersonalSignVerifier::class)->recoverAddress($message, $signature);

        $this->assertSame(self::TEST_WALLET_ADDRESS, $recovered);
    }

    private function signPersonalMessage(string $privateKeyHex, string $message): string
    {
        $hash = app(EvmPersonalSignVerifier::class)->hashPersonalMessage($message);
        $ec = new EC('secp256k1');
        $key = $ec->keyFromPrivate($privateKeyHex, 'hex');
        $signature = $key->sign($hash, 'hex', ['canonical' => true]);
        $r = str_pad($signature->r->toString(16), 64, '0', STR_PAD_LEFT);
        $s = str_pad($signature->s->toString(16), 64, '0', STR_PAD_LEFT);
        $v = dechex($signature->recoveryParam + 27);

        return '0x'.$r.$s.$v;
    }

    private function vaultToken(string $entityAddress): string
    {
        return app(StorefrontTokenService::class)->issue([
            'entity_l1_address' => $entityAddress,
            'proof_token_hash' => hash('sha256', $entityAddress),
        ], ['storefront:read', 'storefront:vault'])['access_token'];
    }
}
