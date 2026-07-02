<?php

namespace Tests\Unit\Routing;

use App\Domain\Routing\RoutingPolicy;
use App\Services\Architecture\OfferRoutingService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class OfferRoutingServiceWeightedTest extends TestCase
{
    protected function tearDown(): void
    {
        config(['routing.enabled' => false]);
        Cache::flush();
        parent::tearDown();
    }

    public function test_weighted_ranking_prefers_higher_margin_offer_when_enabled(): void
    {
        config([
            'routing.enabled' => true,
            'routing.weights' => [
                'margin' => 0.40,
                'success_rate' => 0.30,
                'latency' => 0.15,
                'stock' => 0.15,
            ],
        ]);

        $service = app(OfferRoutingService::class);
        $policy = RoutingPolicy::fromConfig();

        $offers = collect([
            [
                'product_id' => 101,
                'provider_id' => 42,
                'margin' => ['buyer_price_cents' => 1000, 'purchase_price_cents' => 200],
                'ranking' => ['metrics' => ['seller_orders_90_days' => 10, 'seller_completed_90_days' => 9, 'stock_count' => 5, 'p50_fulfillment_ms_7d' => 1200]],
                'price' => ['amount' => 10],
            ],
            [
                'product_id' => 202,
                'provider_id' => 77,
                'margin' => ['buyer_price_cents' => 1000, 'purchase_price_cents' => 700],
                'ranking' => ['metrics' => ['seller_orders_90_days' => 10, 'seller_completed_90_days' => 9, 'stock_count' => 5, 'p50_fulfillment_ms_7d' => 1200]],
                'price' => ['amount' => 10],
            ],
        ]);

        $ranked = $service->rankOffers(
            $offers,
            'best_offer',
            $policy,
            [],
            'entitlement-fingerprint-test',
        );

        $this->assertSame(101, $ranked->first()['product_id']);
        $this->assertSame('weighted_v1', $ranked->first()['routing']['method']);
        $this->assertArrayHasKey('sticky_slot', $ranked->first()['routing']);
    }

    public function test_legacy_ranking_is_used_when_feature_flag_disabled(): void
    {
        config(['routing.enabled' => false]);

        $service = app(OfferRoutingService::class);
        $offers = collect([
            [
                'product_id' => 1,
                'ranking' => ['score' => 10, 'metrics' => ['stock_count' => 0]],
                'price' => ['amount' => 20],
            ],
            [
                'product_id' => 2,
                'ranking' => ['score' => 90, 'metrics' => ['stock_count' => 0]],
                'price' => ['amount' => 10],
            ],
        ]);

        $ranked = $service->rankOffers($offers, 'best_offer');

        $this->assertSame(2, $ranked->first()['product_id']);
        $this->assertSame('intent_best_offer_seller_offer_v1', $ranked->first()['routing']['method']);
    }
}
