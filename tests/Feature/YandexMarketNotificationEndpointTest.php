<?php

namespace Tests\Feature;

use App\Jobs\ProcessYmNotification;
use App\Models\ApiApplication;
use App\Models\Currency;
use App\Models\LegalEntity;
use App\Models\Order\Order;
use App\Models\Order\OrderItems;
use App\Models\Order\YmNotification;
use App\Models\Product;
use App\Models\Provider;
use App\Models\Shop;
use App\Models\WildflowCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class YandexMarketNotificationEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_yandex_notifications_are_routed_per_seller_shop(): void
    {
        Queue::fake();

        $firstShop = $this->createSellerShop('Meanly Seller', 149014578, 'meanly-token');
        $secondShop = $this->createSellerShop('Other Seller', 249014579, 'other-token');

        $this->postJson('/api/ym/meanly-token/notification', [
            'notificationType' => 'ORDER_CREATED',
            'orderId' => 1001,
            'campaignId' => $firstShop->campaign_id,
        ])->assertOk();

        $this->postJson('/api/ym/other-token/notification', [
            'notificationType' => 'ORDER_CREATED',
            'orderId' => 2002,
            'campaignId' => $secondShop->campaign_id,
        ])->assertOk();

        $this->assertDatabaseHas('ym_notifications', [
            'campaign_id' => $firstShop->campaign_id,
            'order_id' => 1001,
            'type' => 'ORDER_CREATED',
        ]);
        $this->assertDatabaseHas('ym_notifications', [
            'campaign_id' => $secondShop->campaign_id,
            'order_id' => 2002,
            'type' => 'ORDER_CREATED',
        ]);

        Queue::assertPushed(ProcessYmNotification::class, 2);
        Queue::assertPushed(ProcessYmNotification::class, fn (ProcessYmNotification $job) => $this->jobData($job)['shop_id'] === $firstShop->id);
        Queue::assertPushed(ProcessYmNotification::class, fn (ProcessYmNotification $job) => $this->jobData($job)['shop_id'] === $secondShop->id);
    }

    public function test_yandex_order_notification_rejects_unknown_campaign(): void
    {
        Queue::fake();
        config(['services.ym.notification_token' => 'global-token']);

        $this->postJson('/api/ym/global-token/notification', [
            'notificationType' => 'ORDER_CREATED',
            'orderId' => 3003,
            'campaignId' => 999999,
        ])
            ->assertStatus(400)
            ->assertJsonPath('error.type', 'UNKNOWN_CAMPAIGN');

        $this->assertSame(0, YmNotification::count());
        Queue::assertNothingPushed();
    }

    public function test_yandex_market_setup_returns_per_shop_notification_url(): void
    {
        $user = \App\Models\User::factory()->create();
        $entity = LegalEntity::create([
            'user_id' => $user->id,
            'name' => 'Seller Legal Entity',
            'inn' => '770000000555',
            'is_active' => true,
        ]);

        $shop = new Shop([
            'name' => 'Seller Shop',
            'domain' => 'seller.test',
            'is_active' => true,
        ]);
        $shop->legal_entity_id = $entity->id;
        $shop->save();

        $this->withoutMiddleware()
            ->actingAs($user)
            ->postJson(route('partner.dashboard.shop.yandex_market', $shop), [
                'business_id' => 123,
                'campaign_id' => 456,
                'api_key' => 'seller-yandex-key',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('shop.campaign_id', 456)
            ->assertJson(fn ($json) => $json->whereType('shop.notification_url', 'string')->etc());

        $shop->refresh();
        $this->assertNotEmpty($shop->notification_token);
    }

    public function test_yandex_market_setup_works_for_managed_legal_entity_and_keeps_existing_api_key_when_blank(): void
    {
        $user = \App\Models\User::factory()->create();
        $entity = LegalEntity::create([
            'name' => 'Managed Seller Legal Entity',
            'inn' => '770000000556',
            'is_active' => true,
        ]);
        $user->managedLegalEntities()->attach($entity->id, ['role' => 'owner']);

        $shop = new Shop([
            'name' => 'Managed Seller Shop',
            'domain' => 'managed-seller.test',
            'is_active' => true,
            'api_key' => 'existing-yandex-key',
        ]);
        $shop->legal_entity_id = $entity->id;
        $shop->save();

        $this->withoutMiddleware()
            ->actingAs($user)
            ->postJson(route('partner.dashboard.shop.yandex_market', $shop), [
                'business_id' => 789,
                'campaign_id' => 987,
                'api_key' => null,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('shop.business_id', 789)
            ->assertJsonPath('shop.campaign_id', 987)
            ->assertJsonPath('shop.is_configured', false)
            ->assertJsonPath('shop.legal_verified', false);

        $shop->refresh();
        $this->assertSame('existing-yandex-key', $shop->api_key);
    }

    public function test_yandex_market_setup_requires_legal_entity_inn(): void
    {
        $user = \App\Models\User::factory()->create();
        $entity = LegalEntity::create([
            'user_id' => $user->id,
            'name' => 'Seller Without INN',
            'inn' => '',
            'is_active' => true,
        ]);

        $shop = new Shop([
            'name' => 'Seller Shop',
            'domain' => 'meanly.ru',
            'shop_region' => 'RU',
            'is_active' => true,
        ]);
        $shop->legal_entity_id = $entity->id;
        $shop->save();

        $this->withoutMiddleware()
            ->actingAs($user)
            ->postJson(route('partner.dashboard.shop.yandex_market', $shop), [
                'business_id' => 123,
                'campaign_id' => 456,
                'ym_warehouse_id' => 789,
                'api_key' => 'seller-yandex-key',
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('yandex_channel_status.state', 'legal_entity_required')
            ->assertJsonPath('yandex_channel_status.legal_entity.can_configure', false);

        $this->assertNull($shop->fresh()->business_id);
    }

    public function test_yandex_market_setup_response_exposes_channel_status_shape(): void
    {
        $user = \App\Models\User::factory()->create();
        $entity = LegalEntity::create([
            'user_id' => $user->id,
            'name' => 'Seller Legal Entity',
            'inn' => '770000000557',
            'is_active' => true,
        ]);

        $shop = new Shop([
            'name' => 'Seller Shop',
            'domain' => 'meanly.ru',
            'shop_region' => 'RU',
            'is_active' => true,
        ]);
        $shop->legal_entity_id = $entity->id;
        $shop->save();

        $product = Product::create([
            'shop_id' => $shop->id,
            'sku' => 'YM-STATUS-001',
            'name' => 'Yandex Status Product',
            'price_rub' => 12000,
            'type' => 'giftcard',
            'is_active' => true,
        ]);

        \App\Models\ProductSalesChannel::create([
            'shop_id' => $shop->id,
            'product_id' => $product->id,
            'channel' => 'yandex_market',
            'is_enabled' => true,
            'last_synced_at' => now(),
            'last_error' => 'Previous sync warning',
        ]);

        \App\Models\Warehouse::create([
            'shop_id' => $shop->id,
            'name' => 'Yandex Market',
            'type' => 'channel',
            'is_main' => false,
            'is_active' => true,
            'channel' => 'yandex_market',
            'channel_quota' => 75,
            'ym_id' => 789,
        ]);

        $this->withoutMiddleware()
            ->actingAs($user)
            ->postJson(route('partner.dashboard.shop.yandex_market', $shop), [
                'business_id' => 123,
                'campaign_id' => 456,
                'ym_warehouse_id' => 789,
                'api_key' => null,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('yandex_channel_status.key', 'yandex_market')
            ->assertJsonPath('yandex_channel_status.legal_entity.inn', '770000000557')
            ->assertJsonPath('yandex_channel_status.warehouse.ym_id', 789)
            ->assertJsonPath('yandex_channel_status.warehouse.channel_quota', 75)
            ->assertJsonPath('yandex_channel_status.publication.enabled_products', 1)
            ->assertJsonPath('yandex_channel_status.publication.last_error', 'Previous sync warning');
    }

    public function test_yandex_notification_reaches_ezpin_sandbox_and_activates_redeem_code(): void
    {
        config([
            'queue.default' => 'sync',
            'services.wildflow.kernel_url' => 'https://wildflow.test/api/v1',
        ]);
        Mail::fake();
        Http::fake([
            '*/partners/grant-credit' => Http::response(['success' => true, 'reservation_id' => 'YM-HOLD-1'], 200),
            '*/providers/ezpin-sandbox/order' => Http::response([
                'order' => ['referenceCode' => 'YM-EZPIN-SANDBOX-ORDER-1'],
            ], 200),
            '*/providers/ezpin-sandbox/orders/*/normalized-cards' => Http::response([
                'cards' => [
                    ['pin_code' => 'YM-EZPIN-SANDBOX-CODE-001'],
                ],
            ], 200),
        ]);

        $legalEntity = LegalEntity::create([
            'name' => 'Meanly Yandex E2E',
            'short_name' => 'Meanly',
            'inn' => '770000009777',
            'available_balance' => 1000,
            'reserved_balance' => 0,
            'currency' => 'RUB',
            'is_active' => true,
        ]);

        $shop = new Shop([
            'name' => 'Meanly YM E2E',
            'domain' => 'meanly-yandex-e2e.test',
            'voucher_prefix' => 'MEAN',
            'business_id' => 910001,
            'campaign_id' => 910002,
            'notification_token' => 'meanly-e2e-token',
            'api_key' => 'ym-api-key',
            'is_active' => true,
            'is_sandbox' => true,
        ]);
        $shop->legal_entity_id = $legalEntity->id;
        $shop->save();

        Currency::updateOrCreate(['code' => 'USD'], [
            'name' => 'US Dollar',
            'rate_to_rub' => 85.0,
            'manual_rate' => 85.0,
            'is_auto_update' => false,
        ]);

        $apiToken = 'meanly-e2e-shop-token';
        ApiApplication::create([
            'shop_id' => $shop->id,
            'type' => ApiApplication::TYPE_SHOP,
            'name' => 'Meanly E2E Redeem API',
            'token' => $apiToken,
            'is_active' => true,
        ]);

        Provider::updateOrCreate(['type' => 'wildflow'], [
            'name' => 'Wildflow Sandbox Proxy',
            'is_active' => true,
            'credentials' => ['api_key' => 'testing-token'],
            'settings' => ['upstream_provider' => 'ezpin-sandbox'],
        ]);

        $sku = 'WF-YM-E2E-001';
        WildflowCatalog::create([
            'sku' => $sku,
            'service_sku' => 'EZPIN-YM-E2E-001',
            'retail_price' => 1,
            'type' => 'giftcard',
            'is_active' => true,
            'data' => [
                'data' => [
                    'sku' => 'EZPIN-YM-E2E-001',
                    'price' => 1.00,
                ],
                'product' => [
                    'title' => 'Yandex E2E Card',
                    'currency' => ['code' => 'USD'],
                ],
            ],
        ]);

        Product::create([
            'shop_id' => $shop->id,
            'sku' => $sku,
            'wildflow_catalog_sku' => $sku,
            'name' => 'Yandex E2E Card',
            'price_rub' => 12000,
            'type' => 'giftcard',
            'is_active' => true,
        ]);

        $sourceOrderId = 9101001;
        $this->postJson('/api/ym/meanly-e2e-token/notification', [
            'notificationType' => 'ORDER_CREATED',
            'orderId' => $sourceOrderId,
            'campaignId' => $shop->campaign_id,
            'fake' => true,
            'is_manual_sync' => true,
            'order_full_info' => [
                'id' => $sourceOrderId,
                'fake' => true,
                'items' => [[
                    'id' => 881001,
                    'offerId' => $sku,
                    'count' => 1,
                    'price' => 120,
                    'buyerPrice' => 120,
                    'order_item_name' => 'Yandex E2E Card',
                ]],
                'buyerTotal' => 120,
                'currency' => 'RUR',
            ],
            'client_info' => [
                'id' => 'ym-buyer-e2e-9101001',
                'firstName' => 'Yandex',
                'lastName' => 'Client',
                'email' => 'ym-e2e-client@example.test',
                'phone' => '+79990000001',
            ],
        ])->assertOk();

        $order = Order::where('order_id', $sourceOrderId)->firstOrFail();
        $this->assertTrue($order->isYandexSandboxOrder());
        $this->assertTrue($order->shouldRedeemThroughProvider());

        $this->postJson('/api/ym/meanly-e2e-token/notification', [
            'notificationType' => 'ORDER_STATUS_UPDATED',
            'orderId' => $sourceOrderId,
            'campaignId' => $shop->campaign_id,
            'status' => 'PROCESSING',
            'substatus' => 'STARTED',
            'is_manual_sync' => true,
        ])->assertOk();

        $item = OrderItems::where('order_id', $order->id)->firstOrFail();
        $voucherCode = $item->key;
        $this->assertNotEmpty($voucherCode);

        $this->withToken($apiToken)
            ->postJson('/api/redeem/verify-code', ['code' => $voucherCode])
            ->assertOk()
            ->assertJsonPath('data.sku', $sku);

        $this->withToken($apiToken)->postJson('/api/redeem/activate', [
            'code' => $voucherCode,
            'verification_code' => 'TRUSTED_USER',
            'email' => 'ym-e2e-client@example.test',
            'first_name' => 'Yandex',
            'last_name' => 'Client',
        ])->assertOk()
            ->assertJsonPath('status', 'success');

        $item->refresh();
        $this->assertSame('success', $item->purchase_status);
        $this->assertSame('YM-EZPIN-SANDBOX-CODE-001', $item->original_code);
        $this->assertTrue($item->is_activated);
        $this->assertTrue($item->is_redeemed);
        $this->assertStringStartsWith('SL1-', $item->provider_order_id);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/providers/ezpin-sandbox/order'));
        Http::assertSent(fn ($request) => str_contains($request->url(), '/providers/ezpin-sandbox/orders/'.$item->provider_order_id.'/normalized-cards'));
    }

    private function createSellerShop(string $name, int $campaignId, string $token): Shop
    {
        $entity = LegalEntity::create([
            'name' => $name.' Legal Entity',
            'inn' => (string) random_int(1000000000, 9999999999),
            'is_active' => true,
        ]);

        $shop = new Shop([
            'name' => $name,
            'domain' => \Illuminate\Support\Str::slug($name).'.test',
            'campaign_id' => $campaignId,
            'notification_token' => $token,
            'is_active' => true,
        ]);
        $shop->legal_entity_id = $entity->id;
        $shop->save();

        return $shop;
    }

    private function jobData(ProcessYmNotification $job): array
    {
        return (fn () => $this->data)->call($job);
    }
}
