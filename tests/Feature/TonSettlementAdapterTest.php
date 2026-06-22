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

final class TonSettlementAdapterTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_ADDRESS = 'EQDrjaLahLkMB-hMCmkzOyBuHJ139ZUYmPHu6RRBKnbdLIYI';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withCommerceCryptoRailsEnabled();
        $this->withSettlementAdapterEnabled('ton');

        config([
            'blockchain_networks.networks.ton.rpc_url' => 'https://tonapi.test/v2',
            'blockchain_networks.networks.ton.rpc_enabled' => true,
        ]);
    }

    #[Test]
    public function ton_adapter_is_registered_and_implements_contract(): void
    {
        $adapter = app(SettlementAdapterRegistry::class)->adapter('ton');

        $this->assertInstanceOf(SettlementAdapter::class, $adapter);
        $this->assertSame('ton', $adapter->adapterKey());
        $this->assertSame('read_only', $adapter->mode());
        $this->assertFalse($adapter->allowsWrite());
    }

    #[Test]
    public function health_check_passes_when_ton_api_is_reachable(): void
    {
        Http::fake([
            'https://tonapi.test/v2/status' => Http::response(['rest_online' => true], 200),
        ]);

        $health = app(SettlementAdapterRegistry::class)->healthCheck('ton');

        $this->assertSame(SettlementAdapterHealthCodes::PASS, $health['status']);
        $this->assertTrue($health['checks']['api_reachable']);
    }

    #[Test]
    public function observe_balance_records_audit_event_for_live_ton_balance(): void
    {
        Http::fake([
            'https://tonapi.test/v2/status' => Http::response(['rest_online' => true], 200),
            'https://tonapi.test/v2/accounts/*' => Http::response([
                'address' => self::TEST_ADDRESS,
                'balance' => 1500000000,
            ], 200),
        ]);

        [$vault, $binding] = $this->boundTonContext();

        $observation = app(SettlementAdapterRegistry::class)
            ->adapter('ton')
            ->observeBalance($vault, $binding);

        $this->assertTrue($observation['observed']);
        $this->assertSame('live', $observation['observation_state']);
        $this->assertSame('1.5 TON', collect($observation['coins'])->firstWhere('symbol', 'TON')['display_amount']);
        $this->assertSame(1, SettlementAuditEvent::query()->count());
    }

    /**
     * @return array{0: VaultIdentity, 1: IdentityBinding}
     */
    private function boundTonContext(): array
    {
        $entityAddress = 'sl1e_'.str_repeat('5', 39);
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
            'binding_key' => 'ton',
            'binding_value_original' => self::TEST_ADDRESS,
            'binding_value_normalized' => app(\App\Support\TonAddressCodec::class)->normalizeAddress(self::TEST_ADDRESS),
            'verification_state' => IdentityBinding::STATE_VERIFIED,
            'verification_method' => IdentityBinding::METHOD_MANUAL,
            'metadata' => [
                'network_label' => 'TON',
                'protocol' => 'ton',
            ],
            'bound_at' => now(),
            'verified_at' => now(),
        ]);

        return [$vault, $binding];
    }
}
