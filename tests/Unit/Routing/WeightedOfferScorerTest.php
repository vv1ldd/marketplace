<?php

namespace Tests\Unit\Routing;

use App\Domain\Routing\RoutingCircuitBreaker;
use App\Domain\Routing\RoutingPolicy;
use App\Domain\Routing\WeightedOfferScorer;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class WeightedOfferScorerTest extends TestCase
{
    private WeightedOfferScorer $scorer;

    private RoutingPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scorer = app(WeightedOfferScorer::class);
        $this->policy = new RoutingPolicy(
            type: 'weighted',
            weights: [
                'margin' => 0.40,
                'success_rate' => 0.30,
                'latency' => 0.15,
                'stock' => 0.15,
            ],
            version: 'v1',
        );
    }

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }

    public function test_excluded_provider_scores_zero(): void
    {
        $candidates = collect([
            $this->offer(providerId: 42, margin: 0.8, success: 0.9, stock: 10),
            $this->offer(providerId: 77, margin: 0.5, success: 0.7, stock: 5),
        ]);

        $score = $this->scorer->score($candidates->first(), $candidates, $this->policy, [42]);

        $this->assertSame(0.0, $score);
    }

    public function test_circuit_breaker_tripped_provider_scores_zero(): void
    {
        $candidates = collect([
            $this->offer(providerId: 42, margin: 0.8, success: 0.9, stock: 10),
        ]);

        app(RoutingCircuitBreaker::class)->trip(42, $this->policy);

        $score = $this->scorer->score($candidates->first(), $candidates, $this->policy);

        $this->assertSame(0.0, $score);
    }

    public function test_higher_margin_offer_scores_higher_within_candidate_set(): void
    {
        $candidates = collect([
            $this->offer(providerId: 42, margin: 0.9, success: 0.8, stock: 10),
            $this->offer(providerId: 77, margin: 0.2, success: 0.8, stock: 10),
        ]);

        $high = $this->scorer->score($candidates[0], $candidates, $this->policy);
        $low = $this->scorer->score($candidates[1], $candidates, $this->policy);

        $this->assertGreaterThan($low, $high);
    }

    /**
     * @return array<string, mixed>
     */
    private function offer(int $providerId, float $margin, float $success, int $stock): array
    {
        return [
            'product_id' => $providerId * 10,
            'provider_id' => $providerId,
            'margin' => [
                'buyer_price_cents' => 1000,
                'purchase_price_cents' => (int) round(1000 * (1 - $margin)),
            ],
            'ranking' => [
                'metrics' => [
                    'seller_orders_90_days' => 100,
                    'seller_completed_90_days' => (int) round(100 * $success),
                    'p50_fulfillment_ms_7d' => 1000,
                    'stock_count' => $stock,
                ],
            ],
            'price' => ['amount' => 10.0],
        ];
    }
}
