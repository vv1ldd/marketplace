<?php

namespace Tests\Feature;

use App\Models\CanonicalProductIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontCatalogIntentContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_api_categories_match_intent_discovery_contract(): void
    {
        foreach ([
            ['shop', 120],
            ['play', 80],
            ['stream', 40],
        ] as [$intent, $count]) {
            for ($i = 0; $i < $count; $i++) {
                CanonicalProductIdentity::query()->create([
                    'fingerprint' => "cp_contract_{$intent}_{$i}",
                    'identity_slug' => "{$intent}-contract-{$i}",
                    'canonical_category' => 'gift_cards',
                    'discovery_intent' => $intent,
                    'brand' => 'TestBrand',
                    'confidence' => 'high',
                    'seller_offers_count' => 1,
                    'provider_candidates_count' => 1,
                ]);
            }
        }

        $response = $this->getJson('/api/storefront/v1/catalog')
            ->assertOk();

        $categories = collect($response->json('data.categories'));

        $this->assertFalse($categories->pluck('slug')->contains('gift_cards'));
        $this->assertSame(['shop', 'play', 'stream'], $categories->pluck('slug')->take(3)->all());
        $this->assertSame('discover:shop', $categories->firstWhere('slug', 'shop')['intent_key']);
        $this->assertSame('category_shop_title', $categories->firstWhere('slug', 'shop')['name_key']);
        $this->assertSame(50.0, (float) $categories->firstWhere('slug', 'shop')['demand_score']);

        $play = $categories->firstWhere('slug', 'play');
        $this->assertNotEmpty($play['cross_links']);
        $this->assertSame('mobile', $play['cross_links'][0]['target_slug']);
        $this->assertSame('Apple', $play['cross_links'][0]['brand_focus']);
    }
}
