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

final class SolanaSettlementAdapterTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_ADDRESS = '7S3P4Hx6jpkrDtFWkwhL2VtB1ZQpZK7wBq';

    private const MAINNET_GENESIS = '5eykt4UsFv8P8NJdTREpY1vzqKqZKvdpKuc147dw2N9d';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withCommerceCryptoRailsEnabled();
        $this->withSettlementAdapterEnabled('solana');

        config([
            'blockchain_networks.networks.solana.rpc_url' => 'https://solana-rpc.test',
            'blockchain_networks.networks.solana.rpc_enabled' => true,
            'blockchain_networks.networks.solana.expected_genesis_hash' => self::MAINNET_GENESIS,
        ]);
    }

    #[Test]
    public function solana_adapter_is_registered_and_implements_contract(): void
    {
        $adapter = app(SettlementAdapterRegistry::class)->adapter('solana');

        $this->assertInstanceOf(SettlementAdapter::class, $adapter);
        $this->assertSame('solana', $adapter->adapterKey());
        $this->assertSame('read_only', $adapter->mode());
        $this->assertFalse($adapter->allowsWrite());
    }

    #[Test]
    public function health_check_passes_when_solana_rpc_reports_mainnet_genesis(): void
    {
        Http::fake(function ($request) {
            if ($request->url() !== 'https://solana-rpc.test') {
                return null;
            }

            $body = json_decode($request->body(), true);
            $method = (string) ($body['method'] ?? '');

            if ($method === 'getHealth') {
                return Http::response([
                    'jsonrpc' => '2.0',
                    'id' => 1,
                    'result' => 'ok',
                ]);
            }

            if ($method === 'getGenesisHash') {
                return Http::response([
                    'jsonrpc' => '2.0',
                    'id' => 1,
                    'result' => self::MAINNET_GENESIS,
                ]);
            }

            return null;
        });

        $health = app(SettlementAdapterRegistry::class)->healthCheck('solana');

        $this->assertSame(SettlementAdapterHealthCodes::PASS, $health['status']);
        $this->assertTrue($health['checks']['rpc_reachable']);
        $this->assertTrue($health['checks']['genesis_matches']);
    }

    #[Test]
    public function observe_balance_records_audit_event_for_live_chain_balance(): void
    {
        Http::fake(function ($request) {
            if ($request->url() !== 'https://solana-rpc.test') {
                return null;
            }

            $body = json_decode($request->body(), true);
            $method = (string) ($body['method'] ?? '');

            if ($method === 'getBalance') {
                return Http::response([
                    'jsonrpc' => '2.0',
                    'id' => 1,
                    'result' => [
                        'context' => ['slot' => 1],
                        'value' => 2500000000,
                    ],
                ]);
            }

            if ($method === 'getTokenAccountsByOwner') {
                return Http::response([
                    'jsonrpc' => '2.0',
                    'id' => 1,
                    'result' => ['context' => ['slot' => 1], 'value' => []],
                ]);
            }

            return null;
        });

        [$vault, $binding] = $this->boundSolanaContext();

        $observation = app(SettlementAdapterRegistry::class)
            ->adapter('solana')
            ->observeBalance($vault, $binding);

        $this->assertTrue($observation['observed']);
        $this->assertSame('live', $observation['observation_state']);
        $this->assertSame('2.5 SOL', collect($observation['coins'])->firstWhere('symbol', 'SOL')['display_amount']);
        $this->assertSame(1, SettlementAuditEvent::query()->count());
    }

    #[Test]
    public function rpc_failure_does_not_fabricate_zero_balance_or_audit_event(): void
    {
        Http::fake([
            'https://solana-rpc.test' => Http::response('', 503),
        ]);

        [$vault, $binding] = $this->boundSolanaContext();

        $observation = app(SettlementAdapterRegistry::class)
            ->adapter('solana')
            ->observeBalance($vault, $binding);

        $this->assertFalse($observation['observed']);
        $this->assertSame(SettlementAdapterHealthCodes::RPC_ERROR, $observation['reason']);
        $this->assertSame(0, SettlementAuditEvent::query()->count());
    }

    #[Test]
    public function wallet_assets_include_solana_network_when_crypto_rails_enabled(): void
    {
        $entityAddress = 'sl1e_'.str_repeat('9', 39);
        User::factory()->create(['entity_l1_address' => $entityAddress]);
        $token = app(\App\Services\StorefrontTokenService::class)->issue([
            'entity_l1_address' => $entityAddress,
            'proof_token_hash' => hash('sha256', $entityAddress),
        ], ['storefront:read', 'storefront:vault'])['access_token'];

        $this->withToken($token)
            ->getJson('/api/storefront/v1/wallet/assets')
            ->assertOk()
            ->assertJsonPath('network_wallets.4.network.key', 'solana');
    }

    /**
     * @return array{0: VaultIdentity, 1: IdentityBinding}
     */
    private function boundSolanaContext(): array
    {
        $entityAddress = 'sl1e_'.str_repeat('6', 39);
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
            'binding_key' => 'solana',
            'binding_value_original' => self::TEST_ADDRESS,
            'binding_value_normalized' => self::TEST_ADDRESS,
            'verification_state' => IdentityBinding::STATE_VERIFIED,
            'verification_method' => IdentityBinding::METHOD_MANUAL,
            'metadata' => [
                'network_label' => 'Solana',
                'protocol' => 'solana',
            ],
            'bound_at' => now(),
            'verified_at' => now(),
        ]);

        return [$vault, $binding];
    }
}
