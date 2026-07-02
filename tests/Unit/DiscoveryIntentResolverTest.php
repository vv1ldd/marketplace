<?php

namespace Tests\Unit;

use App\Services\CanonicalCategoryResolver;
use Tests\TestCase;

class DiscoveryIntentResolverTest extends TestCase
{
    private CanonicalCategoryResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = app(CanonicalCategoryResolver::class);
    }

    public function test_brand_override_routes_steam_from_gift_cards_to_play(): void
    {
        $intent = $this->resolver->discoveryIntent('gift_cards', ['Steam', 'Steam Wallet 20 USD']);

        $this->assertSame('play', $intent);
    }

    public function test_brand_override_routes_spotify_from_subscriptions_to_stream(): void
    {
        $intent = $this->resolver->discoveryIntent('subscriptions', ['Spotify', 'Spotify Premium 3 months']);

        $this->assertSame('stream', $intent);
    }

    public function test_brand_override_routes_xbox_game_pass_from_subscriptions_to_play(): void
    {
        $intent = $this->resolver->discoveryIntent('subscriptions', ['Xbox', 'Xbox Game Pass Ultimate']);

        $this->assertSame('play', $intent);
    }

    public function test_brand_override_routes_riot_from_payment_prepaid_to_play(): void
    {
        $intent = $this->resolver->discoveryIntent('payment_prepaid_cards', ['Riot Games', 'Riot Points 1000']);

        $this->assertSame('play', $intent);
    }

    public function test_retail_gift_card_maps_to_shop(): void
    {
        $intent = $this->resolver->discoveryIntent('gift_cards', ['IKEA', 'IKEA Gift Card 50 EUR']);

        $this->assertSame('shop', $intent);
    }

    public function test_app_store_maps_to_mobile(): void
    {
        $intent = $this->resolver->discoveryIntent('mobile_app_store_cards', ['Apple', 'App Store Gift Card 25 USD']);

        $this->assertSame('mobile', $intent);
    }

    public function test_amazon_in_travel_legacy_routes_to_shop_via_brand_override(): void
    {
        $intent = $this->resolver->discoveryIntent('travel_entertainment_vouchers', ['Amazon', 'Amazon Gift Card']);

        $this->assertSame('shop', $intent);
    }

    public function test_resolve_returns_two_phase_payload(): void
    {
        $result = $this->resolver->resolve([], ['PlayStation', 'PSN 100 TRY'], 'console_payment_cards');

        $this->assertSame('console_payment_cards', $result['canonical_category']);
        $this->assertSame('play', $result['discovery_intent']);
        $this->assertSame('brand_override', $result['resolution']);
    }

    public function test_unknown_legacy_category_falls_back_to_unclassified(): void
    {
        $intent = $this->resolver->discoveryIntent('local_vouchers', ['Meanly', 'Local voucher']);

        $this->assertSame('unclassified', $intent);
    }

    public function test_cross_links_exist_for_play_corridor(): void
    {
        $links = $this->resolver->crossLinksForCorridor('play');

        $this->assertNotEmpty($links);
        $this->assertSame('mobile', $links[0]['target_corridor']);
    }
}
