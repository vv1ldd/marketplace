<?php

namespace Tests\Feature;

use App\Contracts\SettlementAdapter;
use App\Models\IdentityBinding;
use App\Models\SettlementAuditEvent;
use App\Models\User;
use App\Models\VaultIdentity;
use App\Services\SettlementAdapterRegistry;
use App\Support\SettlementAdapterHealthCodes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class EvmSettlementAdapterTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_ADDRESS = '0x742d35cc6634c0532925a3b844bc454e4438f44e0';

    #[Test]
    public function ethereum_adapter_is_registered_and_observes_usdc_balance(): void
    {
        $this->withCommerceCryptoRailsEnabled();
        $this->withSettlementAdapterEnabled('ethereum');

        config([
            'blockchain_networks.networks.ethereum.rpc_url' => 'https://ethereum-rpc.test',
            'blockchain_networks.networks.ethereum.rpc_enabled' => true,
        ]);

        Http::fake(function ($request) {
            if ($request->url() !== 'https://ethereum-rpc.test') {
                return null;
            }

            $body = json_decode($request->body(), true);
            $method = (string) ($body['method'] ?? '');

            if ($method === 'eth_chainId') {
                return Http::response([
                    'jsonrpc' => '2.0',
                    'id' => 1,
                    'result' => '0x1',
                ]);
            }

            if ($method === 'eth_call') {
                return Http::response([
                    'jsonrpc' => '2.0',
                    'id' => 1,
                    'result' => '0x000000000000000000000000000000000000000000000000000000000000f4240',
                ]);
            }

            return null;
        });

        $adapter = app(SettlementAdapterRegistry::class)->adapter('ethereum');
        $this->assertInstanceOf(SettlementAdapter::class, $adapter);
        $this->assertSame('ethereum', $adapter->adapterKey());

        [$vault, $binding] = $this->boundEvmContext('ethereum');
        $observation = $adapter->observeBalance($vault, $binding);

        $this->assertTrue($observation['observed']);
        $this->assertSame('live', $observation['observation_state']);
        $this->assertSame('live', collect($observation['coins'])->firstWhere('symbol', 'USDC')['status']);
        $this->assertSame(1, SettlementAuditEvent::query()->count());
    }

    #[Test]
    public function base_adapter_health_check_passes_when_chain_id_matches(): void
    {
        $this->withCommerceCryptoRailsEnabled();
        $this->withSettlementAdapterEnabled('base');

        config([
            'blockchain_networks.networks.base.rpc_url' => 'https://base-rpc.test',
            'blockchain_networks.networks.base.rpc_enabled' => true,
        ]);

        Http::fake([
            'https://base-rpc.test' => Http::response([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => '0x2105',
            ]),
        ]);

        $health = app(SettlementAdapterRegistry::class)->healthCheck('base');

        $this->assertSame(SettlementAdapterHealthCodes::PASS, $health['status']);
        $this->assertTrue($health['checks']['chain_id_matches']);
    }

    /**
     * @return array{0: VaultIdentity, 1: IdentityBinding}
     */
    private function boundEvmContext(string $networkKey): array
    {
        $entityAddress = 'sl1e_'.str_repeat('a', 39);
        User::factory()->create(['entity_l1_address' => $entityAddress]);
        $token = app(\App\Services\StorefrontTokenService::class)->issue([
            'entity_l1_address' => $entityAddress,
            'proof_token_hash' => hash('sha256', $entityAddress),
        ], ['storefront:read', 'storefront:vault'])['access_token'];
        $this->withToken($token)->getJson('/api/storefront/v1/wallet')->assertOk();

        $vault = VaultIdentity::query()->where('anchor_address', strtolower($entityAddress))->firstOrFail();

        $binding = IdentityBinding::query()->create([
            'vault_id' => $vault->id,
            'binding_type' => IdentityBinding::TYPE_WALLET,
            'binding_key' => $networkKey,
            'binding_value_original' => self::TEST_ADDRESS,
            'binding_value_normalized' => strtolower(self::TEST_ADDRESS),
            'verification_state' => IdentityBinding::STATE_VERIFIED,
            'verification_method' => IdentityBinding::METHOD_MANUAL,
            'metadata' => [
                'network_label' => ucfirst($networkKey),
                'protocol' => 'evm',
            ],
            'bound_at' => now(),
            'verified_at' => now(),
        ]);

        return [$vault, $binding];
    }
}
