<?php

namespace Tests\Feature;

use App\Models\LegalEntity;
use App\Models\Shop;
use App\Models\User;
use App\Support\SalesChannels;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerWorkspaceMarketScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_merchant_workspace_hides_ru_sales_channels_and_modules(): void
    {
        [$user, $entity] = $this->seller('770000000111');

        $response = $this->withoutMiddleware()
            ->actingAs($user)
            ->getJson('https://meanly.one'.route('partner.workspace.summary', [], false))
            ->assertOk()
            ->assertJsonPath('market.market', 'global')
            ->assertJsonPath('capabilities.activations', false)
            ->assertJsonPath('finance_summary.currency', 'USD');

        $channelTypes = collect($response->json('sales_channels'))->pluck('type')->all();

        $this->assertSame(
            ['meanly_storefront', 'offline_store', 'woocommerce', 'whatsapp_business'],
            $channelTypes,
        );

        $this->assertNotContains('yandex_market', $channelTypes);
        $this->assertNotContains('avito', $channelTypes);

        $yandex = collect($response->json('sales_channels'))->firstWhere('type', 'yandex_market');
        $this->assertNull($yandex);

        $offline = collect($response->json('sales_channels'))->firstWhere('type', 'offline_store');
        $this->assertSame([], $offline['scoped_markets'] ?? []);

        $navigationKeys = collect($response->json('navigation'))->pluck('key')->all();

        $this->assertNotContains('activations', $navigationKeys);
    }

    public function test_ru_merchant_workspace_keeps_ru_sales_channels_and_modules(): void
    {
        [$user] = $this->seller('770000000222');

        $response = $this->withoutMiddleware()
            ->actingAs($user)
            ->getJson('https://meanly.ru'.route('partner.workspace.summary', [], false))
            ->assertOk()
            ->assertJsonPath('market.market', 'ru')
            ->assertJsonPath('capabilities.activations', true);

        $channelTypes = collect($response->json('sales_channels'))->pluck('type')->all();

        $this->assertContains('yandex_market', $channelTypes);
        $this->assertContains('avito', $channelTypes);
        $this->assertContains('whatsapp_business', $channelTypes);

        $yandex = collect($response->json('sales_channels'))->firstWhere('type', 'yandex_market');
        $this->assertSame('Яндекс Маркет', $yandex['label']);
        $this->assertSame(['ru'], $yandex['scoped_markets']);

        $navigationKeys = collect($response->json('navigation'))->pluck('key')->all();
        $this->assertContains('activations', $navigationKeys);
    }

    public function test_global_workspace_uses_english_sales_channel_labels(): void
    {
        [$user] = $this->seller('770000000333');

        $response = $this->withoutMiddleware()
            ->actingAs($user)
            ->getJson('https://meanly.one'.route('partner.workspace.summary', [], false))
            ->assertOk();

        $offline = collect($response->json('sales_channels'))->firstWhere('type', 'offline_store');
        $this->assertSame('Offline store', $offline['label']);
    }

    public function test_global_workspace_skips_inn_alert(): void
    {
        [$user] = $this->seller('');

        $response = $this->withoutMiddleware()
            ->actingAs($user)
            ->getJson('https://meanly.one'.route('partner.workspace.summary', [], false))
            ->assertOk();

        $this->assertNotContains(
            'legal_entity_inn_missing',
            collect($response->json('alerts'))->pluck('type')->all(),
        );
    }

    public function test_global_finance_workspace_loads_usd_payload(): void
    {
        [$user] = $this->seller('770000000444');

        $this->withoutMiddleware()
            ->actingAs($user)
            ->getJson('https://meanly.one'.route('partner.workspace.finance', [], false))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('balances.currency', 'USD')
            ->assertJsonPath('deposit_amount_label', 'Amount (USD)');
    }

    public function test_sales_channel_visibility_helper_respects_market_scope(): void
    {
        $this->assertTrue(SalesChannels::isChannelVisibleForMarket('yandex_market', 'ru'));
        $this->assertFalse(SalesChannels::isChannelVisibleForMarket('yandex_market', 'global'));
        $this->assertTrue(SalesChannels::isChannelVisibleForMarket('woocommerce', 'global'));
        $this->assertFalse(SalesChannels::isChannelVisibleForMarket('avito', 'global'));
    }

    /**
     * @return array{0: User, 1: LegalEntity}
     */
    private function seller(string $inn): array
    {
        $user = User::factory()->create();
        $entity = LegalEntity::create([
            'user_id' => $user->id,
            'name' => 'Meanly Seller Entity',
            'short_name' => 'Meanly Seller',
            'inn' => $inn,
            'status' => 'active',
            'is_active' => true,
            'available_balance' => 1000,
            'reserved_balance' => 50,
            'balance' => 1050,
        ]);

        $shop = new Shop([
            'name' => 'Seller Shop',
            'domain' => 'meanly.ru',
            'shop_region' => 'RU',
            'is_active' => true,
        ]);
        $shop->legal_entity_id = $entity->id;
        $shop->save();

        return [$user, $entity];
    }
}
