<?php

namespace Tests\Feature;

use App\Models\IdentityBinding;
use App\Models\SettlementAuditEvent;
use App\Models\User;
use App\Models\VaultIdentity;
use App\Services\SettlementAdapterRegistry;
use App\Services\SettlementAuditEventTypes;
use App\Support\SettlementAdapterHealthCodes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SettlementAdapterHealthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withCommerceCryptoRailsEnabled();
        $this->withSettlementAdapterEnabled();

        config([
            'blockchain_networks.networks.polygon.rpc_url' => 'https://polygon-rpc.health.test',
            'blockchain_networks.networks.polygon.rpc_enabled' => true,
        ]);
    }

    #[Test]
    public function health_check_passes_when_rpc_reachable_and_chain_id_matches(): void
    {
        Http::fake([
            'https://polygon-rpc.health.test' => Http::response([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => '0x89',
            ]),
        ]);

        $health = app(SettlementAdapterRegistry::class)->healthCheck('polygon');

        $this->assertNotNull($health);
        $this->assertSame(SettlementAdapterHealthCodes::PASS, $health['status']);
        $this->assertTrue($health['healthy']);
        $this->assertTrue($health['checks']['adapter_registered']);
        $this->assertTrue($health['checks']['rpc_reachable']);
        $this->assertTrue($health['checks']['chain_id_matches']);
        $this->assertSame([], $health['failures']);
    }

    #[Test]
    public function health_check_reports_rpc_error_when_chain_unreachable(): void
    {
        Http::fake([
            'https://polygon-rpc.health.test' => Http::response('', 503),
        ]);

        $health = app(SettlementAdapterRegistry::class)->healthCheck('polygon');

        $this->assertSame(SettlementAdapterHealthCodes::FAIL, $health['status']);
        $this->assertFalse($health['healthy']);
        $this->assertContains(SettlementAdapterHealthCodes::RPC_ERROR, $health['failures']);
    }

    #[Test]
    public function rpc_failure_is_not_reported_as_zero_balance(): void
    {
        Http::fake([
            'https://polygon-rpc.health.test' => Http::response('', 503),
        ]);

        [$vault, $binding, $token] = $this->boundPolygonContext();

        $observation = app(SettlementAdapterRegistry::class)
            ->adapter('polygon')
            ->observeBalance($vault, $binding);

        $this->assertFalse($observation['observed']);
        $this->assertSame(SettlementAdapterHealthCodes::RPC_ERROR, $observation['reason']);

        $assets = $this->withToken($token)->getJson('/api/storefront/v1/wallet/assets');
        $assets->assertOk()
            ->assertJsonPath('network_wallets.0.coins.2.status', 'balance_unavailable')
            ->assertJsonMissingPath('network_wallets.0.coins.2.display_amount');

        $this->assertSame(0, SettlementAuditEvent::query()->count());
    }

    #[Test]
    public function zero_on_chain_balance_is_live_observation_not_unavailable(): void
    {
        Http::fake(function ($request) {
            if (! str_contains($request->url(), 'polygon-rpc.health.test')) {
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

            return Http::response([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => '0x0',
            ]);
        });

        [$vault, $binding] = $this->boundPolygonContext();

        $observation = app(SettlementAdapterRegistry::class)
            ->adapter('polygon')
            ->observeBalance($vault, $binding);

        $this->assertTrue($observation['observed']);
        $this->assertSame('live', $observation['observation_state']);

        $usdc = collect($observation['coins'])->firstWhere('symbol', 'USDC');
        $this->assertNotNull($usdc);
        $this->assertSame('live', $usdc['status']);
        $this->assertSame('0.000000', $usdc['amount']);
        $this->assertSame('0 USDC', $usdc['display_amount']);
    }

    #[Test]
    public function health_check_reports_stale_observation_when_last_balance_read_is_old(): void
    {
        Http::fake([
            'https://polygon-rpc.health.test' => Http::response([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => '0x89',
            ]),
        ]);

        [$vault] = $this->boundPolygonContext();

        SettlementAuditEvent::query()->create([
            'vault_id' => $vault->id,
            'identity_id' => $vault->anchor_address,
            'adapter_key' => 'polygon',
            'event_type' => SettlementAuditEventTypes::BALANCE_READ,
            'payload' => [
                'identity_id' => $vault->anchor_address,
                'source' => 'polygon',
                'timestamp' => now()->subDays(2)->toJSON(),
            ],
            'occurred_at' => now()->subDays(2),
        ]);

        config(['settlement_adapters.polygon.stale_observation_hours' => 24]);

        $health = app(SettlementAdapterRegistry::class)->healthCheck('polygon');

        $this->assertContains(SettlementAdapterHealthCodes::STALE_OBSERVATION, $health['failures']);
    }

    /**
     * @return array{0: VaultIdentity, 1: IdentityBinding, 2: string}
     */
    private function boundPolygonContext(): array
    {
        $entityAddress = 'sl1e_'.str_repeat('c', 39);
        User::factory()->create(['entity_l1_address' => $entityAddress]);
        $token = app(\App\Services\StorefrontTokenService::class)->issue([
            'entity_l1_address' => $entityAddress,
            'proof_token_hash' => hash('sha256', $entityAddress),
        ], ['storefront:read', 'storefront:vault'])['access_token'];
        $this->withToken($token)->getJson('/api/storefront/v1/wallet')->assertOk();

        $vault = VaultIdentity::query()->where('anchor_address', strtolower($entityAddress))->firstOrFail();
        $walletAddress = '0xabcdefabcdefabcdefabcdefabcdefabcdefabcd';

        $binding = IdentityBinding::query()->create([
            'vault_id' => $vault->id,
            'binding_type' => IdentityBinding::TYPE_WALLET,
            'binding_key' => 'polygon',
            'binding_value_original' => $walletAddress,
            'binding_value_normalized' => strtolower($walletAddress),
            'verification_state' => IdentityBinding::STATE_VERIFIED,
            'verification_method' => IdentityBinding::METHOD_SIGNATURE,
            'metadata' => [
                'network_label' => 'Polygon',
                'protocol' => 'evm',
            ],
            'bound_at' => now(),
            'verified_at' => now(),
        ]);

        return [$vault, $binding, $token];
    }
}
