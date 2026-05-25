<?php

namespace Tests\Feature;

use App\Services\ZeroLayer\YandexDirectClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class YandexDirectClientTest extends TestCase
{
    public function test_yandex_direct_client_calls_services_and_normalizes_report_signals(): void
    {
        Http::fake([
            'api.direct.yandex.com/json/v5/campaigns' => Http::response([
                'result' => [
                    'Campaigns' => [['Id' => 111, 'Name' => 'Steam TR Search']],
                ],
            ]),
            'api.direct.yandex.com/json/v5/reports' => Http::response(implode("\n", [
                "Date\tCampaignName\tAdGroupName\tCriteria\tDevice\tImpressions\tClicks\tCost\tConversions\tRevenue",
                "2026-05-24\tSteam TR Search\tSteam Turkey\tsteam turkey 100\tMOBILE\t300\t12\t4.5\t2\t18",
            ])),
        ]);

        $client = app(YandexDirectClient::class);
        $credentials = ['oauth_token' => 'direct-token'];
        $settings = ['client_login' => 'meanly-client'];

        $campaigns = $client->campaigns($credentials, $settings, [
            'SelectionCriteria' => [],
            'FieldNames' => ['Id', 'Name'],
        ]);
        $signals = $client->paidSearchSignals($credentials, $settings, '2026-05-01', '2026-05-24');

        $this->assertSame('Steam TR Search', $campaigns['result']['Campaigns'][0]['Name']);
        $this->assertSame('yandex_direct', $signals[0]['source']);
        $this->assertSame('steam turkey 100', $signals[0]['query_text']);
        $this->assertSame(4.0, $signals[0]['roas']);

        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer direct-token')
            && $request->hasHeader('Client-Login', 'meanly-client')
            && $request->hasHeader('processingMode', 'auto'));
    }
}
