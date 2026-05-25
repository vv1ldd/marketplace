<?php

namespace Tests\Feature;

use App\Models\CatalogSearchLog;
use App\Models\Product;
use App\Models\ProductSalesChannel;
use App\Models\ProductInventory;
use App\Models\Order\Order;
use App\Services\CatalogSearchLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchIntentLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_log_a_search_query_directly(): void
    {
        /** @var CatalogSearchLogService $service */
        $service = app(CatalogSearchLogService::class);

        $log = $service->log('Steam Turkey $50', 'storefront', 5);

        $this->assertNotNull($log);
        $this->assertDatabaseHas('catalog_search_logs', [
            'query' => 'Steam Turkey $50',
            'source' => 'storefront',
            'results_count' => 5,
        ]);

        $this->assertEquals('Steam', $log->filters['brand'] ?? null);
        $this->assertEquals('turkey', $log->filters['region'] ?? null);
        $this->assertEquals(50.0, $log->filters['face_value'] ?? null);
        $this->assertEquals('USD', $log->filters['currency'] ?? null);
    }

    public function test_it_handles_empty_query_gracefully(): void
    {
        /** @var CatalogSearchLogService $service */
        $service = app(CatalogSearchLogService::class);

        $log = $service->log('   ', 'storefront', 0);

        $this->assertNull($log);
        $this->assertEquals(0, CatalogSearchLog::count());
    }

    public function test_storefront_catalog_search_endpoint_logs_queries(): void
    {
        $this->assertEquals(0, CatalogSearchLog::count());

        $this->get('/catalog?q=Steam');

        $this->assertDatabaseHas('catalog_search_logs', [
            'query' => 'Steam',
            'source' => 'storefront',
        ]);
    }

    public function test_llm_understanding_endpoint_logs_queries(): void
    {
        $this->assertEquals(0, CatalogSearchLog::count());

        $this->get('/llms/catalog/understand?q=Steam');

        $this->assertDatabaseHas('catalog_search_logs', [
            'query' => 'Steam',
            'source' => 'llm_understanding',
        ]);
    }

    public function test_llm_retrieval_endpoint_logs_queries(): void
    {
        $this->assertEquals(0, CatalogSearchLog::count());

        $this->get('/llms/catalog/retrieve?q=Steam');

        $this->assertDatabaseHas('catalog_search_logs', [
            'query' => 'Steam',
            'source' => 'llm_retrieval',
        ]);
    }

    public function test_storefront_live_homepage_logs_queries(): void
    {
        $this->assertEquals(0, CatalogSearchLog::count());

        $this->get('/store?q=Steam');

        $this->assertDatabaseHas('catalog_search_logs', [
            'query' => 'Steam',
            'source' => 'storefront',
        ]);
    }

    public function test_storefront_live_search_logs_queries(): void
    {
        $this->assertEquals(0, CatalogSearchLog::count());

        $this->get('/store/search?q=Steam');

        $this->assertDatabaseHas('catalog_search_logs', [
            'query' => 'Steam',
            'source' => 'storefront',
        ]);
    }

    public function test_checkout_attribution_telemetry(): void
    {
        // 1. Setup config
        config([
            'app.domain' => 'localhost',
            'meanly_storefront.legal_entity.inn' => '770000099001',
            'meanly_storefront.legal_entity.name' => 'Meanly First Party LLC',
            'meanly_storefront.legal_entity.short_name' => 'Meanly',
            'meanly_storefront.shop.name' => 'Meanly Test Store',
            'meanly_storefront.shop.domain' => 'meanly.test',
            'meanly_storefront.shop.voucher_prefix' => 'MEAN',
            'meanly_storefront.shop.business_id' => '900001',
            'meanly_storefront.shop.campaign_id' => '900002',
            'meanly_storefront.shop.api_key' => 'ym-api-key',
            'meanly_storefront.shop.notification_token' => 'ym-token',
        ]);

        // 2. Setup storefront product & stock
        $storefront = app(\App\Services\MeanlyFirstPartyStorefrontService::class);
        $shop = $storefront->shop();

        $product = Product::create([
            'shop_id' => $shop->id,
            'sku' => 'TEST-ATTRIBUTION-SKU',
            'name' => 'Attribution Test Card',
            'price_rub' => 15000, // 150.00 RUB
            'type' => 'giftcard',
            'category' => 'Gift Cards',
            'is_active' => true,
        ]);

        ProductSalesChannel::create([
            'product_id' => $product->id,
            'shop_id' => $shop->id,
            'channel' => 'meanly_storefront',
            'is_enabled' => true,
        ]);

        ProductInventory::create([
            'shop_id' => $shop->id,
            'sku' => $product->sku,
            'nominal_amount' => 150,
            'nominal_currency' => 'RUB',
            'voucher' => 'ATTRIBUTION-VOUCHER-CODE',
            'is_used' => false,
            'status' => 'available',
            'expires_at' => now()->addYear(),
        ]);

        // 3. Perform a storefront search via GET request to trigger logging and session assignment
        $this->get('/store/search?q=Attribution');

        // Assert database log has been created
        $this->assertDatabaseHas('catalog_search_logs', [
            'query' => 'Attribution',
            'source' => 'storefront',
        ]);

        $log = CatalogSearchLog::where('query', 'Attribution')->firstOrFail();

        // Assert session has the last_search_log_id
        $this->assertEquals($log->id, session()->get('last_search_log_id'));

        // 4. Perform storefront checkout post request
        $response = $this->postJson(route('meanly.storefront.checkout'), [
            'product_id' => $product->id,
            'quantity' => 1,
            'email' => 'attribution-buyer@example.test',
            'name' => 'Attribution Buyer',
        ]);

        $response->assertOk();

        // 5. Assert database Order has been created with search_log_id attribute
        $this->assertDatabaseHas('orders', [
            'shop_id' => $shop->id,
            'search_log_id' => $log->id,
            'total_amount' => 150.00,
        ]);

        // 6. Assert session last_search_log_id has been forgotten/cleared
        $this->assertNull(session()->get('last_search_log_id'));
    }
}
