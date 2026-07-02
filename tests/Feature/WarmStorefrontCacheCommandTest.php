<?php

namespace Tests\Feature;

use App\Models\CanonicalProductIdentity;
use App\Services\CanonicalStorefrontHomepageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class WarmStorefrontCacheCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_warm_cache_command_runs_and_reports_warmed_blocks(): void
    {
        CanonicalProductIdentity::create([
            'fingerprint' => hash('sha256', 'steam-us-20-usd'),
            'identity_slug' => 'steam-us-20-usd',
            'canonical_category' => 'game_wallet_topups',
            'discovery_intent' => 'play',
            'brand' => 'Steam',
            'product_family' => 'Steam Wallet',
            'face_value' => 20,
            'face_value_currency' => 'USD',
            'region' => 'US',
            'platform' => 'global',
            'confidence' => 'high',
            'signals' => [],
            'provider_candidates_count' => 1,
            'seller_offers_count' => 0,
            'last_seen_at' => now(),
        ]);

        $this->artisan('catalog:warm-cache', ['--searches' => 3])
            ->assertSuccessful();
    }

    public function test_warm_service_reports_counts_for_homepage_and_corridors(): void
    {
        $warmed = app(CanonicalStorefrontHomepageService::class)
            ->warmStorefrontCaches(['playstation', 'steam']);

        $this->assertSame(6, $warmed['homepage']);
        $this->assertGreaterThan(0, $warmed['categories']);
        $this->assertSame(2, $warmed['searches']);
    }
}
