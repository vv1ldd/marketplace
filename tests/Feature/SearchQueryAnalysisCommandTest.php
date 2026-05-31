<?php

namespace Tests\Feature;

use App\Models\CanonicalProductIdentity;
use App\Models\CatalogSearchLog;
use App\Services\CanonicalProductSearchProfileBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SearchQueryAnalysisCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_query_analysis_classifies_current_profile_coverage(): void
    {
        $xbox = CanonicalProductIdentity::create([
            'fingerprint' => hash('sha256', 'xbox-us-25-usd-analysis'),
            'identity_slug' => 'xbox-us-25-usd-analysis',
            'canonical_category' => 'console_payment_cards',
            'brand' => 'Xbox',
            'product_family' => 'Xbox Gift Card',
            'face_value' => 25,
            'face_value_currency' => 'USD',
            'region' => 'US',
            'platform' => 'global',
            'confidence' => 'high',
            'signals' => [],
            'provider_candidates_count' => 1,
            'seller_offers_count' => 0,
            'last_seen_at' => now(),
        ]);

        app(CanonicalProductSearchProfileBuilder::class)->rebuild($xbox);

        CatalogSearchLog::create([
            'query' => 'xbox usa',
            'normalized_query' => 'xbox usa',
            'source' => 'storefront',
            'intent' => 'buy_now',
            'filters' => ['brand' => 'Xbox', 'region' => 'USA'],
            'results_count' => 8,
        ]);
        CatalogSearchLog::create([
            'query' => 'unknown thing',
            'normalized_query' => 'unknown thing',
            'source' => 'storefront',
            'intent' => 'buy_now',
            'filters' => ['brand' => 'Unknown'],
            'results_count' => 0,
        ]);

        $exitCode = Artisan::call('search-query:analyze', ['--limit' => 10, '--json' => true]);
        $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exitCode);
        $this->assertSame(2, $payload['summary']['queries_analyzed']);
        $this->assertSame(1, $payload['summary']['covered']);
        $this->assertSame(1, $payload['summary']['zero_result_hotspots']);
        $this->assertSame('COVERED', collect($payload['queries'])->firstWhere('query', 'xbox usa')['diagnosis']);
        $this->assertSame('ZERO_RESULT_HOTSPOT', collect($payload['queries'])->firstWhere('query', 'unknown thing')['diagnosis']);
    }
}
