<?php

namespace Tests\Feature;

use App\Models\CanonicalProductIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscoveryIntentBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_apply_command_persists_discovery_intent_on_identities(): void
    {
        $identity = CanonicalProductIdentity::query()->create([
            'fingerprint' => 'cp_test_apply_identity',
            'identity_slug' => 'steam-wallet-20-usd-apply',
            'canonical_category' => 'gift_cards',
            'discovery_intent' => null,
            'brand' => 'Steam',
            'confidence' => 'high',
            'seller_offers_count' => 1,
            'provider_candidates_count' => 1,
        ]);

        $this->artisan('catalog:reclassify-discovery-intent', [
            '--apply' => true,
            '--source' => 'identities',
        ])->assertSuccessful();

        $identity->refresh();

        $this->assertSame('play', $identity->discovery_intent);
    }

    public function test_storefront_category_api_rejects_legacy_category_slug(): void
    {
        $this->getJson('/api/storefront/v1/catalog/categories/gift_cards')
            ->assertNotFound();
    }

    public function test_storefront_category_api_accepts_discovery_intent_slug(): void
    {
        CanonicalProductIdentity::query()->create([
            'fingerprint' => 'cp_test_shop_identity',
            'identity_slug' => 'ikea-50-eur-shop',
            'canonical_category' => 'gift_cards',
            'discovery_intent' => 'shop',
            'brand' => 'IKEA',
            'confidence' => 'high',
            'seller_offers_count' => 1,
            'provider_candidates_count' => 1,
        ]);

        $this->getJson('/api/storefront/v1/catalog/categories/shop')
            ->assertOk()
            ->assertJsonPath('data.surface.discovery_intent', 'shop');
    }
}
