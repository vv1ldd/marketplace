<?php

namespace Tests\Feature;

use App\Models\IdentityBinding;
use App\Models\User;
use App\Models\VaultIdentity;
use App\Services\StorefrontTokenService;
use App\Support\TonAddressCodec;
use App\Support\TonMessageSignVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontTonWalletBindingChallengeTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_ADDRESS = 'EQDrjaLahLkMB-hMCmkzOyBuHJ139ZUYmPHu6RRBKnbdLIYI';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withCommerceCryptoRailsEnabled();
        config(['blockchain_networks.ton_connect.allowed_domains' => 'meanly.test']);
    }

    public function test_wallet_binding_challenge_and_verify_creates_verified_ton_binding(): void
    {
        if (! function_exists('sodium_crypto_sign_keypair')) {
            $this->markTestSkipped('libsodium is required for TON wallet binding tests.');
        }

        $keypair = sodium_crypto_sign_keypair();
        $secretKey = sodium_crypto_sign_secretkey($keypair);
        $publicKey = sodium_crypto_sign_publickey($keypair);
        $publicKeyHex = bin2hex($publicKey);

        $entityAddress = 'sl1e_'.str_repeat('e', 39);
        User::factory()->create([
            'entity_l1_address' => $entityAddress,
        ]);
        $token = $this->vaultToken($entityAddress);

        $challengeResponse = $this->withToken($token)
            ->postJson('/api/storefront/v1/wallet/bindings/challenge', [
                'binding_type' => 'wallet',
                'binding_key' => 'ton',
                'binding_value' => self::TEST_ADDRESS,
                'verification_method' => 'signature',
            ])
            ->assertCreated()
            ->assertJsonPath('challenge.binding_key', 'ton');

        $nonce = $challengeResponse->json('challenge.nonce');
        $message = $challengeResponse->json('challenge.message');
        $timestamp = time();
        $domain = 'meanly.test';

        $parsedAddress = app(TonAddressCodec::class)->parse(self::TEST_ADDRESS);
        $this->assertIsArray($parsedAddress);
        $accountHash = $parsedAddress['account_id'];
        $addressRaw = $parsedAddress['workchain'].':'.bin2hex($accountHash);

        $signingMessage = "\xFF\xFF"
            .'ton-connect/sign-data/'
            .pack('N', $parsedAddress['workchain'] & 0xFFFFFFFF)
            .$accountHash
            .pack('N', strlen($domain))
            .$domain
            .pack('J', $timestamp)
            .'txt'
            .pack('N', strlen($message))
            .$message;

        $signature = base64_encode(sodium_crypto_sign_detached(
            hash('sha256', $signingMessage, true),
            $secretKey,
        ));

        $this->withToken($token)
            ->postJson('/api/storefront/v1/wallet/bindings/verify', [
                'nonce' => $nonce,
                'signature' => $signature,
                'wallet_public_key' => $publicKeyHex,
                'ton_sign_data' => [
                    'domain' => $domain,
                    'timestamp' => $timestamp,
                    'address' => $addressRaw,
                    'payload' => [
                        'type' => 'text',
                        'text' => $message,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('binding.verification_state', IdentityBinding::STATE_VERIFIED);

        $vaultId = VaultIdentity::query()->where('anchor_address', $entityAddress)->value('id');

        $this->assertDatabaseHas('identity_bindings', [
            'vault_id' => $vaultId,
            'binding_key' => 'ton',
            'verification_state' => IdentityBinding::STATE_VERIFIED,
        ]);
    }

    public function test_ton_message_sign_verifier_accepts_valid_signature(): void
    {
        if (! function_exists('sodium_crypto_sign_keypair')) {
            $this->markTestSkipped('libsodium is required for TON wallet binding tests.');
        }

        $keypair = sodium_crypto_sign_keypair();
        $secretKey = sodium_crypto_sign_secretkey($keypair);
        $publicKeyHex = bin2hex(sodium_crypto_sign_publickey($keypair));
        $message = "Meanly TON test\nNonce: abc";
        $signature = base64_encode(sodium_crypto_sign_detached($message, $secretKey));

        $this->assertTrue(app(TonMessageSignVerifier::class)->verifyMessage(
            $message,
            $signature,
            $publicKeyHex,
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
