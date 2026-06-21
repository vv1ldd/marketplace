<?php

namespace Tests\Feature;

use App\Models\CanonicalProductIdentity;
use App\Models\CatalogSearchLog;
use App\Models\DemandGap;
use App\Services\DemandGapEngineService;
use App\Services\DiscoveryEntityGraphService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscoveryEntityGraphTest extends TestCase
{
    use RefreshDatabase;

    public function test_discovery_graph_exposes_brand_region_and_intersection_nodes(): void
    {
        $this->seedCanonicalIdentity('steam-turkey-100-try', 'Steam', 'turkey', 'gift_cards', sellerOffers: 2);
        $this->seedCanonicalIdentity('steam-argentina-100-ars', 'Steam', 'argentina', 'gift_cards');
        $this->seedCanonicalIdentity('xbox-turkey-50-try', 'Xbox', 'turkey', 'gaming');

        /** @var DiscoveryEntityGraphService $graph */
        $graph = app(DiscoveryEntityGraphService::class);

        $brand = $graph->brand('steam');
        $region = $graph->region('turkey');
        $edge = $graph->brandRegion('steam', 'turkey');

        $this->assertNotNull($brand);
        $this->assertSame('Steam', $brand['name']);
        $this->assertSame(2, $brand['product_count']);
        $this->assertSame(route('meanly.catalog.brands.show', 'steam'), $brand['url']);

        $this->assertNotNull($region);
        $this->assertSame('turkey', $region['name']);
        $this->assertSame(2, $region['product_count']);

        $this->assertNotNull($edge);
        $this->assertSame('Steam', $edge['brand']);
        $this->assertSame('turkey', $edge['region']);
        $this->assertSame(1, $edge['product_count']);
        $this->assertSame(route('meanly.catalog.brand-regions.show', ['brandSlug' => 'steam', 'regionSlug' => 'turkey']), $edge['url']);
    }

    public function test_brand_region_landing_routes_render_and_filter_catalog_products(): void
    {
        $this->seedCanonicalIdentity('steam-turkey-100-try', 'Steam', 'turkey', 'gift_cards', sellerOffers: 2);
        $this->seedCanonicalIdentity('steam-argentina-100-ars', 'Steam', 'argentina', 'gift_cards');
        $this->seedCanonicalIdentity('xbox-turkey-50-try', 'Xbox', 'turkey', 'gaming');

        $this->get(route('meanly.catalog.brands.show', 'steam'))
            ->assertRedirect('/catalog/brands/steam');

        $this->get(route('meanly.catalog.regions.show', 'turkey'))
            ->assertRedirect('/catalog/regions/turkey');

        $this->get(route('meanly.catalog.brand-regions.show', ['brandSlug' => 'steam', 'regionSlug' => 'turkey']))
            ->assertRedirect('/catalog/brands/steam/regions/turkey');

        $this->get('/catalog/brands/not-a-brand')->assertNotFound();
        $this->get('/catalog/regions/not-a-region')->assertNotFound();
    }

    public function test_catalog_landing_can_sort_products_by_nominal(): void
    {
        $this->seedCanonicalIdentity('blizzard-canada-60-cad', 'Blizzard', 'canada', 'gift_cards');
        $this->seedCanonicalIdentity('blizzard-united-states-20-usd', 'Blizzard', 'united-states', 'gift_cards');
        $this->seedCanonicalIdentity('blizzard-united-states-50-usd', 'Blizzard', 'united-states', 'gift_cards');

        $this->get(route('meanly.catalog.brands.show', [
            'brandSlug' => 'blizzard',
            'sort' => 'face_value_asc',
        ]))
            ->assertRedirect('/catalog/brands/blizzard?sort=face_value_asc');
    }

    public function test_discovery_sitemaps_are_generated_from_graph(): void
    {
        $this->seedCanonicalIdentity('steam-turkey-100-try', 'Steam', 'turkey', 'gift_cards', sellerOffers: 2);

        $this->get(route('sitemap.brands'))
            ->assertOk()
            ->assertSee(route('meanly.catalog.brands.show', 'steam'), false);

        $this->get(route('sitemap.regions'))
            ->assertOk()
            ->assertSee(route('meanly.catalog.regions.show', 'turkey'), false);

        $this->get(route('sitemap.brand-regions'))
            ->assertOk()
            ->assertSee(route('meanly.catalog.brand-regions.show', ['brandSlug' => 'steam', 'regionSlug' => 'turkey']), false);
    }

    public function test_demand_gap_recalculation_attaches_entity_keys(): void
    {
        CatalogSearchLog::create([
            'query' => 'Steam Turkey',
            'normalized_query' => 'steam turkey',
            'source' => 'storefront',
            'intent' => 'buy_now',
            'filters' => ['brand' => 'Steam', 'region' => 'turkey', 'category' => 'gift_cards'],
            'confidence' => 0.95,
            'results_count' => 0,
        ]);

        app(DemandGapEngineService::class)->recalculateGaps();

        $gap = DemandGap::firstOrFail();

        $this->assertSame('steam', $gap->brand_entity_key);
        $this->assertSame('turkey', $gap->region_entity_key);
        $this->assertSame('gift-cards', $gap->category_entity_key);
    }

    private function seedCanonicalIdentity(string $slug, string $brand, string $region, string $category, int $sellerOffers = 0): CanonicalProductIdentity
    {
        preg_match('/(\d+)-([a-z]+)/', $slug, $matches);
        $faceValue = isset($matches[1]) ? (float) $matches[1] : null;
        $currency = isset($matches[2]) ? strtoupper($matches[2]) : null;

        return CanonicalProductIdentity::create([
            'fingerprint' => hash('sha256', $slug),
            'identity_slug' => $slug,
            'canonical_category' => $category,
            'brand' => $brand,
            'product_family' => $brand,
            'face_value' => $faceValue,
            'face_value_currency' => $currency,
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
