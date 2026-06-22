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

final class BitcoinSettlementAdapterTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_ADDRESS = 'bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withCommerceCryptoRailsEnabled();
        $this->withSettlementAdapterEnabled('bitcoin');

        config([
            'blockchain_networks.networks.bitcoin.rpc_url' => 'https://bitcoin-rpc.test',
            'blockchain_networks.networks.bitcoin.rpc_enabled' => true,
            'blockchain_networks.networks.bitcoin.balance_api_url' => null,
        ]);
    }

    #[Test]
    public function bitcoin_adapter_is_registered_and_implements_contract(): void
    {
        $adapter = app(SettlementAdapterRegistry::class)->adapter('bitcoin');

        $this->assertInstanceOf(SettlementAdapter::class, $adapter);
        $this->assertSame('bitcoin', $adapter->adapterKey());
        $this->assertSame('read_only', $adapter->mode());
        $this->assertFalse($adapter->allowsWrite());
    }

    #[Test]
    public function health_check_passes_when_bitcoin_rpc_reports_main_chain(): void
    {
        Http::fake([
            'https://bitcoin-rpc.test' => Http::response([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => [
                    'chain' => 'main',
                    'blocks' => 900000,
                ],
            ]),
        ]);

        $health = app(SettlementAdapterRegistry::class)->healthCheck('bitcoin');

        $this->assertSame(SettlementAdapterHealthCodes::PASS, $health['status']);
        $this->assertTrue($health['checks']['rpc_reachable']);
        $this->assertTrue($health['checks']['chain_matches']);
    }

    #[Test]
    public function observe_balance_records_audit_event_for_live_chain_balance(): void
    {
        Http::fake(function ($request) {
            if ($request->url() !== 'https://bitcoin-rpc.test') {
                return null;
            }

            $body = json_decode($request->body(), true);
            $method = (string) ($body['method'] ?? '');

            if ($method === 'getblockchaininfo') {
                return Http::response([
                    'jsonrpc' => '2.0',
                    'id' => 1,
                    'result' => ['chain' => 'main'],
                ]);
            }

            if ($method === 'scantxoutset') {
                return Http::response([
                    'jsonrpc' => '2.0',
                    'id' => 1,
                    'result' => [
                        'success' => true,
                        'total_amount' => 0.015,
                    ],
                ]);
            }

            return null;
        });

        [$vault, $binding] = $this->boundBitcoinContext();

        $observation = app(SettlementAdapterRegistry::class)
            ->adapter('bitcoin')
            ->observeBalance($vault, $binding);

        $this->assertTrue($observation['observed']);
        $this->assertSame('live', $observation['observation_state']);
        $this->assertSame('0.015 BTC', collect($observation['coins'])->firstWhere('symbol', 'BTC')['display_amount']);
        $this->assertSame(1, SettlementAuditEvent::query()->count());
    }

    #[Test]
    public function rpc_failure_does_not_fabricate_zero_balance_or_audit_event(): void
    {
        Http::fake([
            'https://bitcoin-rpc.test' => Http::response('', 503),
        ]);

        [$vault, $binding] = $this->boundBitcoinContext();

        $observation = app(SettlementAdapterRegistry::class)
            ->adapter('bitcoin')
            ->observeBalance($vault, $binding);

        $this->assertFalse($observation['observed']);
        $this->assertSame(SettlementAdapterHealthCodes::RPC_ERROR, $observation['reason']);
        $this->assertSame(0, SettlementAuditEvent::query()->count());
    }

    #[Test]
    public function wallet_assets_include_bitcoin_network_when_crypto_rails_enabled(): void
    {
        $entityAddress = 'sl1e_'.str_repeat('8', 39);
        User::factory()->create(['entity_l1_address' => $entityAddress]);
        $token = app(\App\Services\StorefrontTokenService::class)->issue([
            'entity_l1_address' => $entityAddress,
            'proof_token_hash' => hash('sha256', $entityAddress),
        ], ['storefront:read', 'storefront:vault'])['access_token'];

        $this->withToken($token)
            ->getJson('/api/storefront/v1/wallet/assets')
            ->assertOk()
            ->assertJsonPath('network_wallets.1.network.key', 'bitcoin');
    }

    /**
     * @return array{0: VaultIdentity, 1: IdentityBinding}
     */
    private function boundBitcoinContext(): array
    {
        $entityAddress = 'sl1e_'.str_repeat('7', 39);
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
            'binding_key' => 'bitcoin',
            'binding_value_original' => self::TEST_ADDRESS,
            'binding_value_normalized' => self::TEST_ADDRESS,
            'verification_state' => IdentityBinding::STATE_VERIFIED,
            'verification_method' => IdentityBinding::METHOD_MANUAL,
            'metadata' => [
                'network_label' => 'Bitcoin',
                'protocol' => 'utxo',
            ],
            'bound_at' => now(),
            'verified_at' => now(),
        ]);

        return [$vault, $binding];
    }
}
