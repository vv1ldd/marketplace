<?php

namespace Tests\Feature;

use App\Models\BindingEvent;
use App\Models\IdentityBinding;
use App\Models\User;
use App\Models\VaultIdentity;
use App\Services\StorefrontTokenService;
use App\Support\EvmPersonalSignVerifier;
use Elliptic\EC;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontWalletBindingEventsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withCommerceCryptoRailsEnabled();
    }

    private const TEST_PRIVATE_KEY = 'ac0974bec39a17e36ba4a6b4d5bf038c971d058074a521d8f985e51f0e0b08161b63';

    private const TEST_WALLET_ADDRESS = '0x9926a054657433dc4181886c9877ba2c96001b0a';

    public function test_successful_verify_emits_wallet_bound_event(): void
    {
        $entityAddress = 'sl1e_'.str_repeat('f', 39);
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

        $bindingId = $this->withToken($token)
            ->postJson('/api/storefront/v1/wallet/bindings/verify', [
                'nonce' => $challenge['nonce'],
                'signature' => $signature,
            ])
            ->assertOk()
            ->json('binding.id');

        $vaultId = VaultIdentity::query()->where('anchor_address', $entityAddress)->value('id');

        $this->assertDatabaseHas('binding_events', [
            'vault_id' => $vaultId,
            'identity_binding_id' => $bindingId,
            'binding_type' => IdentityBinding::TYPE_WALLET,
            'binding_key' => 'polygon',
            'binding_value_normalized' => strtolower(self::TEST_WALLET_ADDRESS),
            'event_type' => BindingEvent::TYPE_WALLET_BOUND,
            'verification_method' => IdentityBinding::METHOD_SIGNATURE,
        ]);

        $event = BindingEvent::query()->where('event_type', BindingEvent::TYPE_WALLET_BOUND)->first();
        $this->assertSame($challenge['id'] ?? null, data_get($event?->payload, 'challenge_id'));
        $this->assertSame($challenge['nonce'], data_get($event?->payload, 'nonce'));
        $this->assertStringNotContainsString('polygon wallet', strtolower(json_encode($event?->payload ?? [])));
    }

    public function test_failed_verify_emits_wallet_binding_failed_event(): void
    {
        $entityAddress = 'sl1e_'.str_repeat('a', 39);
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

        $this->withToken($token)
            ->postJson('/api/storefront/v1/wallet/bindings/verify', [
                'nonce' => $challenge['nonce'],
                'signature' => '0x'.str_repeat('a', 130),
            ])
            ->assertUnprocessable();

        $vaultId = VaultIdentity::query()->where('anchor_address', $entityAddress)->value('id');

        $this->assertDatabaseHas('binding_events', [
            'vault_id' => $vaultId,
            'identity_binding_id' => null,
            'binding_type' => IdentityBinding::TYPE_WALLET,
            'binding_key' => 'polygon',
            'event_type' => BindingEvent::TYPE_WALLET_BINDING_FAILED,
            'verification_method' => IdentityBinding::METHOD_SIGNATURE,
        ]);

        $event = BindingEvent::query()->where('event_type', BindingEvent::TYPE_WALLET_BINDING_FAILED)->first();
        $this->assertSame('signature_mismatch', data_get($event?->payload, 'error.code'));
        $this->assertSame($challenge['nonce'], data_get($event?->payload, 'nonce'));
        $this->assertSame(1, data_get($event?->payload, 'verification_attempt_count'));
    }

    public function test_manual_pending_binding_does_not_emit_wallet_bound_event(): void
    {
        $entityAddress = 'sl1e_'.str_repeat('b', 39);
        User::factory()->create(['entity_l1_address' => $entityAddress]);
        $token = $this->vaultToken($entityAddress);

        $this->withToken($token)
            ->postJson('/api/storefront/v1/wallet/bindings', [
                'binding_type' => 'wallet',
                'binding_key' => 'polygon',
                'binding_value' => '0xabcdefabcdefabcdefabcdefabcdefabcdefabcd',
                'verification_method' => 'manual',
            ])
            ->assertCreated();

        $this->assertDatabaseCount('binding_events', 0);
    }

    public function test_revoke_emits_wallet_revoked_event(): void
    {
        $entityAddress = 'sl1e_'.str_repeat('c', 39);
        User::factory()->create(['entity_l1_address' => $entityAddress]);
        $token = $this->vaultToken($entityAddress);
        $address = '0xabcdefabcdefabcdefabcdefabcdefabcdefabcd';

        $bindingId = $this->withToken($token)
            ->postJson('/api/storefront/v1/wallet/bindings', [
                'binding_type' => 'wallet',
                'binding_key' => 'polygon',
                'binding_value' => $address,
                'verification_method' => 'manual',
            ])
            ->assertCreated()
            ->json('binding.id');

        $this->withToken($token)
            ->deleteJson('/api/storefront/v1/wallet/bindings/'.$bindingId)
            ->assertOk();

        $vaultId = VaultIdentity::query()->where('anchor_address', $entityAddress)->value('id');

        $this->assertDatabaseHas('binding_events', [
            'vault_id' => $vaultId,
            'identity_binding_id' => $bindingId,
            'binding_type' => IdentityBinding::TYPE_WALLET,
            'binding_key' => 'polygon',
            'binding_value_normalized' => strtolower($address),
            'event_type' => BindingEvent::TYPE_WALLET_REVOKED,
            'verification_method' => IdentityBinding::METHOD_MANUAL,
        ]);

        $event = BindingEvent::query()->where('event_type', BindingEvent::TYPE_WALLET_REVOKED)->first();
        $this->assertSame(IdentityBinding::STATE_PENDING, data_get($event?->payload, 'previous_verification_state'));
    }

    public function test_idempotent_revoke_does_not_emit_duplicate_wallet_revoked_event(): void
    {
        $entityAddress = 'sl1e_'.str_repeat('d', 39);
        User::factory()->create(['entity_l1_address' => $entityAddress]);
        $token = $this->vaultToken($entityAddress);

        $bindingId = $this->withToken($token)
            ->postJson('/api/storefront/v1/wallet/bindings', [
                'binding_type' => 'wallet',
                'binding_key' => 'polygon',
                'binding_value' => '0xabcdefabcdefabcdefabcdefabcdefabcdefabcd',
                'verification_method' => 'manual',
            ])
            ->assertCreated()
            ->json('binding.id');

        $this->withToken($token)->deleteJson('/api/storefront/v1/wallet/bindings/'.$bindingId)->assertOk();
        $this->withToken($token)->deleteJson('/api/storefront/v1/wallet/bindings/'.$bindingId)->assertOk();

        $this->assertSame(1, BindingEvent::query()->where('event_type', BindingEvent::TYPE_WALLET_REVOKED)->count());
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
