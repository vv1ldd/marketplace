<?php

namespace Tests\Feature;

use App\Models\CommerceEntity;
use App\Models\CommerceEntityLink;
use App\Models\CommerceEntityMetric;
use App\Models\Currency;
use App\Models\IntentLiquidityNode;
use App\Services\IntentLiquidityGraphService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntentLiquidityGraphTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_materializes_commerce_and_currency_intents_into_corridors(): void
    {
        $currency = Currency::where('code', 'TRY')->firstOrFail();
        $currency->forceFill([
            'base_asset' => 'USDT',
            'quote_asset' => 'TRY',
            'liquidity_stress_index' => 0.12,
            'observability_score' => 0.88,
            'confidence_score' => 0.82,
            'max_executable_size' => 25000,
            'estimated_slippage' => 0.004,
            'market_regime' => 'THIN',
            'execution_ready' => true,
            'corridors' => [
                'USDT/TRY' => [
                    'source' => 'bybit',
                    'route_type' => 'P2P',
                    'regime' => 'THIN',
                    'execution_grade' => 'B',
                    'route_score' => 72.5,
                    'capacity' => 25000,
                    'maker_count' => 8,
                    'slippage_bps' => 65,
                    'failure_modes' => ['LOW_DEPTH'],
                    'confidence' => 0.82,
                ],
            ],
        ])->save();

        $entity = CommerceEntity::create([
            'slug' => 'steam-turkey-100-try',
            'entity_type' => 'gift_card',
            'attributes' => [
                'brand' => 'steam',
                'brand_label' => 'Steam',
                'region' => 'turkey',
                'region_label' => 'Turkey',
                'category' => 'gift_cards',
                'face_value' => 100,
                'currency' => 'TRY',
            ],
            'canonical_query' => 'steam turkey 100 TRY',
        ]);

        CommerceEntityLink::create([
            'commerce_entity_id' => $entity->id,
            'link_type' => CommerceEntityLink::TYPE_PRODUCT,
            'link_id' => 123,
            'confidence' => 0.98,
            'signals' => ['source' => 'test'],
        ]);

        CommerceEntityMetric::create([
            'commerce_entity_id' => $entity->id,
            'searches' => 100,
            'views' => 60,
            'carts' => 30,
            'orders' => 8,
            'attributed_gmv' => 12000,
            'estimated_lost_gmv' => 45000,
            'opportunity_score' => 82.5,
            'active_cases' => 1,
            'resolved_cases' => 0,
            'calculated_at' => now(),
        ]);

        $count = app(IntentLiquidityGraphService::class)->rebuild();

        $this->assertGreaterThanOrEqual(2, $count);

        $buyNode = IntentLiquidityNode::with('corridors')->where('intent_key', 'buy:commerce:steam-turkey-100-try')->firstOrFail();
        $this->assertSame('buyer', $buyNode->actor_role);
        $this->assertTrue($buyNode->corridors->contains(fn ($corridor): bool => $corridor->corridor_type === 'product'));
        $this->assertTrue($buyNode->corridors->contains(fn ($corridor): bool => $corridor->corridor_type === 'currency' && $corridor->corridor_key === 'USDT/TRY'));

        $exchangeNode = IntentLiquidityNode::with('corridors')->where('intent_key', 'exchange:currency:TRY')->firstOrFail();
        $this->assertSame('liquidity_provider', $exchangeNode->actor_role);
        $this->assertTrue($exchangeNode->corridors->firstWhere('corridor_key', 'USDT/TRY')->execution_ready);
    }

    public function test_llm_intent_endpoint_exposes_nodes_and_corridors(): void
    {
        $entity = CommerceEntity::create([
            'slug' => 'xbox-turkey-50-try',
            'entity_type' => 'gift_card',
            'attributes' => [
                'brand' => 'xbox',
                'region' => 'turkey',
                'category' => 'gift_cards',
                'face_value' => 50,
                'currency' => 'TRY',
            ],
            'canonical_query' => 'xbox turkey 50 TRY',
        ]);

        CommerceEntityMetric::create([
            'commerce_entity_id' => $entity->id,
            'searches' => 10,
            'opportunity_score' => 20,
            'calculated_at' => now(),
        ]);

        app(IntentLiquidityGraphService::class)->syncCommerceEntity($entity);

        $this->getJson(route('llms.intents.index', ['intent' => 'index', 'q' => 'xbox']))
            ->assertOk()
            ->assertJsonPath('type', 'MeanlyIntentLiquidityGraph')
            ->assertJsonPath('nodes.0.intent_key', 'index:commerce:xbox-turkey-50-try')
            ->assertJsonPath('nodes.0.corridors.0.type', 'index');

        $this->getJson(route('llms.intents.show', ['intentKey' => 'index:commerce:xbox-turkey-50-try']))
            ->assertOk()
            ->assertJsonPath('intent_key', 'index:commerce:xbox-turkey-50-try')
            ->assertJsonPath('entity.slug', 'xbox-turkey-50-try');
    }
}
