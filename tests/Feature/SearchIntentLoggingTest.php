<?php

namespace Tests\Feature;

use App\Models\CatalogSearchLog;
use App\Models\CanonicalProductIdentity;
use App\Models\Product;
use App\Models\ProductSalesChannel;
use App\Models\ProductInventory;
use App\Models\Order\Order;
use App\Services\CatalogSearchLogService;
use App\Services\CanonicalProductSearchProfileBuilder;
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

    public function test_storefront_live_search_does_not_log_keystrokes(): void
    {
        $this->assertEquals(0, CatalogSearchLog::count());

        $this->get('/store/search?q=Steam');

        $this->assertEquals(0, CatalogSearchLog::count());
        $this->assertNull(session()->get('last_search_log_id'));
    }

    public function test_storefront_suggest_returns_lightweight_json_without_logging(): void
    {
        $identity = CanonicalProductIdentity::create([
            'fingerprint' => hash('sha256', 'playstation-us-20-usd'),
            'identity_slug' => 'playstation-us-20-usd',
            'canonical_category' => 'gift_cards',
            'brand' => 'PlayStation',
            'product_family' => 'PlayStation',
            'face_value' => 20,
            'face_value_currency' => 'USD',
            'region' => 'US',
            'platform' => 'global',
            'confidence' => 'high',
            'signals' => [],
            'provider_candidates_count' => 1,
            'seller_offers_count' => 0,
            'last_seen_at' => now(),
        ]);
        app(CanonicalProductSearchProfileBuilder::class)->rebuild($identity);

        $this->getJson('/store/suggest?q=play')
            ->assertOk()
            ->assertJsonPath('query', 'play')
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.name', 'PlayStation 20 USD US')
            ->assertJsonPath('results.0.image', null);

        $this->assertEquals(0, CatalogSearchLog::count());
        $this->assertNull(session()->get('last_search_log_id'));
    }

    public function test_storefront_suggest_prefers_requested_brand_over_weak_token_matches(): void
    {
        $steam = CanonicalProductIdentity::create([
            'fingerprint' => hash('sha256', 'steam-bahrain-10-usd'),
            'identity_slug' => 'steam-bahrain-10-usd',
            'canonical_category' => 'gift_cards',
            'brand' => 'Steam',
            'product_family' => 'Steam Wallet',
            'face_value' => 10,
            'face_value_currency' => 'USD',
            'region' => 'Bahrain',
            'platform' => 'global',
            'confidence' => 'high',
            'signals' => [],
            'provider_candidates_count' => 1,
            'seller_offers_count' => 0,
            'last_seen_at' => now(),
        ]);
        $xbox = CanonicalProductIdentity::create([
            'fingerprint' => hash('sha256', 'xbox-us-50-usd'),
            'identity_slug' => 'xbox-us-50-usd',
            'canonical_category' => 'console_payment_cards',
            'brand' => 'Xbox',
            'product_family' => 'Xbox Gift Card',
            'face_value' => 50,
            'face_value_currency' => 'USD',
            'region' => 'US',
            'platform' => 'global',
            'confidence' => 'high',
            'signals' => [],
            'provider_candidates_count' => 1,
            'seller_offers_count' => 0,
            'last_seen_at' => now(),
        ]);

        $builder = app(CanonicalProductSearchProfileBuilder::class);
        $builder->rebuild($steam);
        $builder->rebuild($xbox);

        $this->getJson('/store/suggest?q=xbox%20usa')
            ->assertOk()
            ->assertJsonPath('results.0.brand', 'Xbox')
            ->assertJsonPath('results.0.name', 'Xbox Xbox Gift Card 50 USD US')
            ->assertJsonPath('results.0.match_label', __('runtime.suggest.brand_region'));
    }

    public function test_storefront_suggest_supports_cyrillic_brand_aliases(): void
    {
        $identity = CanonicalProductIdentity::create([
            'fingerprint' => hash('sha256', 'playstation-us-20-usd-cyrillic'),
            'identity_slug' => 'playstation-us-20-usd-cyrillic',
            'canonical_category' => 'gift_cards',
            'brand' => 'PlayStation',
            'product_family' => 'PlayStation',
            'face_value' => 20,
            'face_value_currency' => 'USD',
            'region' => 'US',
            'platform' => 'global',
            'confidence' => 'high',
            'signals' => [],
            'provider_candidates_count' => 1,
            'seller_offers_count' => 0,
            'last_seen_at' => now(),
        ]);

        app(CanonicalProductSearchProfileBuilder::class)->rebuild($identity);

        $this->getJson('/store/suggest?q='.rawurlencode('плейстейшн'))
            ->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.brand', 'PlayStation')
            ->assertJsonPath('results.0.match_label', __('runtime.suggest.alias_match'));
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

        // 3. Perform a full storefront search to trigger logging and session assignment.
        // The live-search JSON endpoint intentionally skips logging keystrokes.
        $this->get('/store?q=Attribution');

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
