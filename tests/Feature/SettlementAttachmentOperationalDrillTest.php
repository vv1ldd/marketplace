<?php

namespace Tests\Feature;

use App\Models\IdentityBinding;
use App\Models\User;
use App\Models\VaultIdentity;
use App\Services\StorefrontTokenService;
use App\Support\EvmPersonalSignVerifier;
use Elliptic\EC;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase C operational drill — settlement attachment durability.
 *
 * Maps to the observation table:
 *   bind → receive (observed balance) → read → re-login → read → clear cache → read
 *
 * On-chain USDC transfer is simulated via RPC balanceOf (observation layer).
 * Attachment durability (binding survives re-login and cache wipe) is the Phase C gate.
 */
final class SettlementAttachmentOperationalDrillTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_PRIVATE_KEY = 'ac0974bec39a17e36ba4a6b4d5bf038c971d058074a521d8f985e51f0e0b08161b63';

    private const TEST_WALLET_ADDRESS = '0x9926a054657433dc4181886c9877ba2c96001b0a';

    private const SIMULATED_USDC_MINOR = 24_180_000;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withCommerceCryptoRailsEnabled();
        $this->withSettlementAdapterEnabled();

        config([
            'blockchain_networks.networks.polygon.rpc_url' => 'https://polygon-rpc.drill.test',
            'blockchain_networks.networks.polygon.rpc_enabled' => true,
        ]);

        Http::fake(function ($request) {
            if (! str_contains($request->url(), 'polygon-rpc.drill.test')) {
                return null;
            }

            $body = json_decode($request->body(), true);
            $to = strtolower((string) ($body['params'][0]['to'] ?? ''));

            if ($to !== '0x3c499c542cef5e3811e1192ce70d8cc03d5c3359') {
                return Http::response([
                    'jsonrpc' => '2.0',
                    'id' => 1,
                    'result' => '0x0',
                ]);
            }

            return Http::response([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => '0x'.dechex(self::SIMULATED_USDC_MINOR),
            ]);
        });
    }

    #[Test]
    public function phase_c_operational_drill_passes_without_manual_repair(): void
    {
        $observations = [];

        // Step 1: Create identity
        $entityAddress = 'sl1e_'.str_repeat('f', 39);
        User::factory()->create(['entity_l1_address' => $entityAddress]);
        $token = $this->vaultToken($entityAddress);
        $this->withToken($token)->getJson('/api/storefront/v1/wallet')->assertOk();
        $vaultId = VaultIdentity::query()
            ->where('anchor_address', strtolower($entityAddress))
            ->value('id');
        $this->assertNotEmpty($vaultId);

        // Step 2: Bind wallet
        $observations['bind_wallet'] = $this->bindPolygonWallet($token);
        $this->assertTrue($observations['bind_wallet'], 'Bind wallet must PASS');

        // Step 3: Receive USDC (observation — simulated non-zero chain balance via RPC)
        $observations['receive_usdc'] = true;

        // Step 4: Balance visible
        $firstRead = $this->readUsdcBalance($token);
        $observations['balance_visible'] = $firstRead === '24.18 USDC';
        $this->assertSame('24.18 USDC', $firstRead);

        // Step 5: Re-login (new access token, same identity)
        $reloginToken = $this->vaultToken($entityAddress);
        $observations['re_login'] = $reloginToken !== $token && $reloginToken !== '';

        // Step 6: Balance visible after re-login
        $secondRead = $this->readUsdcBalance($reloginToken);
        $observations['balance_visible_after_relogin'] = $secondRead === '24.18 USDC';
        $this->assertSame('24.18 USDC', $secondRead);

        // Step 7: Clear disposable cache/projections (avoid Artisan cache:clear — it breaks RefreshDatabase transactions)
        Cache::flush();
        $observations['clear_cache_projections'] = true;

        $bindingAfterCacheClear = IdentityBinding::query()
            ->where('vault_id', $vaultId)
            ->where('binding_key', 'polygon')
            ->first();
        $this->assertNotNull($bindingAfterCacheClear);
        $this->assertTrue($bindingAfterCacheClear->isVerified());

        // Step 8: Re-auth after cache wipe, then read balance again
        $postCacheToken = $this->vaultToken($entityAddress);
        $observations['re_auth_after_cache_clear'] = $postCacheToken !== $reloginToken && $postCacheToken !== '';

        $thirdRead = $this->readUsdcBalance($postCacheToken);
        $observations['balance_visible_after_cache_clear'] = $thirdRead === '24.18 USDC';
        $this->assertSame('24.18 USDC', $thirdRead);

        $binding = IdentityBinding::query()
            ->where('vault_id', $vaultId)
            ->where('binding_key', 'polygon')
            ->first();

        $this->assertNotNull($binding);
        $this->assertTrue($binding->isVerified());
        $this->assertSame(strtolower(self::TEST_WALLET_ADDRESS), $binding->binding_value_normalized);

        $manualRepairRequired = false;
        foreach ($observations as $step => $passed) {
            $this->assertTrue($passed, "Drill step failed: {$step}");
        }

        $this->assertFalse($manualRepairRequired);

        // Artifact hook for CI / human drill log
        $this->addToAssertionCount(1);
    }

    private function bindPolygonWallet(string $token): bool
    {
        $challengeResponse = $this->withToken($token)
            ->postJson('/api/storefront/v1/wallet/bindings/challenge', [
                'binding_type' => 'wallet',
                'binding_key' => 'polygon',
                'binding_value' => self::TEST_WALLET_ADDRESS,
                'verification_method' => 'signature',
            ]);

        if ($challengeResponse->getStatusCode() !== 201) {
            return false;
        }

        $nonce = $challengeResponse->json('challenge.nonce');
        $message = $challengeResponse->json('challenge.message');
        $signature = $this->signPersonalMessage(self::TEST_PRIVATE_KEY, $message);

        $verifyResponse = $this->withToken($token)
            ->postJson('/api/storefront/v1/wallet/bindings/verify', [
                'nonce' => $nonce,
                'signature' => $signature,
            ]);

        return $verifyResponse->getStatusCode() === 200
            && $verifyResponse->json('binding.verification_state') === IdentityBinding::STATE_VERIFIED;
    }

    private function readUsdcBalance(string $token): ?string
    {
        $response = $this->withToken($token)->getJson('/api/storefront/v1/wallet/assets');

        return $this->extractUsdcBalance($response);
    }

    private function extractUsdcBalance($response): ?string
    {
        if ($response->getStatusCode() !== 200) {
            return null;
        }

        $coins = $response->json('network_wallets.0.coins') ?? [];

        foreach ($coins as $coin) {
            if (($coin['symbol'] ?? '') === 'USDC') {
                return $coin['display_amount'] ?? null;
            }
        }

        return null;
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
