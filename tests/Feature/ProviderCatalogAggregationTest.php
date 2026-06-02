<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Provider;
use App\Models\ProviderProduct;
use App\Models\WildflowCatalog;
use App\Services\Provider\EzPinCatalogPuller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProviderCatalogAggregationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.wildflow_token' => 'test-provider-token']);
    }

    public function test_unified_catalog_requires_provider_token(): void
    {
        Provider::updateOrCreate(
            ['type' => 'wildflow'],
            ['name' => 'Wildflow', 'is_active' => true],
        );

        $this->getJson('/api/v1/providers/wildflow/unified-catalog')
            ->assertUnauthorized()
            ->assertJsonPath('success', false);
    }

    public function test_embedded_provider_unified_catalog_projects_provider_products(): void
    {
        $provider = Provider::updateOrCreate(
            ['type' => 'wildflow'],
            ['name' => 'Wildflow', 'is_active' => true],
        );

        ProviderProduct::create([
            'provider_id' => $provider->id,
            'sku' => 'EZ-APPLE-10',
            'market_sku' => 'WFC-APPLE-10',
            'name' => 'Apple Gift Card US',
            'category' => 'Apple',
            'purchase_price' => 9.50,
            'retail_price' => 10.00,
            'min_price' => 10.00,
            'max_price' => 10.00,
            'currency' => 'USD',
            'image' => 'https://cdn.example.test/apple.png',
            'is_active' => true,
            'data' => [
                'provider_purchase' => ['pre_order' => true],
                'upc' => '123456789012',
            ],
        ]);

        ProviderProduct::create([
            'provider_id' => $provider->id,
            'sku' => 'EZ-INACTIVE',
            'market_sku' => 'WFC-INACTIVE',
            'name' => 'Inactive Gift Card',
            'category' => 'Archive',
            'purchase_price' => 1.00,
            'retail_price' => 2.00,
            'currency' => 'USD',
            'is_active' => false,
            'data' => [],
        ]);

        $this->withHeader('X-Auth-Token', 'test-provider-token')
            ->getJson('/api/v1/providers/wildflow/unified-catalog')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('provider.type', 'wildflow')
            ->assertJsonPath('count', 1)
            ->assertJsonPath('items.0.service_sku', 'EZ-APPLE-10')
            ->assertJsonPath('items.0.market_sku', 'WFC-APPLE-10')
            ->assertJsonPath('items.0.name', 'Apple Gift Card US')
            ->assertJsonPath('items.0.status', 'active')
            ->assertJsonPath('items.0.provider_purchase.pre_order', true)
            ->assertJsonMissingPath('items.0.raw_data');

        $this->withHeader('X-Auth-Token', 'test-provider-token')
            ->getJson('/api/v1/providers/wildflow/unified-catalog?include_inactive=1')
            ->assertOk()
            ->assertJsonPath('count', 2);

        $this->withHeader('X-Auth-Token', 'test-provider-token')
            ->getJson('/api/v1/providers/ezpin/unified-catalog?include_raw=1')
            ->assertOk()
            ->assertJsonPath('provider.type', 'wildflow')
            ->assertJsonPath('provider.requested_type', 'ezpin')
            ->assertJsonPath('items.0.raw_data.upc', '123456789012');
    }

    public function test_embedded_sync_uses_local_projection_without_external_wildflow_api(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://api.bybit.com/v5/market/tickers*' => Http::response([
                'result' => ['list' => [['lastPrice' => '90']]],
            ]),
        ]);

        Currency::updateOrCreate(
            ['code' => 'USD'],
            [
                'name' => 'US Dollar',
                'rate_to_rub' => 90,
                'manual_rate' => 90,
                'is_auto_update' => false,
            ],
        );

        $provider = Provider::updateOrCreate(
            ['type' => 'wildflow'],
            ['name' => 'Wildflow', 'is_active' => true, 'settings' => ['catalog_source' => 'embedded']],
        );

        ProviderProduct::create([
            'provider_id' => $provider->id,
            'sku' => 'EZ-STEAM-25',
            'market_sku' => 'WFC-STEAM-25',
            'name' => 'Steam Wallet US 25',
            'category' => 'Steam',
            'purchase_price' => 23.50,
            'retail_price' => 25.00,
            'min_price' => 25.00,
            'max_price' => 25.00,
            'currency' => 'USD',
            'is_active' => true,
            'data' => ['raw' => 'local-projection'],
        ]);

        $this->artisan('app:sync-catalogs', [
            'provider' => 'wildflow',
            '--embedded' => true,
            '--force' => true,
        ])->assertExitCode(0);

        $this->assertDatabaseHas('provider_products', [
            'provider_id' => $provider->id,
            'name' => 'Steam Wallet US 25',
            'is_active' => true,
        ]);

        $this->assertTrue(WildflowCatalog::query()
            ->where('provider_id', $provider->id)
            ->where('is_active', true)
            ->exists());

        Http::assertSentCount(1);
    }

    public function test_ezpin_puller_persists_catalog_and_retailer_items_into_provider_products(): void
    {
        $provider = Provider::updateOrCreate(
            ['type' => 'wildflow'],
            ['name' => 'Wildflow', 'is_active' => true],
        );

        $stats = app(EzPinCatalogPuller::class)->syncPayloadIntoProvider(
            $provider,
            [[
                'sku' => '12345',
                'name' => 'Apple Gift Card US',
                'price' => ['min' => 10, 'max' => 10],
                'currency' => ['code' => 'USD'],
                'categories' => [['name' => 'Apple']],
                'regions' => [['code' => 'US']],
                'pre_order' => true,
            ]],
            [[
                'product_code' => 'RP-APPLE-10',
                'price' => 10,
                'buying_price' => 9.25,
                'currency' => 'USD',
                'product' => [
                    'sku' => 12345,
                    'title' => 'Apple Retailer 10',
                    'categories' => [['name' => 'Apple']],
                ],
            ]],
            false,
        );

        $this->assertSame(2, $stats['total']);

        $catalogProduct = ProviderProduct::query()
            ->where('provider_id', $provider->id)
            ->where('name', 'Apple Gift Card US')
            ->firstOrFail();
        $this->assertSame('12345', $catalogProduct->sku);
        $this->assertStringStartsWith('WFC-', $catalogProduct->market_sku);
        $this->assertSame('catalog', data_get($catalogProduct->data, 'provider_purchase.purchase_mode'));
        $this->assertTrue(data_get($catalogProduct->data, 'provider_purchase.pre_order'));

        $retailerProduct = ProviderProduct::query()
            ->where('provider_id', $provider->id)
            ->where('name', 'Apple Retailer 10')
            ->firstOrFail();
        $this->assertSame('RP-APPLE-10', $retailerProduct->sku);
        $this->assertSame('retailer', data_get($retailerProduct->data, 'provider_purchase.purchase_mode'));
        $this->assertSame('12345', (string) data_get($retailerProduct->data, 'provider_purchase.catalog_sku'));
    }
}
