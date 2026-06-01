<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Services\MarketContextResolver;
use App\Support\SalesChannels;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketOwnedSalesChannelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_domain_controls_yandex_market_eligibility_without_request_host(): void
    {
        $ruShop = $this->shop('RU Shop', 'meanly.ru', withValidYandex: true);
        $globalShop = $this->shop('Global Shop', 'meanly.one', withValidYandex: true);

        $this->get('https://digitienda.ar/theme/consortium')->assertRedirect();
        $this->assertSame('latam_ar', market()->market);

        $this->assertSame('ru', app(MarketContextResolver::class)->resolveForShop($ruShop)->market);
        $this->assertSame('global', app(MarketContextResolver::class)->resolveForShop($globalShop)->market);

        $this->assertTrue(SalesChannels::isChannelAllowedForShop('yandex_market', $ruShop));
        $this->assertTrue(SalesChannels::isChannelConfigured('yandex_market', $ruShop));

        $this->assertFalse(SalesChannels::isChannelAllowedForShop('yandex_market', $globalShop));
        $this->assertFalse(SalesChannels::isChannelConfigured('yandex_market', $globalShop));
    }

    public function test_yandex_market_eligibility_and_shop_activation_are_separate(): void
    {
        $unconfiguredRuShop = $this->shop('RU Shop Without Yandex', 'meanly.ru');

        $this->assertTrue(SalesChannels::isChannelAllowedForShop('yandex_market', $unconfiguredRuShop));
        $this->assertFalse(SalesChannels::isChannelActivatedByShop('yandex_market', $unconfiguredRuShop));
        $this->assertFalse(SalesChannels::isChannelConfigured('yandex_market', $unconfiguredRuShop));
    }

    public function test_market_unavailable_channels_are_removed_before_activation_checks(): void
    {
        $globalShop = $this->shop('Global Shop', 'meanly.one', withValidYandex: true);
        $ruShop = $this->shop('RU Shop', 'meanly.ru', withValidYandex: true);

        $this->assertSame(
            ['offline_store'],
            SalesChannels::filterSelectionForShop(['yandex_market', 'offline_store'], $globalShop)
        );

        $this->assertSame(
            ['yandex_market', 'offline_store'],
            SalesChannels::filterSelectionForShop(['yandex_market', 'offline_store'], $ruShop)
        );
    }

    public function test_shop_region_can_resolve_market_when_domain_is_missing(): void
    {
        $shop = $this->shop('Region Only RU Shop', null, shopRegion: 'RU');

        $context = app(MarketContextResolver::class)->resolveForShop($shop);

        $this->assertSame('ru', $context->market);
        $this->assertFalse($context->matchedDomain);
        $this->assertContains('yandex_market', $context->salesChannels);
    }

    public function test_unmapped_shop_domain_falls_back_to_default_market_before_region(): void
    {
        $shop = $this->shop('Unmapped RU Region Shop', 'seller-catalog.test', shopRegion: 'RU');

        $context = app(MarketContextResolver::class)->resolveForShop($shop);

        $this->assertSame('global', $context->market);
        $this->assertFalse(SalesChannels::isChannelAllowedForShop('yandex_market', $shop));
    }

    private function shop(
        string $name,
        ?string $domain,
        bool $withValidYandex = false,
        ?string $shopRegion = null,
    ): Shop {
        return Shop::withoutEvents(fn () => Shop::create(array_filter([
            'name' => $name,
            'domain' => $domain,
            'voucher_prefix' => strtoupper(substr(preg_replace('/[^A-Z]/i', '', $name) ?: 'SHOP', 0, 8)),
            'shop_region' => $shopRegion,
            'is_active' => true,
            'business_id' => $withValidYandex ? 'business-1' : null,
            'campaign_id' => $withValidYandex ? 'campaign-1' : null,
            'api_key' => $withValidYandex ? 'test-yandex-token' : null,
            'ym_warehouse_id' => $withValidYandex ? 'warehouse-1' : null,
            'ym_legal_verified_at' => $withValidYandex ? now() : null,
            'ym_legal_verification' => $withValidYandex ? ['matches' => ['inn' => true]] : null,
        ], static fn (mixed $value): bool => $value !== null)));
    }
}
