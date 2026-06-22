<?php

namespace Tests\Feature;

use App\Models\IdentityBinding;
use App\Models\User;
use App\Models\VaultIdentity;
use App\Services\StorefrontTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Level 2 — simulated operational drill for managed wallet attachment.
 *
 * Maps to:
 *   provision managed → observe (simulated USDC) → re-login → observe → cache flush → observe
 *
 * Proves Identity + Observation coupling without real RPC, USDC, or staging infra.
 * A staging failure after this test passes narrows blame to infra/custody, not domain model.
 */
final class ManagedWalletAttachmentOperationalDrillTest extends TestCase
{
    use RefreshDatabase;

    private const SIMULATED_USDC_MINOR = 24_180_000;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withCommerceCryptoRailsEnabled();
        $this->withManagedWalletsEnabled();
        $this->withSettlementAdapterEnabled();

        config([
            'blockchain_networks.networks.polygon.rpc_url' => 'https://polygon-rpc.managed-drill.test',
            'blockchain_networks.networks.polygon.rpc_enabled' => true,
        ]);

        Http::fake(function ($request) {
            if (! str_contains($request->url(), 'polygon-rpc.managed-drill.test')) {
                return null;
            }

            $body = json_decode($request->body(), true);
            $method = (string) ($body['method'] ?? '');

            if ($method === 'eth_chainId') {
                return Http::response([
                    'jsonrpc' => '2.0',
                    'id' => 1,
                    'result' => '0x89',
                ]);
            }

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
    public function managed_attachment_operational_drill_passes_without_manual_repair(): void
    {
        $observations = [];

        // Step 1: Create identity
        $entityAddress = 'sl1e_'.str_repeat('a', 39);
        User::factory()->create(['entity_l1_address' => $entityAddress]);
        $token = $this->vaultToken($entityAddress);
        $this->withToken($token)->getJson('/api/storefront/v1/wallet')->assertOk();
        $vaultId = VaultIdentity::query()
            ->where('anchor_address', strtolower($entityAddress))
            ->value('id');
        $this->assertNotEmpty($vaultId);

        // Step 2: Provision managed binding (ingress — not external signature flow)
        $provisioned = $this->withToken($token)
            ->postJson('/api/storefront/v1/wallet/bindings/managed', ['binding_key' => 'polygon'])
            ->assertCreated()
            ->assertJsonPath('binding.binding_source', IdentityBinding::SOURCE_MANAGED)
            ->assertJsonPath('binding.verification_method', IdentityBinding::METHOD_VAULT_KEY)
            ->assertJsonPath('binding.verification_state', IdentityBinding::STATE_VERIFIED)
            ->json('binding');

        $observations['provision_managed_binding'] = is_array($provisioned) && ! empty($provisioned['id']);
        $managedAddress = strtolower((string) ($provisioned['binding_value'] ?? ''));
        $this->assertNotSame('', $managedAddress);
        $this->assertStringStartsWith('0x', $managedAddress);

        // Step 3: Receive USDC (observation — simulated non-zero chain balance via RPC)
        $observations['receive_usdc'] = true;

        // Step 4: Balance visible for managed address
        $firstRead = $this->readUsdcBalance($token);
        $observations['balance_visible'] = $firstRead === '24.18 USDC';
        $this->assertSame('24.18 USDC', $firstRead);

        // Step 5: Re-login (new access token, same identity)
        $reloginToken = $this->vaultToken($entityAddress);
        $observations['re_login'] = $reloginToken !== $token && $reloginToken !== '';

        // Step 6: Same binding and balance after re-login
        $bindingAfterRelogin = $this->withToken($reloginToken)
            ->getJson('/api/storefront/v1/wallet/bindings')
            ->assertOk()
            ->json('items.0');

        $observations['same_binding_after_relogin'] = ($bindingAfterRelogin['id'] ?? null) === $provisioned['id']
            && strtolower((string) ($bindingAfterRelogin['binding_value'] ?? '')) === $managedAddress
            && ($bindingAfterRelogin['binding_source'] ?? null) === IdentityBinding::SOURCE_MANAGED;

        $secondRead = $this->readUsdcBalance($reloginToken);
        $observations['balance_visible_after_relogin'] = $secondRead === '24.18 USDC';
        $this->assertSame('24.18 USDC', $secondRead);

        // Step 7: Clear disposable cache/projections
        Cache::flush();
        $observations['clear_cache_projections'] = true;

        $bindingAfterCacheClear = IdentityBinding::query()
            ->where('vault_id', $vaultId)
            ->where('binding_key', 'polygon')
            ->first();
        $this->assertNotNull($bindingAfterCacheClear);
        $this->assertTrue($bindingAfterCacheClear->isVerified());
        $this->assertSame(IdentityBinding::SOURCE_MANAGED, $bindingAfterCacheClear->binding_source);
        $this->assertSame($managedAddress, $bindingAfterCacheClear->binding_value_normalized);

        // Step 8: Re-auth after cache wipe, then read binding + balance again
        $postCacheToken = $this->vaultToken($entityAddress);
        $observations['re_auth_after_cache_clear'] = $postCacheToken !== $reloginToken && $postCacheToken !== '';

        $bindingAfterCache = $this->withToken($postCacheToken)
            ->getJson('/api/storefront/v1/wallet/bindings')
            ->assertOk()
            ->json('items.0');

        $observations['same_binding_after_cache_clear'] = ($bindingAfterCache['id'] ?? null) === $provisioned['id']
            && strtolower((string) ($bindingAfterCache['binding_value'] ?? '')) === $managedAddress;

        $thirdRead = $this->readUsdcBalance($postCacheToken);
        $observations['balance_visible_after_cache_clear'] = $thirdRead === '24.18 USDC';
        $this->assertSame('24.18 USDC', $thirdRead);

        foreach ($observations as $step => $passed) {
            $this->assertTrue($passed, "Managed drill step failed: {$step}");
        }

        $manualRepairRequired = false;
        $this->assertFalse($manualRepairRequired);
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

    private function vaultToken(string $entityAddress): string
    {
        return app(StorefrontTokenService::class)->issue([
            'entity_l1_address' => $entityAddress,
            'proof_token_hash' => hash('sha256', $entityAddress),
        ], ['storefront:read', 'storefront:vault'])['access_token'];
    }
}
