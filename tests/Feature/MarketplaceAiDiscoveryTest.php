<?php

namespace Tests\Feature;

use App\Models\LegalEntity;
use App\Models\Brand;
use App\Models\CatalogGroup;
use App\Models\Order\Order;
use App\Models\Product;
use App\Models\ProductSalesChannel;
use App\Models\Provider;
use App\Models\ProviderProduct;
use App\Models\Shop;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Services\MarketplaceDiscoveryService;
use App\Services\MeanlyFirstPartyStorefrontService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MarketplaceAiDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.domain' => 'localhost',
            'meanly_storefront.legal_entity.inn' => '770000099001',
            'meanly_storefront.legal_entity.name' => 'Meanly First Party LLC',
            'meanly_storefront.legal_entity.short_name' => 'Meanly',
            'meanly_storefront.shop.name' => 'Meanly Test Store',
            'meanly_storefront.shop.domain' => 'meanly.test',
            'meanly_storefront.shop.voucher_prefix' => 'MEAN',
        ]);
    }

    public function test_intent_search_matches_products_across_enabled_marketplace_sellers(): void
    {
        $storefront = app(MeanlyFirstPartyStorefrontService::class);
        $shop = $storefront->shop();

        $steam = $this->createStorefrontProduct($shop, [
            'sku' => 'MEANLY-STEAM-TR-1000',
            'name' => 'Steam Gift Card Turkey 1000 RUB',
            'vendor' => 'Steam',
            'category' => 'Gift Cards',
            'price_rub' => 100000,
        ]);
        $this->createStorefrontProduct($shop, [
            'sku' => 'MEANLY-PS-US-500',
            'name' => 'PlayStation Store Card USA',
            'vendor' => 'PlayStation',
            'category' => 'Gaming',
            'price_rub' => 50000,
        ]);
        $otherSeller = $this->createOtherSellerProduct('Other Seller Steam Turkey');
        $disabledSeller = $this->createOtherSellerProduct('Disabled Seller Steam Turkey', false);

        $intent = app(MarketplaceDiscoveryService::class)->parseIntent('хочу купить Steam Турция на 1000 рублей');
        $matches = app(MarketplaceDiscoveryService::class)->rankProducts(collect([$steam]), $intent);

        $this->assertSame('steam', $intent['platform']);
        $this->assertSame('TR', $intent['region']);
        $this->assertSame(1000.0, $intent['amount']);
        $this->assertGreaterThan(0, $matches->first()['score']);

        $this->get(route('home', ['intent' => 'хочу купить Steam Турция на 1000 рублей']))
            ->assertOk()
            ->assertSee('Search results', false)
            ->assertDontSee('Disabled Seller Steam Turkey', false);

        $this->get(route('meanly.storefront.products.show', $otherSeller->slug))
            ->assertOk()
            ->assertSee('Other Seller Steam Turkey', false);

        $this->get(route('meanly.storefront.products.show', $disabledSeller->slug))
            ->assertNotFound();
    }

    public function test_homepage_exposes_popular_frequently_bought_categories_favorites_and_recently_viewed(): void
    {
        $storefront = app(MeanlyFirstPartyStorefrontService::class);
        $shop = $storefront->shop();
        $popular = $this->createStorefrontProduct($shop, [
            'sku' => 'MEANLY-POPULAR-001',
            'name' => 'Popular Steam Card',
            'category' => 'Gift Cards',
        ]);
        $recent = $this->createStorefrontProduct($shop, [
            'sku' => 'MEANLY-RECENT-001',
            'name' => 'Recent Spotify Subscription',
            'category' => 'Subscriptions',
        ]);

        $firstOrder = $this->createOrder($shop, 'MS-DISCOVERY-1');
        $secondOrder = $this->createOrder($shop, 'MS-DISCOVERY-2');
        $this->createOrderItem($firstOrder, $popular->sku, 1);
        $this->createOrderItem($secondOrder, $popular->sku, 2);

        $this->get(route('meanly.storefront.products.show', $recent->slug))->assertOk();
        $this->postJson(route('meanly.storefront.favorites.toggle', $popular))->assertOk()
            ->assertJsonPath('favorite', true);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Popular groups', false)
            ->assertSee('Best offers now', false)
            ->assertSee('Catalog categories', false);
    }

    public function test_best_offer_ranking_is_deterministic_from_price_stock_and_seller_reliability(): void
    {
        $storefront = app(MeanlyFirstPartyStorefrontService::class);
        $shop = $storefront->shop();
        $expensive = $this->createStorefrontProduct($shop, [
            'sku' => 'MEANLY-STEAM-EXPENSIVE',
            'name' => 'Steam Turkey Gift Card 1000',
            'vendor' => 'Steam',
            'category' => 'Gift Cards',
            'price_rub' => 120000,
        ]);

        $cheapSeller = $this->createOtherSellerProduct('Cheaper Steam Turkey Gift Card 1000');
        $cheapSeller->update(['price_rub' => 90000]);
        $this->createStock($cheapSeller, 7);

        for ($i = 1; $i <= 3; $i++) {
            $order = $this->createOrder($cheapSeller->shop, 'MS-BEST-OFFER-'.$i);
            $this->createOrderItem($order, $cheapSeller->sku, 1);
        }

        $matches = app(MarketplaceDiscoveryService::class)
            ->rankProducts(collect([$expensive->fresh('shop'), $cheapSeller->fresh('shop')]), app(MarketplaceDiscoveryService::class)->parseIntent('Steam Turkey 1000'));

        $this->assertSame($cheapSeller->id, $matches->first()['product']->id);
        $this->assertContains('лучшая цена', $matches->first()['offer_badges']);
        $this->assertContains('есть в наличии', $matches->first()['offer_badges']);
        $this->assertContains('надежный продавец', $matches->first()['offer_badges']);
        $this->assertGreaterThan($matches->last()['score'], $matches->first()['score']);
    }

    public function test_yandex_market_rating_reviews_and_price_competitiveness_boost_offer_score(): void
    {
        $storefront = app(MeanlyFirstPartyStorefrontService::class);
        $shop = $storefront->shop();

        $plain = $this->createStorefrontProduct($shop, [
            'sku' => 'MEANLY-YM-PLAIN',
            'name' => 'Steam Wallet Card 50',
            'vendor' => 'Steam',
            'category' => 'Gift Cards',
            'price_rub' => 100000,
        ]);
        $marketProven = $this->createStorefrontProduct($shop, [
            'sku' => 'MEANLY-YM-PROVEN',
            'name' => 'Steam Wallet Card 50',
            'vendor' => 'Steam',
            'category' => 'Gift Cards',
            'price_rub' => 100000,
            'ym_url' => 'https://market.yandex.ru/product/steam-wallet-card',
            'price_competitiveness' => \App\Enums\Yandex\YmPriceCompetitivenessType::OPTIMAL,
            'data' => [
                'ym_raw' => [
                    'offer' => [
                        'offerId' => 'MEANLY-YM-PROVEN',
                        'rating' => 4.8,
                        'reviewsCount' => 128,
                    ],
                ],
            ],
        ]);

        $matches = app(MarketplaceDiscoveryService::class)
            ->rankProducts(collect([$plain->fresh('shop'), $marketProven->fresh('shop')]), app(MarketplaceDiscoveryService::class)->parseIntent('Steam wallet'));

        $this->assertSame($marketProven->id, $matches->first()['product']->id);
        $this->assertContains('высокий рейтинг', $matches->first()['offer_badges']);
        $this->assertContains('есть отзывы', $matches->first()['offer_badges']);
        $this->assertContains('цена хороша на Маркете', $matches->first()['offer_badges']);
        $this->assertContains('есть на Яндекс.Маркете', $matches->first()['offer_badges']);
        $this->assertSame(4.8, $matches->first()['metrics']['yandex_rating']);
        $this->assertSame(128, $matches->first()['metrics']['yandex_reviews_count']);
    }

    public function test_public_discovery_keeps_one_best_offer_per_provider_catalog_product(): void
    {
        $storefront = app(MeanlyFirstPartyStorefrontService::class);
        $shop = $storefront->shop();
        $canonicalSku = 'WFC-SAME-CATALOG-001';

        $expensiveAlias = $this->createStorefrontProduct($shop, [
            'sku' => 'YANDEX-ALIAS-EXPENSIVE',
            'name' => 'Steam Duplicate Offer',
            'vendor' => 'Steam',
            'price_rub' => 130000,
            'wildflow_catalog_sku' => $canonicalSku,
        ]);
        $cheapAlias = $this->createStorefrontProduct($shop, [
            'sku' => 'YANDEX-ALIAS-CHEAP',
            'name' => 'Steam Duplicate Offer',
            'vendor' => 'Steam',
            'price_rub' => 90000,
            'wildflow_catalog_sku' => $canonicalSku,
        ]);

        $service = app(MarketplaceDiscoveryService::class);
        $matches = $service->bestOfferMatches($service->rankProducts(
            collect([$expensiveAlias->fresh('shop'), $cheapAlias->fresh('shop')]),
            $service->parseIntent('Steam')
        ));

        $this->assertCount(1, $matches);
        $this->assertSame($cheapAlias->id, $matches->first()['product']->id);
        $this->assertContains('лучшая цена', $matches->first()['offer_badges']);
    }

    public function test_homepage_has_intentional_empty_states_when_storefront_has_no_products(): void
    {
        app(MeanlyFirstPartyStorefrontService::class)->shop();
        \App\Models\Category::create(['name' => '3D-очки', 'slug' => '3d-glasses', 'is_active' => true]);
        $group = CatalogGroup::create(['name' => 'Игры', 'slug' => 'igry', 'is_active' => true]);
        $brand = Brand::create(['name' => 'Steam', 'slug' => 'steam', 'catalog_group_id' => $group->id, 'is_active' => true]);
        $provider = Provider::create(['name' => 'Wildflow Provider', 'type' => 'wildflow-test', 'is_active' => true]);
        ProviderProduct::create([
            'provider_id' => $provider->id,
            'brand_id' => $brand->id,
            'sku' => 'WF-STEAM-001',
            'market_sku' => 'MS-STEAM-001',
            'name' => 'Steam Gift Card',
            'category' => 'Gift Cards',
            'reward_type' => 'Gift-Card',
            'purchase_price' => 10,
            'retail_price' => 15,
            'currency' => 'USD',
            'is_active' => true,
        ]);
        app(\App\Services\CanonicalProductIdentityIndexService::class)->rebuild();

        $this->get(route('home', ['intent' => 'Steam Турция']))
            ->assertOk()
            ->assertSee('No storefront results for this query', false)
            ->assertSee('Coming soon', false)
            ->assertDontSee('3D-очки', false);
    }

    private function createStorefrontProduct(Shop $shop, array $overrides = []): Product
    {
        $product = Product::create(array_merge([
            'shop_id' => $shop->id,
            'sku' => (string) Str::uuid(),
            'name' => 'Meanly Digital Product',
            'vendor' => 'Meanly',
            'price_rub' => 10000,
            'type' => 'giftcard',
            'category' => 'Digital goods',
            'is_active' => true,
        ], $overrides));

        ProductSalesChannel::create([
            'product_id' => $product->id,
            'shop_id' => $shop->id,
            'channel' => 'meanly_storefront',
            'is_enabled' => true,
        ]);

        app(\App\Services\CanonicalProductIdentityIndexService::class)->rebuild();

        return $product;
    }

    private function createOtherSellerProduct(string $name, bool $enabled = true): Product
    {
        $entity = LegalEntity::create([
            'name' => $name.' LLC',
            'short_name' => Str::limit($name, 20, ''),
            'inn' => (string) random_int(770000100000, 770000999999),
            'available_balance' => 1000,
            'reserved_balance' => 0,
            'currency' => 'RUB',
            'is_active' => true,
        ]);
        $shop = new Shop([
            'name' => $name.' Shop',
            'domain' => Str::slug($name).'.test',
            'voucher_prefix' => strtoupper(substr(Str::slug($name, ''), 0, 4)) ?: 'OTHR',
            'is_active' => true,
        ]);
        $shop->legal_entity_id = $entity->id;
        $shop->save();

        $product = Product::create([
            'shop_id' => $shop->id,
            'sku' => 'OTHER-STEAM-TR-'.Str::upper(Str::random(6)),
            'name' => $name,
            'vendor' => 'Steam',
            'price_rub' => 100000,
            'type' => 'giftcard',
            'category' => 'Gift Cards',
            'is_active' => true,
        ]);

        ProductSalesChannel::create([
            'product_id' => $product->id,
            'shop_id' => $shop->id,
            'channel' => 'meanly_storefront',
            'is_enabled' => $enabled,
        ]);

        app(\App\Services\CanonicalProductIdentityIndexService::class)->rebuild();

        return $product;
    }

    private function createOrder(Shop $shop, string $orderId): Order
    {
        return Order::create([
            'order_id' => $orderId,
            'uuid' => (string) Str::uuid(),
            'status' => 'COMPLETED',
            'shop_id' => $shop->id,
            'progress_id' => 4,
            'sales_channel' => 'meanly_storefront',
            'total_amount' => 100,
            'currency' => 'RUB',
            'info' => [],
            'client_info' => [],
        ]);
    }

    private function createOrderItem(Order $order, string $sku, int $count): void
    {
        DB::table('order_items')->insert([
            'uuid' => (string) Str::uuid(),
            'key' => Str::random(20),
            'order_id' => $order->id,
            'sku' => $sku,
            'count' => $count,
            'activate_till' => now()->addMonth()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createStock(Product $product, int $count): void
    {
        $warehouse = Warehouse::create([
            'shop_id' => $product->shop_id,
            'name' => 'Main warehouse',
            'type' => 'main',
            'is_active' => true,
            'is_main' => true,
        ]);

        WarehouseStock::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'count' => $count,
            'synced_at' => now(),
        ]);
    }
}
