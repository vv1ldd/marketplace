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

class StorefrontWalletBindingAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withCommerceCryptoRailsEnabled();
    }

    private const TEST_PRIVATE_KEY = 'ac0974bec39a17e36ba4a6b4d5bf038c971d058074a521d8f985e51f0e0b08161b63';

    private const TEST_WALLET_ADDRESS = '0x9926a054657433dc4181886c9877ba2c96001b0a';

    public function test_consumed_challenge_cannot_be_replayed(): void
    {
        $entityAddress = 'sl1e_'.str_repeat('1', 39);
        User::factory()->create(['entity_l1_address' => $entityAddress]);
        $token = $this->vaultToken($entityAddress);

        $challenge = $this->withToken($token)
            ->postJson('/api/storefront/v1/wallet/bindings/challenge', [
                'binding_type' => 'wallet',
                'binding_key' => 'polygon',
                'binding_value' => self::TEST_WALLET_ADDRESS,
            ])
            ->assertCreated()
            ->json('challenge');

        $signature = $this->signPersonalMessage(self::TEST_PRIVATE_KEY, $challenge['message']);

        $this->withToken($token)
            ->postJson('/api/storefront/v1/wallet/bindings/verify', [
                'nonce' => $challenge['nonce'],
                'signature' => $signature,
            ])
            ->assertOk();

        $stored = BindingChallenge::query()->where('nonce', $challenge['nonce'])->first();
        $this->assertNotNull($stored?->consumed_at);

        $this->withToken($token)
            ->postJson('/api/storefront/v1/wallet/bindings/verify', [
                'nonce' => $challenge['nonce'],
                'signature' => $signature,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nonce']);
    }

    public function test_vault_a_challenge_cannot_be_verified_by_vault_b(): void
    {
        $entityA = 'sl1e_'.str_repeat('2', 39);
        $entityB = 'sl1e_'.str_repeat('3', 39);
        User::factory()->create(['entity_l1_address' => $entityA]);
        User::factory()->create(['entity_l1_address' => $entityB]);

        $tokenA = $this->vaultToken($entityA);
        $tokenB = $this->vaultToken($entityB);

        $challenge = $this->withToken($tokenA)
            ->postJson('/api/storefront/v1/wallet/bindings/challenge', [
                'binding_type' => 'wallet',
                'binding_key' => 'polygon',
                'binding_value' => self::TEST_WALLET_ADDRESS,
            ])
            ->assertCreated()
            ->json('challenge');

        $signature = $this->signPersonalMessage(self::TEST_PRIVATE_KEY, $challenge['message']);

        $this->withToken($tokenB)
            ->postJson('/api/storefront/v1/wallet/bindings/verify', [
                'nonce' => $challenge['nonce'],
                'signature' => $signature,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nonce']);

        $this->assertNull(BindingChallenge::query()->where('nonce', $challenge['nonce'])->value('consumed_at'));
        $this->assertDatabaseMissing('identity_bindings', [
            'binding_key' => 'polygon',
            'verification_state' => IdentityBinding::STATE_VERIFIED,
        ]);
    }

    public function test_same_wallet_address_cannot_bind_to_two_vaults(): void
    {
        $entityA = 'sl1e_'.str_repeat('4', 39);
        $entityB = 'sl1e_'.str_repeat('5', 39);
        User::factory()->create(['entity_l1_address' => $entityA]);
        User::factory()->create(['entity_l1_address' => $entityB]);

        $tokenA = $this->vaultToken($entityA);
        $tokenB = $this->vaultToken($entityB);
        $mixedCaseAddress = '0x9926A054657433dc4181886c9877ba2C96001B0a';

        $challengeA = $this->withToken($tokenA)
            ->postJson('/api/storefront/v1/wallet/bindings/challenge', [
                'binding_type' => 'wallet',
                'binding_key' => 'polygon',
                'binding_value' => $mixedCaseAddress,
            ])
            ->assertCreated()
            ->json('challenge');

        $signature = $this->signPersonalMessage(self::TEST_PRIVATE_KEY, $challengeA['message']);

        $this->withToken($tokenA)
            ->postJson('/api/storefront/v1/wallet/bindings/verify', [
                'nonce' => $challengeA['nonce'],
                'signature' => $signature,
            ])
            ->assertOk()
            ->assertJsonPath('binding.binding_value_original', $mixedCaseAddress)
            ->assertJsonPath('binding.binding_value_normalized', self::TEST_WALLET_ADDRESS);

        $this->withToken($tokenB)
            ->postJson('/api/storefront/v1/wallet/bindings/challenge', [
                'binding_type' => 'wallet',
                'binding_key' => 'polygon',
                'binding_value' => self::TEST_WALLET_ADDRESS,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['binding_value']);

        $this->assertSame(1, IdentityBinding::query()
            ->where('binding_key', 'polygon')
            ->where('binding_value_normalized', self::TEST_WALLET_ADDRESS)
            ->whereIn('verification_state', IdentityBinding::ACTIVE_STATES)
            ->count());
    }

    public function test_evm_address_canonicalization_preserves_original_and_normalizes_lookup(): void
    {
        $entityA = 'sl1e_'.str_repeat('6', 39);
        $entityB = 'sl1e_'.str_repeat('7', 39);
        User::factory()->create(['entity_l1_address' => $entityA]);
        User::factory()->create(['entity_l1_address' => $entityB]);

        $tokenA = $this->vaultToken($entityA);
        $tokenB = $this->vaultToken($entityB);
        $mixedCaseAddress = '0x9926A054657433dc4181886c9877ba2C96001B0a';

        $this->withToken($tokenA)
            ->postJson('/api/storefront/v1/wallet/bindings', [
                'binding_type' => 'wallet',
                'binding_key' => 'polygon',
                'binding_value' => $mixedCaseAddress,
                'verification_method' => 'manual',
            ])
            ->assertCreated()
            ->assertJsonPath('binding.binding_value_original', $mixedCaseAddress)
            ->assertJsonPath('binding.binding_value_normalized', self::TEST_WALLET_ADDRESS);

        $this->withToken($tokenB)
            ->postJson('/api/storefront/v1/wallet/bindings', [
                'binding_type' => 'wallet',
                'binding_key' => 'polygon',
                'binding_value' => self::TEST_WALLET_ADDRESS,
                'verification_method' => 'manual',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['binding_value']);
    }

    public function test_expired_challenge_fails_gracefully_and_records_error(): void
    {
        $entityAddress = 'sl1e_'.str_repeat('9', 39);
        User::factory()->create(['entity_l1_address' => $entityAddress]);
        $token = $this->vaultToken($entityAddress);

        $challenge = $this->withToken($token)
            ->postJson('/api/storefront/v1/wallet/bindings/challenge', [
                'binding_type' => 'wallet',
                'binding_key' => 'polygon',
                'binding_value' => self::TEST_WALLET_ADDRESS,
            ])
            ->assertCreated()
            ->json('challenge');

        BindingChallenge::query()
            ->where('nonce', $challenge['nonce'])
            ->update(['expires_at' => now()->subMinute()]);

        $signature = $this->signPersonalMessage(self::TEST_PRIVATE_KEY, $challenge['message']);

        $this->withToken($token)
            ->postJson('/api/storefront/v1/wallet/bindings/verify', [
                'nonce' => $challenge['nonce'],
                'signature' => $signature,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nonce']);

        $stored = BindingChallenge::query()->where('nonce', $challenge['nonce'])->first();
        $this->assertSame('challenge_expired', data_get($stored?->last_verification_error, 'code'));
        $this->assertNull($stored?->consumed_at);
        $this->assertDatabaseMissing('identity_bindings', [
            'binding_key' => 'polygon',
            'verification_state' => IdentityBinding::STATE_VERIFIED,
        ]);
    }

    public function test_failed_verify_records_last_verification_error(): void
    {
        $entityAddress = 'sl1e_'.str_repeat('8', 39);
        User::factory()->create(['entity_l1_address' => $entityAddress]);
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

        $challenge = BindingChallenge::query()->where('nonce', $nonce)->first();
        $this->assertSame(1, (int) $challenge?->verification_attempt_count);
        $this->assertSame('signature_mismatch', data_get($challenge?->last_verification_error, 'code'));
        $this->assertNull($challenge?->consumed_at);
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
