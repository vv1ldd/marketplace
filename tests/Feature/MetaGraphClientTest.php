<?php

namespace Tests\Feature;

use App\Services\ZeroLayer\MetaGraphClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MetaGraphClientTest extends TestCase
{
    public function test_meta_graph_client_reads_campaigns_and_normalizes_paid_social_signals(): void
    {
        Http::fake([
            'graph.facebook.com/v25.0/act_123456789/campaigns*' => Http::response([
                'data' => [[
                    'id' => '111',
                    'name' => 'Steam TR Social',
                    'status' => 'ACTIVE',
                ]],
            ]),
            'graph.facebook.com/v25.0/act_123456789/insights*' => Http::response([
                'data' => [[
                    'date_start' => '2026-05-24',
                    'date_stop' => '2026-05-24',
                    'campaign_id' => '111',
                    'campaign_name' => 'Steam TR Social',
                    'impressions' => '300',
                    'clicks' => '12',
                    'inline_link_clicks' => '9',
                    'spend' => '4.50',
                    'actions' => [[
                        'action_type' => 'purchase',
                        'value' => '2',
                    ]],
                    'action_values' => [[
                        'action_type' => 'purchase',
                        'value' => '18.00',
                    ]],
                    'purchase_roas' => [[
                        'action_type' => 'omni_purchase',
                        'value' => '4',
                    ]],
                ]],
            ]),
        ]);

        $client = app(MetaGraphClient::class);
        $credentials = ['access_token' => 'meta-token', 'ad_account_id' => '123456789'];

        $campaigns = $client->campaigns($credentials, []);
        $signals = $client->paidSocialSignals($credentials, [], '2026-05-01', '2026-05-24');

        $this->assertSame('Steam TR Social', $campaigns['data'][0]['name']);
        $this->assertSame('meta_ads', $signals[0]['source']);
        $this->assertSame('paid_social_campaign', $signals[0]['signal_type']);
        $this->assertSame('Steam TR Social', $signals[0]['campaign']);
        $this->assertSame(4.5, $signals[0]['cost']);
        $this->assertSame(4.0, $signals[0]['roas']);

        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer meta-token')
            && str_contains($request->url(), '/v25.0/act_123456789/insights')
            && str_contains($request->url(), 'fields=')
            && str_contains($request->url(), 'time_range%5Bsince%5D=2026-05-01')
            && str_contains($request->url(), 'time_range%5Buntil%5D=2026-05-24'));
    }
}
