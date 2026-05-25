<?php

namespace Tests\Feature;

use App\Models\ZeroLayerIntegration;
use App\Models\ZeroLayerSignal;
use App\Services\ZeroLayer\ZeroLayerIngestionService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ZeroLayerIngestionServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('zero_layer_signals');
        Schema::dropIfExists('zero_layer_integrations');

        $migration = require database_path('migrations/2026_05_25_034600_create_zero_layer_tables.php');
        $migration->up();
    }

    protected function tearDown(): void
    {
        $migration = require database_path('migrations/2026_05_25_034600_create_zero_layer_tables.php');
        $migration->down();

        parent::tearDown();
    }

    public function test_zero_layer_ingestion_persists_search_and_paid_signals_idempotently(): void
    {
        ZeroLayerIntegration::create([
            'name' => 'Yahoo Search',
            'source' => 'yahoo_search',
            'status' => 'active',
            'credentials' => ['api_key' => 'searchapi-key'],
            'settings' => [
                'queries' => ['steam turkey 100'],
                'location' => 'United States',
            ],
        ]);

        ZeroLayerIntegration::create([
            'name' => 'TikTok Ads',
            'source' => 'tiktok_ads',
            'status' => 'active',
            'credentials' => [
                'access_token' => 'tiktok-token',
                'advertiser_id' => '123456789',
            ],
        ]);

        Http::fake([
            'www.searchapi.io/api/v1/search*' => Http::response([
                'organic_results' => [[
                    'position' => 1,
                    'title' => 'Steam Turkey 100 TRY',
                    'link' => 'https://meanly.test/catalog/products/steam-turkey-100',
                    'displayed_link' => 'meanly.test',
                    'snippet' => 'Buy Steam Turkey 100 TRY digital gift card.',
                ]],
            ]),
            'business-api.tiktok.com/open_api/v1.3/report/integrated/get/*' => Http::response([
                'code' => 0,
                'data' => [
                    'list' => [[
                        'dimensions' => [
                            'campaign_id' => '111',
                            'stat_time_day' => '2026-05-24',
                        ],
                        'metrics' => [
                            'campaign_name' => 'Steam TR Video',
                            'spend' => '4.50',
                            'impressions' => '300',
                            'clicks' => '12',
                            'conversion' => '2',
                            'total_purchase_value' => '18.00',
                            'video_play_actions' => '120',
                        ],
                    ]],
                ],
            ]),
        ]);

        $service = app(ZeroLayerIngestionService::class);

        $firstRun = $service->sync(null, '2026-05-01', '2026-05-24');
        $secondRun = $service->sync(null, '2026-05-01', '2026-05-24');

        $this->assertSame(1, $firstRun[0]['signals_count']);
        $this->assertSame(1, $firstRun[1]['signals_count']);
        $this->assertSame(1, $secondRun[0]['signals_count']);
        $this->assertSame(1, $secondRun[1]['signals_count']);
        $this->assertDatabaseCount('zero_layer_signals', 2);

        $this->assertDatabaseHas('zero_layer_signals', [
            'source' => 'yahoo_search',
            'signal_type' => 'serp_result',
            'title' => 'Steam Turkey 100 TRY',
        ]);

        $this->assertDatabaseHas('zero_layer_signals', [
            'source' => 'tiktok_ads',
            'signal_type' => 'paid_social_campaign',
            'campaign' => 'Steam TR Video',
        ]);

        $this->assertSame('4.0000', ZeroLayerSignal::where('source', 'tiktok_ads')->firstOrFail()->roas);
        $this->assertNotNull(ZeroLayerIntegration::where('source', 'yahoo_search')->firstOrFail()->last_synced_at);
    }
}
