<?php

namespace Tests\Feature;

use App\Models\CanonicalProductIdentity;
use App\Models\ExternalSearchQuerySignal;
use App\Models\SearchDemandRecommendation;
use App\Services\CanonicalProductSearchProfileBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SearchDemandIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_external_search_signals_import_and_analyze_without_mutating_profiles(): void
    {
        $identity = CanonicalProductIdentity::create([
            'fingerprint' => hash('sha256', 'xbox-us-25-usd-demand-intel'),
            'identity_slug' => 'xbox-us-25-usd-demand-intel',
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
        app(CanonicalProductSearchProfileBuilder::class)->rebuild($identity);
        $profileTimestamp = $identity->searchProfile()->firstOrFail()->updated_at;

        $path = tempnam(sys_get_temp_dir(), 'external-search-signals-').'.json';
        file_put_contents($path, json_encode([
            'signals' => [
                [
                    'query' => 'xbox usa',
                    'source' => 'google_search_console',
                    'country' => 'AR',
                    'locale' => 'es-AR',
                    'impressions' => 100,
                    'clicks' => 12,
                    'observed_at' => now()->toDateString(),
                    'metadata' => [
                        'expected_brand' => 'Xbox',
                        'expected_region' => 'USA',
                    ],
                ],
                [
                    'query' => 'qzxjkv',
                    'source' => 'google_trends',
                    'volume' => 80,
                    'observed_at' => now()->toDateString(),
                    'query_type' => 'latent_demand',
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        $this->artisan('search-signals:import', ['path' => $path])
            ->expectsOutput('Imported 2 external search signal(s).')
            ->assertExitCode(0);
        $this->artisan('search-signals:import', ['path' => $path])
            ->expectsOutput('Imported 2 external search signal(s).')
            ->assertExitCode(0);

        $this->assertSame(2, ExternalSearchQuerySignal::count());
        $this->assertSame('latent_demand', ExternalSearchQuerySignal::where('normalized_query', 'qzxjkv')->firstOrFail()->metadata['query_type']);

        $exitCode = Artisan::call('search-signals:analyze', ['--limit' => 10, '--json' => true]);
        $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exitCode);
        $this->assertSame(2, $payload['summary']['queries_analyzed']);
        $this->assertSame(1, $payload['summary']['covered']);
        $this->assertSame(1, $payload['summary']['coverage_gaps']);
        $this->assertSame('COVERED', collect($payload['insights'])->firstWhere('query', 'xbox usa')['insight_type']);
        $this->assertSame('COVERAGE_GAP', collect($payload['insights'])->firstWhere('query', 'qzxjkv')['insight_type']);
        $this->assertTrue($profileTimestamp->equalTo($identity->searchProfile()->firstOrFail()->updated_at));

        $this->assertSame(0, Artisan::call('search-signals:recommend', ['--limit' => 10, '--json' => true]));
        $this->assertSame(0, Artisan::call('search-signals:recommend', ['--limit' => 10, '--json' => true]));

        $this->assertSame(1, SearchDemandRecommendation::count());
        $recommendation = SearchDemandRecommendation::firstOrFail();
        $this->assertSame('ADD_PRODUCT', $recommendation->type);
        $this->assertSame('qzxjkv', $recommendation->normalized_query);
        $this->assertSame('COVERAGE_GAP', $recommendation->insight_type);
        $this->assertSame(SearchDemandRecommendation::STATUS_PROPOSED, $recommendation->status);
        $this->assertGreaterThan(0, $recommendation->impact_score);
        $this->assertSame(80, $recommendation->evidence['demand_weight']);
        $this->assertTrue($profileTimestamp->equalTo($identity->searchProfile()->firstOrFail()->updated_at));

        $recommendation->update([
            'status' => SearchDemandRecommendation::STATUS_APPROVED,
            'decided_at' => now(),
        ]);

        $this->assertSame(0, Artisan::call('search-signals:recommend', ['--limit' => 10, '--json' => true]));
        $this->assertSame(SearchDemandRecommendation::STATUS_APPROVED, $recommendation->fresh()->status);
    }

    public function test_pull_imports_google_search_console_signals(): void
    {
        config([
            'services.google_search_console.access_token' => 'test-token',
            'services.google_search_console.site_url' => 'https://marketplace.one/',
        ]);

        Http::fake([
            'www.googleapis.com/*' => Http::response([
                'rows' => [
                    [
                        'keys' => ['xbox usa', 'usa', 'https://marketplace.one/store'],
                        'clicks' => 7,
                        'impressions' => 70,
                        'ctr' => 0.1,
                        'position' => 2.4,
                    ],
                ],
            ]),
        ]);

        $this->artisan('search-signals:pull', [
            'provider' => 'google_search_console',
            '--from' => '2026-05-01',
            '--to' => '2026-05-31',
            '--limit' => 10,
            '--json' => true,
        ])->assertExitCode(0);

        $signal = ExternalSearchQuerySignal::firstOrFail();
        $this->assertSame('google_search_console', $signal->source);
        $this->assertSame('xbox usa', $signal->normalized_query);
        $this->assertSame('USA', $signal->country);
        $this->assertSame('https://marketplace.one/store', $signal->landing_url);
        $this->assertSame(70, $signal->impressions);
        $this->assertSame(7, $signal->clicks);
        $this->assertSame(2.4, $signal->metadata['position']);
    }

    public function test_pull_imports_yandex_webmaster_signals(): void
    {
        config([
            'services.yandex_webmaster.oauth_token' => 'test-token',
            'services.yandex_webmaster.user_id' => '123',
            'services.yandex_webmaster.host_id' => 'https:marketplace.one:443',
        ]);

        Http::fake([
            'api.webmaster.yandex.net/*' => Http::response([
                'date_from' => '2026-05-01',
                'date_to' => '2026-05-31',
                'queries' => [
                    [
                        'query_id' => 'q1',
                        'query_text' => 'плейстейшн турция',
                        'indicators' => [
                            'TOTAL_SHOWS' => 55,
                            'TOTAL_CLICKS' => 6,
                        ],
                    ],
                ],
            ]),
        ]);

        $this->artisan('search-signals:pull', [
            'provider' => 'yandex_webmaster',
            '--from' => '2026-05-01',
            '--to' => '2026-05-31',
            '--limit' => 10,
            '--json' => true,
        ])->assertExitCode(0);

        $signal = ExternalSearchQuerySignal::firstOrFail();
        $this->assertSame('yandex_webmaster', $signal->source);
        $this->assertSame('плейстейшн турция', $signal->normalized_query);
        $this->assertSame(55, $signal->impressions);
        $this->assertSame(6, $signal->clicks);
        $this->assertSame('q1', $signal->metadata['query_id']);
    }

    public function test_pull_suggest_dry_run_maps_records_without_persisting(): void
    {
        Http::fake([
            'suggestqueries.google.com/*' => Http::response([
                'play',
                ['playstation turkey', 'playstation usa'],
            ]),
        ]);

        $this->artisan('search-signals:pull', [
            'provider' => 'google_suggest',
            '--query' => 'play',
            '--locale' => 'ru',
            '--limit' => 2,
            '--dry-run' => true,
            '--json' => true,
        ])->assertExitCode(0);

        $this->assertSame(0, ExternalSearchQuerySignal::count());
    }
}
