<?php

namespace Tests\Feature;

use App\Models\IdentityBinding;
use App\Models\User;
use App\Models\VaultIdentity;
use App\Services\StorefrontTokenService;
use App\Support\BitcoinAddressCodec;
use App\Support\BitcoinMessageSignVerifier;
use Elliptic\EC;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontBitcoinWalletBindingChallengeTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_PRIVATE_KEY = 'ac0974bec39a17e36ba4a6b4d5bf038c971d058074a521d8f985e51f0e0b08161b63';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withCommerceCryptoRailsEnabled();
    }

    public function test_bitcoin_wallet_binding_challenge_and_verify_creates_verified_binding(): void
    {
        $address = $this->testBitcoinAddress();
        $entityAddress = 'sl1e_'.str_repeat('f', 39);
        User::factory()->create([
            'entity_l1_address' => $entityAddress,
        ]);
        $token = $this->vaultToken($entityAddress);

        $challengeResponse = $this->withToken($token)
            ->postJson('/api/storefront/v1/wallet/bindings/challenge', [
                'binding_type' => 'wallet',
                'binding_key' => 'bitcoin',
                'binding_value' => $address,
                'verification_method' => 'signature',
            ])
            ->assertCreated()
            ->assertJsonPath('challenge.binding_key', 'bitcoin')
            ->assertJsonPath('challenge.binding_value', strtolower($address));

        $nonce = $challengeResponse->json('challenge.nonce');
        $message = $challengeResponse->json('challenge.message');
        $signature = app(BitcoinMessageSignVerifier::class)->signMessage(self::TEST_PRIVATE_KEY, $message);

        $this->withToken($token)
            ->postJson('/api/storefront/v1/wallet/bindings/verify', [
                'nonce' => $nonce,
                'signature' => $signature,
            ])
            ->assertOk()
            ->assertJsonPath('binding.verification_state', IdentityBinding::STATE_VERIFIED)
            ->assertJsonPath('binding.verification_method', IdentityBinding::METHOD_SIGNATURE)
            ->assertJsonPath('binding.binding_key', 'bitcoin');

        $vaultId = VaultIdentity::query()->where('anchor_address', $entityAddress)->value('id');

        $this->assertDatabaseHas('identity_bindings', [
            'vault_id' => $vaultId,
            'binding_key' => 'bitcoin',
            'verification_state' => IdentityBinding::STATE_VERIFIED,
            'verification_method' => IdentityBinding::METHOD_SIGNATURE,
        ]);
    }

    public function test_bitcoin_message_sign_verifier_accepts_valid_signature(): void
    {
        $address = $this->testBitcoinAddress();
        $message = "Meanly test\nNonce: abc";
        $signature = app(BitcoinMessageSignVerifier::class)->signMessage(self::TEST_PRIVATE_KEY, $message);

        $this->assertTrue(
            app(BitcoinMessageSignVerifier::class)->verifyMessage($message, $signature, $address),
        );
    }

    public function test_bitcoin_bip322_runtime_verifies_legacy_and_module_is_available(): void
    {
        if (! is_file(base_path('scripts/node_modules/bip322-js/dist/Verifier.js'))) {
            $this->markTestSkipped('scripts/node_modules/bip322-js is not installed.');
        }

        $address = $this->testBitcoinAddress();
        $message = "Meanly test\nNonce: abc";
        $signature = app(BitcoinMessageSignVerifier::class)->signMessage(self::TEST_PRIVATE_KEY, $message);

        $process = new \Symfony\Component\Process\Process(['node', base_path('scripts/verify-bitcoin-message.cjs')]);
        $process->setWorkingDirectory(base_path('scripts'));
        $process->setInput(json_encode([
            'address' => $address,
            'message' => $message,
            'signature' => $signature,
        ], JSON_THROW_ON_ERROR));
        $process->run();

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
        $this->assertSame('1', trim($process->getOutput()));
    }

    private function testBitcoinAddress(): string
    {
        $ec = new EC('secp256k1');
        $key = $ec->keyFromPrivate(self::TEST_PRIVATE_KEY, 'hex');
        $publicKey = $key->getPublic(true, 'hex');
        $address = app(BitcoinAddressCodec::class)->p2wpkhAddressFromPublicKey($publicKey);

        $this->assertIsString($address);

        return $address;
    }

    private function vaultToken(string $entityAddress): string
    {
        return app(StorefrontTokenService::class)->issue([
            'entity_l1_address' => $entityAddress,
            'proof_token_hash' => hash('sha256', $entityAddress),
        ], ['storefront:read', 'storefront:vault'])['access_token'];
    }
}
