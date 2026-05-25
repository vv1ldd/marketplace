<?php

namespace Tests\Feature;

use App\Services\ZeroLayer\TikTokAdsClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TikTokAdsClientTest extends TestCase
{
    public function test_tiktok_ads_client_reads_advertiser_info_and_normalizes_campaign_report_signals(): void
    {
        Http::fake([
            'business-api.tiktok.com/open_api/v1.3/advertiser/info/*' => Http::response([
                'code' => 0,
                'data' => [
                    'list' => [[
                        'advertiser_id' => '123456789',
                        'name' => 'Meanly',
                    ]],
                ],
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
                            'video_watched_6s' => '45',
                        ],
                    ]],
                ],
            ]),
        ]);

        $client = app(TikTokAdsClient::class);
        $credentials = ['access_token' => 'tiktok-token', 'advertiser_id' => '123456789'];

        $advertiser = $client->advertiserInfo($credentials, [], ['123456789']);
        $signals = $client->campaignReportSignals($credentials, [], '2026-05-01', '2026-05-24');

        $this->assertSame('Meanly', $advertiser['data']['list'][0]['name']);
        $this->assertSame('tiktok_ads', $signals[0]['source']);
        $this->assertSame('paid_social_campaign', $signals[0]['signal_type']);
        $this->assertSame('Steam TR Video', $signals[0]['campaign']);
        $this->assertSame(4.5, $signals[0]['cost']);
        $this->assertSame(4.0, $signals[0]['roas']);
        $this->assertSame(120.0, $signals[0]['video_views']);

        Http::assertSent(fn ($request): bool => $request->hasHeader('Access-Token', 'tiktok-token')
            && str_contains($request->url(), '/open_api/v1.3/report/integrated/get/')
            && str_contains($request->url(), 'advertiser_id=123456789')
            && str_contains($request->url(), 'data_level=AUCTION_CAMPAIGN')
            && str_contains($request->url(), 'start_date=2026-05-01')
            && str_contains($request->url(), 'end_date=2026-05-24'));
    }
}
