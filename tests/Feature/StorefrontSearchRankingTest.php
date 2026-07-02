<?php

namespace Tests\Feature;

use App\Models\CanonicalProductIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class StorefrontSearchRankingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_storefront_search_filters_by_detected_playstation_brand(): void
    {
        $this->identity([
            'fingerprint' => hash('sha256', 'playstation-us-20-usd'),
            'identity_slug' => 'playstation-us-20-usd',
            'canonical_category' => 'console_payment_cards',
            'discovery_intent' => 'play',
            'brand' => 'PlayStation',
            'product_family' => 'PlayStation Gift Card',
            'face_value' => 20,
            'face_value_currency' => 'USD',
            'region' => 'US',
        ]);
        $this->identity([
            'fingerprint' => hash('sha256', 'bol-com-nl-5-eur'),
            'identity_slug' => 'bol-com-nl-5-eur',
            'canonical_category' => 'gift_cards',
            'discovery_intent' => 'shop',
            'brand' => 'Bol.com',
            'product_family' => 'Bol.com Gift Card',
            'face_value' => 5,
            'face_value_currency' => 'EUR',
            'region' => 'NL',
        ]);

        $this->getJson('/api/storefront/v1/catalog?q=playstation')
            ->assertOk()
            ->assertJsonPath('data.products.browse.0.brand', 'PlayStation')
            ->assertJsonMissing(['brand' => 'Bol.com']);
    }

    public function test_storefront_search_filters_by_detected_play_intent(): void
    {
        $this->identity([
            'fingerprint' => hash('sha256', 'epic-gift-400-aed'),
            'identity_slug' => 'epic-gift-400-aed',
            'canonical_category' => 'game_wallet_topups',
            'discovery_intent' => 'play',
            'brand' => 'Epic Games',
            'product_family' => 'Epic Gift',
            'face_value' => 400,
            'face_value_currency' => 'AED',
            'region' => 'global',
        ]);
        $this->identity([
            'fingerprint' => hash('sha256', 'foot-locker-us-25-usd'),
            'identity_slug' => 'foot-locker-us-25-usd',
            'canonical_category' => 'gift_cards',
            'discovery_intent' => 'shop',
            'brand' => 'Foot Locker',
            'product_family' => 'Foot Locker Gift Card',
            'face_value' => 25,
            'face_value_currency' => 'USD',
            'region' => 'US',
        ]);

        $this->getJson('/api/storefront/v1/catalog?q=play')
            ->assertOk()
            ->assertJsonPath('data.products.browse.0.brand', 'Epic Games')
            ->assertJsonMissing(['brand' => 'Foot Locker']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function identity(array $attributes): CanonicalProductIdentity
    {
        return CanonicalProductIdentity::create($attributes + [
            'platform' => 'global',
            'confidence' => 'high',
            'signals' => [],
            'provider_candidates_count' => 1,
            'seller_offers_count' => 0,
            'last_seen_at' => now(),
        ]);
    }
}
