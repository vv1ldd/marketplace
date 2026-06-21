<?php

namespace Tests\Feature;

use App\Contracts\SettlementAdapter;
use App\Models\IdentityBinding;
use App\Models\SettlementAuditEvent;
use App\Models\User;
use App\Models\VaultIdentity;
use App\Services\SettlementAdapterRegistry;
use App\Services\SettlementAuditEventTypes;
use App\Services\SettlementNetworkResolver;
use App\Services\StorefrontTokenService;
use App\Support\SettlementAdapterConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SettlementAdapterBoundaryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function polygon_adapter_defaults_to_disabled_read_only_boundary(): void
    {
        $this->assertFalse(SettlementAdapterConfig::isEnabled('polygon'));
        $this->assertSame('read_only', SettlementAdapterConfig::mode('polygon'));
        $this->assertFalse(SettlementAdapterConfig::allowsWrite('polygon'));
    }

    #[Test]
    public function polygon_stays_coming_soon_until_settlement_adapter_is_enabled(): void
    {
        $this->withCommerceCryptoRailsEnabled();

        $polygon = app(SettlementNetworkResolver::class)->resolve('polygon');

        $this->assertFalse($polygon->enabled);
        $this->assertSame('coming_soon', $polygon->status);
    }

    #[Test]
    public function enabled_read_only_adapter_exposes_observation_without_network_rollout_flag(): void
    {
        $this->withCommerceCryptoRailsEnabled();
        $this->withSettlementAdapterEnabled();

        $polygon = app(SettlementNetworkResolver::class)->resolve('polygon');

        $this->assertTrue($polygon->enabled);
        $this->assertSame('read_only', $polygon->status);
        $this->assertFalse((bool) config('blockchain_networks.networks.polygon.enabled'));
        $this->assertFalse(SettlementAdapterConfig::allowsWrite('polygon'));
    }

    #[Test]
    public function polygon_settlement_adapter_is_registered_and_implements_contract(): void
    {
        $adapter = app(SettlementAdapterRegistry::class)->adapter('polygon');

        $this->assertInstanceOf(SettlementAdapter::class, $adapter);
        $this->assertSame('polygon', $adapter->adapterKey());
    }

    #[Test]
    public function verified_wallet_binding_emits_attachment_created_audit_event(): void
    {
        $this->withCommerceCryptoRailsEnabled();
        $this->withSettlementAdapterEnabled();

        $entityAddress = 'sl1e_'.str_repeat('a', 39);
        User::factory()->create(['entity_l1_address' => $entityAddress]);
        $token = app(StorefrontTokenService::class)->issue([
            'entity_l1_address' => $entityAddress,
            'proof_token_hash' => hash('sha256', $entityAddress),
        ], ['storefront:read', 'storefront:vault'])['access_token'];
        $this->withToken($token)->getJson('/api/storefront/v1/wallet')->assertOk();

        $vault = VaultIdentity::query()->where('anchor_address', strtolower($entityAddress))->firstOrFail();

        app(\App\Services\WalletBindingService::class)->createVerifiedWalletBinding(
            vault: $vault,
            networkKey: 'polygon',
            address: '0x9926a054657433dc4181886c9877ba2c96001b0a',
            verificationMethod: IdentityBinding::METHOD_SIGNATURE,
        );

        $event = SettlementAuditEvent::query()
            ->where('event_type', SettlementAuditEventTypes::ATTACHMENT_CREATED)
            ->first();

        $this->assertNotNull($event);
        $this->assertSame(strtolower($entityAddress), $event->identity_id);
        $this->assertSame('polygon', $event->payload['chain']);
        $this->assertSame('0x9926a054657433dc4181886c9877ba2c96001b0a', $event->payload['address']);
    }

    #[Test]
    public function transfer_proofs_are_blocked_in_read_only_adapter_mode(): void
    {
        $this->withCommerceCryptoRailsEnabled();
        $this->withSettlementAdapterEnabled();

        $entityAddress = 'sl1e_'.str_repeat('b', 39);
        User::factory()->create(['entity_l1_address' => $entityAddress]);
        $token = app(StorefrontTokenService::class)->issue([
            'entity_l1_address' => $entityAddress,
            'proof_token_hash' => hash('sha256', $entityAddress),
        ], ['storefront:read', 'storefront:vault'])['access_token'];

        $this->withToken($token)
            ->postJson('/api/storefront/v1/wallet/proofs/usdc-transfer', [
                'binding_key' => 'polygon',
                'transaction_hash' => '0x'.str_repeat('c', 64),
                'recipient' => '0xabcdefabcdefabcdefabcdefabcdefabcdefabcd',
                'minimum_amount' => '1',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['binding_key']);
    }
}
