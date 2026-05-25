<?php

namespace Tests\Feature;

use App\Models\CanonicalProductIdentity;
use App\Models\DemandGap;
use App\Models\OpportunityCase;
use App\Services\OpportunityLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpportunityGraphApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_entity_endpoint_returns_demand_diagnosis_cases_and_opportunities(): void
    {
        $this->seedCanonicalIdentity('steam-turkey-100-try', 'Steam', 'turkey', 'gift_cards', sellerOffers: 2);
        $gap = $this->seedDemandGap('steam turkey', 'steam', 'turkey', 'gift-cards', 91.0, 'catalog_gap');

        app(OpportunityLifecycleService::class)->openCase($gap, autoCreated: true);

        $response = $this->getJson(route('llms.commerce.entities.show', [
            'type' => 'intersections',
            'slug' => 'steam-turkey',
        ]));

        $response
            ->assertOk()
            ->assertJsonPath('type', 'MeanlyCommerceEntityOpportunity')
            ->assertJsonPath('entity.type', 'brand_region')
            ->assertJsonPath('entity.brand_slug', 'steam')
            ->assertJsonPath('entity.region_slug', 'turkey')
            ->assertJsonPath('demand.searches', 120)
            ->assertJsonPath('demand.max_opportunity_score', 91)
            ->assertJsonPath('diagnosis.primary.code', 'catalog_gap')
            ->assertJsonPath('cases.active', 1)
            ->assertJsonPath('top_opportunities.0.canonical_query', 'steam turkey');
    }

    public function test_opportunity_listing_endpoint_filters_and_sorts_read_model(): void
    {
        $steamGap = $this->seedDemandGap('steam turkey', 'steam', 'turkey', 'gift-cards', 91.0, 'catalog_gap');
        $this->seedDemandGap('xbox colombia', 'xbox', 'colombia', 'gaming', 42.0, 'pricing_issue');
        app(OpportunityLifecycleService::class)->openCase($steamGap, autoCreated: true);

        $response = $this->getJson(route('llms.commerce.opportunities', [
            'brand' => 'steam',
            'region' => 'turkey',
            'min_score' => 70,
            'has_active_case' => 'true',
            'sort' => 'opportunity_score',
        ]));

        $response
            ->assertOk()
            ->assertJsonPath('type', 'MeanlyCommerceOpportunityList')
            ->assertJsonPath('count', 1)
            ->assertJsonPath('opportunities.0.canonical_query', 'steam turkey')
            ->assertJsonPath('opportunities.0.entity_keys.brand', 'steam')
            ->assertJsonPath('opportunities.0.cases.active', 1);
    }

    public function test_action_effectiveness_endpoint_returns_historical_outcomes(): void
    {
        $gap = $this->seedDemandGap('steam turkey', 'steam', 'turkey', 'gift-cards', 91.0, 'catalog_gap');
        /** @var OpportunityLifecycleService $service */
        $service = app(OpportunityLifecycleService::class);
        $case = $service->openCase($gap);
        $case = $service->recordAction($case, OpportunityCase::ACTION_ADD_SUPPLY, 'Added supply.');

        $gap->update([
            'opportunity_score' => 31.0,
            'attributed_gmv' => 24000,
            'attributed_orders_count' => 12,
            'opportunity_diagnosis' => 'healthy',
            'diagnosis_confidence' => 70,
            'opportunity_diagnosis_graph' => [
                ['cause' => 'healthy', 'score' => 70],
            ],
        ]);

        $service->resolveCase($case, recalculate: false);

        $response = $this->getJson(route('llms.commerce.actions.effectiveness'));

        $response
            ->assertOk()
            ->assertJsonPath('type', 'MeanlyCommerceActionEffectiveness')
            ->assertJsonPath('actions.add_supply.cases_count', 1)
            ->assertJsonPath('actions.add_supply.success_rate', 100)
            ->assertJsonPath('actions.add_supply.avg_score_delta', 60);
    }

    public function test_unknown_entity_returns_404(): void
    {
        $this->getJson(route('llms.commerce.entities.show', [
            'type' => 'brands',
            'slug' => 'unknown',
        ]))->assertNotFound();
    }

    private function seedDemandGap(string $query, string $brand, string $region, string $category, float $score, string $diagnosis): DemandGap
    {
        return DemandGap::create([
            'canonical_query' => $query,
            'brand_entity_key' => $brand,
            'region_entity_key' => $region,
            'category_entity_key' => $category,
            'search_volume' => 120,
            'views_count' => 20,
            'carts_count' => 4,
            'zero_results_count' => 70,
            'average_results_count' => 0.5,
            'attributed_orders_count' => 0,
            'attributed_gmv' => 0,
            'estimated_lost_gmv' => 320000,
            'opportunity_score' => $score,
            'opportunity_diagnosis' => $diagnosis,
            'diagnosis_confidence' => 82,
            'opportunity_diagnosis_graph' => [
                ['cause' => $diagnosis, 'score' => 82],
                ['cause' => 'pricing_issue', 'score' => 37],
            ],
            'demand_gap_score' => 320000,
            'priority_label' => 'critical',
            'last_searched_at' => now(),
        ]);
    }

    private function seedCanonicalIdentity(string $slug, string $brand, string $region, string $category, int $sellerOffers = 0): CanonicalProductIdentity
    {
        return CanonicalProductIdentity::create([
            'fingerprint' => hash('sha256', $slug),
            'identity_slug' => $slug,
            'canonical_category' => $category,
            'brand' => $brand,
            'product_family' => $brand,
            'face_value' => 100,
            'face_value_currency' => 'TRY',
            'region' => $region,
            'platform' => 'global',
            'confidence' => 'high',
            'signals' => [],
            'provider_candidates_count' => 1,
            'seller_offers_count' => $sellerOffers,
            'last_seen_at' => now(),
        ]);
    }
}
