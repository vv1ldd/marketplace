<?php

namespace Tests\Feature;

use App\Contracts\SettlementNetworkAdapter;
use App\Services\SettlementNetworkRegistry;
use App\Services\SettlementNetworkResolver;
use App\Services\StorefrontTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettlementNetworkRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_registry_exposes_default_simple_layer_one_network(): void
    {
        $registry = app(SettlementNetworkRegistry::class);

        $this->assertSame('simple-layer-1', $registry->defaultKey());
        $this->assertSame('simple-layer-1', $registry->defaultNetwork()->key);
        $this->assertSame('sl1', $registry->defaultNetwork()->protocol);
        $this->assertTrue($registry->defaultNetwork()->isLive());
    }

    public function test_storefront_catalog_lists_visible_networks_without_conflating_markets(): void
    {
        config(['blockchain_networks.crypto_rails_enabled' => false]);

        $catalog = app(SettlementNetworkResolver::class)->storefrontCatalog();

        $this->assertSame('simple-layer-1', $catalog['default']);
        $this->assertCount(1, $catalog['items']);
        $this->assertSame('simple-layer-1', $catalog['items'][0]['key']);
        $this->assertTrue($catalog['items'][0]['enabled']);
    }

    public function test_storefront_catalog_includes_polygon_when_crypto_rails_enabled(): void
    {
        $this->withCommerceCryptoRailsEnabled();

        $catalog = app(SettlementNetworkResolver::class)->storefrontCatalog();

        $this->assertCount(6, $catalog['items']);
        $this->assertSame('polygon', $catalog['items'][1]['key']);
        $this->assertFalse($catalog['items'][1]['enabled']);
        $this->assertSame('coming_soon', $catalog['items'][1]['status']);
        $this->assertFalse($catalog['items'][1]['adapter_enabled']);
        $this->assertSame('read_only', $catalog['items'][1]['adapter_mode']);
    }

    public function test_storefront_catalog_marks_polygon_read_only_when_adapter_enabled(): void
    {
        $this->withCommerceCryptoRailsEnabled();
        $this->withSettlementAdapterEnabled();

        $catalog = app(SettlementNetworkResolver::class)->storefrontCatalog();

        $this->assertTrue($catalog['items'][1]['enabled']);
        $this->assertSame('read_only', $catalog['items'][1]['status']);
        $this->assertTrue($catalog['items'][1]['adapter_enabled']);
        $this->assertSame('read_only', $catalog['items'][1]['adapter_mode']);
    }

    public function test_adapters_are_registered_for_each_configured_network(): void
    {
        $registry = app(SettlementNetworkRegistry::class);

        foreach (['simple-layer-1', 'polygon', 'bitcoin', 'ethereum', 'base', 'solana'] as $networkKey) {
            $adapter = $registry->adapter($networkKey);
            $this->assertInstanceOf(SettlementNetworkAdapter::class, $adapter);
            $this->assertSame($networkKey, $adapter->network()->key);
        }
    }

    public function test_context_api_exposes_settlement_network_catalog(): void
    {
        config(['blockchain_networks.crypto_rails_enabled' => false]);

        $this->getJson('/api/storefront/v1/context')
            ->assertOk()
            ->assertJsonPath('settlement_networks.default', 'simple-layer-1')
            ->assertJsonPath('settlement_networks.items.0.key', 'simple-layer-1')
            ->assertJsonCount(1, 'settlement_networks.items');
    }

    public function test_context_api_includes_polygon_when_crypto_rails_enabled(): void
    {
        $this->withCommerceCryptoRailsEnabled();

        $this->getJson('/api/storefront/v1/context')
            ->assertOk()
            ->assertJsonPath('settlement_networks.items.1.key', 'polygon')
            ->assertJsonPath('settlement_networks.items.1.status', 'coming_soon');
    }

    public function test_wallet_assets_hide_evm_networks_when_crypto_rails_disabled(): void
    {
        config(['blockchain_networks.crypto_rails_enabled' => false]);

        $entityAddress = 'sl1e_settlementnet000000000000000000000001';
        $token = app(StorefrontTokenService::class)->issue([
            'entity_l1_address' => $entityAddress,
            'proof_token_hash' => hash('sha256', $entityAddress),
        ], ['storefront:read', 'storefront:vault'])['access_token'];

        $this->withToken($token)
            ->getJson('/api/storefront/v1/wallet/assets')
            ->assertOk()
            ->assertJsonPath('contract.network', 'simple-layer-1')
            ->assertJsonPath('settlement_network.key', 'simple-layer-1')
            ->assertJsonPath('network_wallets', [])
            ->assertJsonPath('bound_wallets', []);

        $this->withToken($token)
            ->getJson('/api/storefront/v1/wallet')
            ->assertOk()
            ->assertJsonPath('settlement_networks.default', 'simple-layer-1')
            ->assertJsonPath('capabilities.crypto_rails_enabled', false);
    }

    public function test_wallet_assets_include_polygon_when_crypto_rails_enabled(): void
    {
        $this->withCommerceCryptoRailsEnabled();

        $entityAddress = 'sl1e_settlementnet000000000000000000000002';
        $token = app(StorefrontTokenService::class)->issue([
            'entity_l1_address' => $entityAddress,
            'proof_token_hash' => hash('sha256', $entityAddress),
        ], ['storefront:read', 'storefront:vault'])['access_token'];

        $this->withToken($token)
            ->getJson('/api/storefront/v1/wallet/assets')
            ->assertOk()
            ->assertJsonPath('contract.network', 'simple-layer-1')
            ->assertJsonPath('settlement_network.key', 'simple-layer-1')
            ->assertJsonPath('network_wallets.0.network.key', 'polygon')
            ->assertJsonPath('bound_wallets.0.network.key', 'polygon')
            ->assertJsonPath('network_wallets.0.capabilities.next_action', 'NETWORK_COMING_SOON');
    }

    public function test_wallet_assets_use_read_only_adapter_capabilities_when_enabled(): void
    {
        $this->withCommerceCryptoRailsEnabled();
        $this->withSettlementAdapterEnabled();

        $entityAddress = 'sl1e_settlementnet000000000000000000000003';
        $token = app(StorefrontTokenService::class)->issue([
            'entity_l1_address' => $entityAddress,
            'proof_token_hash' => hash('sha256', $entityAddress),
        ], ['storefront:read', 'storefront:vault'])['access_token'];

        $this->withToken($token)
            ->getJson('/api/storefront/v1/wallet/assets')
            ->assertOk()
            ->assertJsonPath('network_wallets.0.network.status', 'read_only')
            ->assertJsonPath('network_wallets.0.capabilities.next_action', 'CONNECT_OR_VIEW_WALLET');

        $this->withToken($token)
            ->getJson('/api/storefront/v1/wallet')
            ->assertOk()
            ->assertJsonPath('settlement_networks.default', 'simple-layer-1');
    }
}
