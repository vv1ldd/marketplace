<?php

namespace Tests\Feature;

use App\Models\IdentityBinding;
use App\Models\User;
use App\Models\VaultIdentity;
use App\Services\StorefrontTokenService;
use App\Support\SolanaAddressCodec;
use App\Support\SolanaMessageSignVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontSolanaWalletBindingChallengeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withCommerceCryptoRailsEnabled();
    }

    public function test_wallet_binding_challenge_and_verify_creates_verified_solana_binding(): void
    {
        if (! function_exists('sodium_crypto_sign_keypair')) {
            $this->markTestSkipped('libsodium is required for Solana wallet binding tests.');
        }

        $keypair = sodium_crypto_sign_keypair();
        $secretKey = sodium_crypto_sign_secretkey($keypair);
        $publicKey = sodium_crypto_sign_publickey($keypair);
        $address = app(SolanaAddressCodec::class)->encodeAddress($publicKey);

        $entityAddress = 'sl1e_'.str_repeat('f', 39);
        User::factory()->create([
            'entity_l1_address' => $entityAddress,
        ]);
        $token = $this->vaultToken($entityAddress);

        $challengeResponse = $this->withToken($token)
            ->postJson('/api/storefront/v1/wallet/bindings/challenge', [
                'binding_type' => 'wallet',
                'binding_key' => 'solana',
                'binding_value' => $address,
                'verification_method' => 'signature',
            ])
            ->assertCreated()
            ->assertJsonPath('challenge.binding_key', 'solana')
            ->assertJsonPath('challenge.binding_value', $address);

        $nonce = $challengeResponse->json('challenge.nonce');
        $message = $challengeResponse->json('challenge.message');
        $signature = base64_encode(sodium_crypto_sign_detached($message, $secretKey));

        $this->withToken($token)
            ->postJson('/api/storefront/v1/wallet/bindings/verify', [
                'nonce' => $nonce,
                'signature' => $signature,
            ])
            ->assertOk()
            ->assertJsonPath('binding.verification_state', IdentityBinding::STATE_VERIFIED)
            ->assertJsonPath('binding.binding_value', $address);

        $vaultId = VaultIdentity::query()->where('anchor_address', $entityAddress)->value('id');

        $this->assertDatabaseHas('identity_bindings', [
            'vault_id' => $vaultId,
            'binding_key' => 'solana',
            'verification_state' => IdentityBinding::STATE_VERIFIED,
        ]);
    }

    public function test_solana_message_sign_verifier_accepts_valid_signature(): void
    {
        if (! function_exists('sodium_crypto_sign_keypair')) {
            $this->markTestSkipped('libsodium is required for Solana wallet binding tests.');
        }

        $keypair = sodium_crypto_sign_keypair();
        $secretKey = sodium_crypto_sign_secretkey($keypair);
        $publicKey = sodium_crypto_sign_publickey($keypair);
        $address = app(SolanaAddressCodec::class)->encodeAddress($publicKey);
        $message = "Meanly Solana test\nNonce: abc";
        $signature = base64_encode(sodium_crypto_sign_detached($message, $secretKey));

        $this->assertTrue(app(SolanaMessageSignVerifier::class)->verifyMessage($message, $signature, $address));
    }

    public function test_solana_message_sign_verifier_accepts_offchain_message(): void
    {
        if (! function_exists('sodium_crypto_sign_keypair')) {
            $this->markTestSkipped('libsodium is required for Solana wallet binding tests.');
        }

        $keypair = sodium_crypto_sign_keypair();
        $secretKey = sodium_crypto_sign_secretkey($keypair);
        $publicKey = sodium_crypto_sign_publickey($keypair);
        $address = app(SolanaAddressCodec::class)->encodeAddress($publicKey);
        $message = "Meanly Vault Binding Challenge\nNonce: abc";
        $verifier = app(SolanaMessageSignVerifier::class);
        $payload = $verifier->offchainMessage($message);
        $signature = base64_encode(sodium_crypto_sign_detached($payload, $secretKey));

        $this->assertTrue($verifier->verifyMessage($message, $signature, $address));
    }

    public function test_solana_message_sign_verifier_accepts_signed_message_payload(): void
    {
        if (! function_exists('sodium_crypto_sign_keypair')) {
            $this->markTestSkipped('libsodium is required for Solana wallet binding tests.');
        }

        $keypair = sodium_crypto_sign_keypair();
        $secretKey = sodium_crypto_sign_secretkey($keypair);
        $publicKey = sodium_crypto_sign_publickey($keypair);
        $address = app(SolanaAddressCodec::class)->encodeAddress($publicKey);
        $message = "Meanly Vault Binding Challenge\nNonce: abc";
        $verifier = app(SolanaMessageSignVerifier::class);
        $payload = $verifier->offchainMessage($message);
        $signature = base64_encode(sodium_crypto_sign_detached($payload, $secretKey));

        $this->assertTrue($verifier->verifyMessage(
            $message,
            $signature,
            $address,
            base64_encode($payload),
        ));
    }

    private function vaultToken(string $entityAddress): string
    {
        return app(StorefrontTokenService::class)->issue([
            'entity_l1_address' => $entityAddress,
            'proof_token_hash' => hash('sha256', $entityAddress),
        ], ['storefront:read', 'storefront:vault'])['access_token'];
    }
}
