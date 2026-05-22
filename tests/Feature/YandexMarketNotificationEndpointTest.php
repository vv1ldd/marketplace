<?php

namespace Tests\Feature;

use App\Jobs\ProcessYmNotification;
use App\Models\LegalEntity;
use App\Models\Order\YmNotification;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
