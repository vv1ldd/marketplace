<?php

namespace Tests\Feature;

use App\Models\CanonicalProductIdentity;
use App\Models\CanonicalProductIdentitySource;
use App\Models\CommerceEntity;
use App\Models\CommerceEntityLink;
use App\Models\DemandGap;
use App\Models\Product;
use App\Services\CommerceEntityGraphService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommerceEntityGraphTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_rebuilds_commerce_entities_links_and_metrics_from_canonical_identities(): void
    {
        $identity = $this->seedCanonicalIdentity();
        $product = Product::create([
            'sku' => 'STEAM-TR-100',
            'name' => 'Steam Turkey 100 TRY',
            'slug' => 'steam-turkey-100-try',
            'price_rub' => 52000,
            'type' => 'giftcard',
            'canonical_category' => 'gift_cards',
            'is_active' => true,
        ]);

        CanonicalProductIdentitySource::create([
            'canonical_product_identity_id' => $identity->id,
            'source_type' => CanonicalProductIdentitySource::SOURCE_PRODUCT,
            'source_id' => $product->id,
            'source_sku' => 'STEAM-TR-100',
            'confidence' => 'high',
            'signals' => ['source' => 'test'],
            'last_seen_at' => now(),
        ]);

        DemandGap::create([
            'canonical_query' => 'steam turkey 100 try',
            'brand_entity_key' => 'steam',
            'region_entity_key' => 'turkey',
            'category_entity_key' => 'gift-cards',
            'search_volume' => 100,
            'views_count' => 80,
            'carts_count' => 40,
            'zero_results_count' => 20,
            'average_results_count' => 1.5,
            'attributed_orders_count' => 10,
            'attributed_gmv' => 18000,
            'estimated_lost_gmv' => 45000,
            'opportunity_score' => 77.5,
            'opportunity_diagnosis' => 'catalog_gap',
            'diagnosis_confidence' => 82,
            'opportunity_diagnosis_graph' => [['cause' => 'catalog_gap', 'score' => 82]],
            'demand_gap_score' => 45000,
            'priority_label' => 'high',
            'last_searched_at' => now(),
        ]);

        /** @var CommerceEntityGraphService $graph */
        $graph = app(CommerceEntityGraphService::class);
        $this->assertSame(1, $graph->rebuild());

        $entity = CommerceEntity::with(['links', 'metrics'])->where('slug', 'steam-turkey-100-try')->firstOrFail();
        $this->assertSame('gift_card', $entity->entity_type);
        $this->assertSame('steam', $entity->attributes['brand']);
        $this->assertSame('turkey', $entity->attributes['region']);
        $this->assertEquals(100.0, $entity->attributes['face_value']);
        $this->assertSame('TRY', $entity->attributes['currency']);
        $this->assertSame('steam turkey 100 TRY', $entity->canonical_query);

        $this->assertTrue($entity->links->contains(fn (CommerceEntityLink $link): bool => $link->link_type === CommerceEntityLink::TYPE_CANONICAL_IDENTITY && $link->link_id === $identity->id));
        $this->assertTrue($entity->links->contains(fn (CommerceEntityLink $link): bool => $link->link_type === CommerceEntityLink::TYPE_PRODUCT && $link->link_id === $product->id));

        $this->assertNotNull($entity->metrics);
        $this->assertSame(100, $entity->metrics->searches);
        $this->assertSame(80, $entity->metrics->views);
        $this->assertSame(40, $entity->metrics->carts);
        $this->assertEquals(10.0, $entity->metrics->orders);
        $this->assertEquals(18000.0, $entity->metrics->attributed_gmv);
        $this->assertEquals(45000.0, $entity->metrics->estimated_lost_gmv);
        $this->assertEquals(77.5, $entity->metrics->opportunity_score);
    }

    public function test_llm_commerce_entity_endpoint_returns_stable_intent_node(): void
    {
        $this->seedCanonicalIdentity();
        DemandGap::create([
            'canonical_query' => 'steam turkey 100 try',
            'brand_entity_key' => 'steam',
            'region_entity_key' => 'turkey',
            'category_entity_key' => 'gift-cards',
            'search_volume' => 12,
            'estimated_lost_gmv' => 1000,
            'opportunity_score' => 30,
            'demand_gap_score' => 1000,
            'priority_label' => 'low',
            'last_searched_at' => now(),
        ]);

        app(CommerceEntityGraphService::class)->rebuild();

        $this->getJson(route('llms.commerce.entities.show', [
            'type' => 'commerce',
            'slug' => 'steam-turkey-100-try',
        ]))
            ->assertOk()
            ->assertJsonPath('type', 'MeanlyCommerceEntityNode')
            ->assertJsonPath('entity.slug', 'steam-turkey-100-try')
            ->assertJsonPath('entity.attributes.brand', 'steam')
            ->assertJsonPath('metrics.searches', 12)
            ->assertJsonPath('links.0.type', CommerceEntityLink::TYPE_CANONICAL_IDENTITY);
    }

    private function seedCanonicalIdentity(): CanonicalProductIdentity
    {
        return CanonicalProductIdentity::create([
            'fingerprint' => hash('sha256', 'steam-turkey-100-try'),
            'identity_slug' => 'steam-turkey-100-try',
            'canonical_category' => 'gift_cards',
            'brand' => 'Steam',
            'product_family' => 'Steam',
            'face_value' => 100,
            'face_value_currency' => 'TRY',
            'region' => 'turkey',
            'platform' => 'Steam',
            'confidence' => 'high',
            'signals' => [],
            'provider_candidates_count' => 1,
            'seller_offers_count' => 1,
            'last_seen_at' => now(),
        ]);
    }
}
