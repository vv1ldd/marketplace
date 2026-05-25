<?php

namespace Tests\Feature;

use App\Services\ZeroLayer\BingWebSearchClient;
use App\Services\ZeroLayer\GoogleAdsClient;
use App\Services\ZeroLayer\GoogleAnalyticsDataClient;
use App\Services\ZeroLayer\GoogleSearchConsoleClient;
use App\Services\ZeroLayer\IndexNowClient;
use App\Services\ZeroLayer\YandexWebmasterClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ZeroLayerSdkClientsTest extends TestCase
{
    public function test_yandex_webmaster_client_uses_oauth_scheme_and_reads_core_resources(): void
    {
        Http::fake([
            'api.webmaster.yandex.net/v4/user' => Http::response(['user_id' => 777]),
            'api.webmaster.yandex.net/v4/user/777/hosts' => Http::response([
                'hosts' => [[
                    'host_id' => 'https:meanly.test:443',
                    'ascii_host_url' => 'https://meanly.test/',
                    'verified' => true,
                ]],
            ]),
            'api.webmaster.yandex.net/v4/user/777/hosts/https:meanly.test:443/verification*' => Http::response([
                'verification_uin' => 'b01288afe67b1595',
                'verification_state' => 'IN_PROGRESS',
                'verification_type' => 'META_TAG',
                'applicable_verifiers' => ['META_TAG'],
            ]),
            'api.webmaster.yandex.net/v4/user/777/hosts/https:meanly.test:443/search-queries/popular*' => Http::response([
                'date_to' => '2026-05-24',
                'queries' => [[
                    'query_id' => 'q-1',
                    'query_text' => 'steam turkey 100',
                    'indicators' => [
                        'TOTAL_SHOWS' => 300,
                        'TOTAL_CLICKS' => 12,
                        'AVG_SHOW_POSITION' => 4.2,
                    ],
                ]],
            ]),
        ]);

        $client = app(YandexWebmasterClient::class);
        $credentials = ['oauth_token' => 'ya-token'];
        $settings = ['host_id' => 'https:meanly.test:443'];

        $this->assertSame(777, $client->user($credentials)['user_id']);
        $this->assertSame('https:meanly.test:443', $client->hosts($credentials, $settings)['hosts'][0]['host_id']);
        $this->assertSame('IN_PROGRESS', $client->startVerification($credentials, $settings, 'META_TAG')['verification_state']);

        $signals = $client->popularQueries($credentials, $settings, '2026-05-01', '2026-05-24');
        $this->assertSame('yandex_webmaster', $signals[0]['source']);
        $this->assertSame('steam turkey 100', $signals[0]['query_text']);
        $this->assertSame(300.0, $signals[0]['impressions']);

        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'OAuth ya-token'));
    }

    public function test_yandex_webmaster_client_covers_indexing_recrawl_links_and_feeds(): void
    {
        Http::fake([
            'api.webmaster.yandex.net/v4/user/777/hosts/https:meanly.test:443/summary' => Http::response([
                'sqi' => 12,
                'excluded_pages_count' => 3,
                'searchable_pages_count' => 150,
                'site_problems' => ['CRITICAL' => 1],
            ]),
            'api.webmaster.yandex.net/v4/user/777/hosts/https:meanly.test:443/search-urls/events/history*' => Http::response([
                'indicators' => [
                    'APPEARED_IN_SEARCH' => [['date' => '2026-05-24T00:00:00,000+0300', 'value' => 4]],
                ],
            ]),
            'api.webmaster.yandex.net/v4/user/777/hosts/https:meanly.test:443/recrawl/queue' => Http::response([
                'task_id' => 'task-1',
                'quota_remainder' => 9,
            ], 202),
            'api.webmaster.yandex.net/v4/user/777/hosts/https:meanly.test:443/links/external/history*' => Http::response([
                'indicators' => [
                    'LINKS_TOTAL_COUNT' => [['date' => '2026-05-24T00:00:00,000+0300', 'value' => 7]],
                ],
            ]),
            'api.webmaster.yandex.net/v4/user/777/hosts/https:meanly.test:443/news-feeds/set-enabled/' => Http::response([], 200),
        ]);

        $client = app(YandexWebmasterClient::class);
        $credentials = ['oauth_token' => 'ya-token', 'user_id' => 777];
        $settings = ['host_id' => 'https:meanly.test:443'];

        $this->assertSame(150, $client->summary($credentials, $settings)['searchable_pages_count']);
        $this->assertSame(4, $client->searchUrlsEventsHistory($credentials, $settings)['indicators']['APPEARED_IN_SEARCH'][0]['value']);
        $this->assertSame('task-1', $client->submitRecrawl($credentials, $settings, 'https://meanly.test/catalog')['task_id']);
        $this->assertSame(7, $client->externalLinksHistory($credentials, $settings)['indicators']['LINKS_TOTAL_COUNT'][0]['value']);
        $this->assertSame([], $client->setNewsFeedEnabled($credentials, $settings, 'https://meanly.test/rss.xml', true));

        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.webmaster.yandex.net/v4/user/777/hosts/https:meanly.test:443/news-feeds/set-enabled/'
            && $request->data() === ['url' => 'https://meanly.test/rss.xml', 'isEnabled' => true]);
    }

    public function test_google_analytics_data_client_uses_ga4_run_report_shape_and_pagination(): void
    {
        Http::fake([
            'analyticsdata.googleapis.com/v1beta/properties/123:runReport' => Http::sequence()
                ->push([
                    'rows' => [[
                        'dimensionValues' => [['value' => 'page-a']],
                        'metricValues' => [['value' => '10']],
                    ]],
                    'rowCount' => 2,
                ])
                ->push([
                    'rows' => [[
                        'dimensionValues' => [['value' => 'page-b']],
                        'metricValues' => [['value' => '20']],
                    ]],
                    'rowCount' => 2,
                ]),
            'analyticsdata.googleapis.com/v1beta/properties/123:runRealtimeReport' => Http::response([
                'rows' => [[
                    'dimensionValues' => [['value' => 'Turkey']],
                    'metricValues' => [['value' => '3']],
                ]],
            ]),
            'analyticsdata.googleapis.com/v1beta/properties/123/metadata' => Http::response([
                'dimensions' => [['apiName' => 'country']],
            ]),
        ]);

        $client = app(GoogleAnalyticsDataClient::class);
        $credentials = ['access_token' => 'ga-token'];
        $settings = ['property_id' => '123'];
        $body = [
            'dateRanges' => [['startDate' => '2026-05-01', 'endDate' => '2026-05-24']],
            'dimensions' => [['name' => 'landingPagePlusQueryString']],
            'metrics' => [['name' => 'sessions']],
        ];

        $response = $client->runReportWithPagination($credentials, $settings, $body, 1);
        $this->assertCount(2, $response['rows']);
        $this->assertSame('page-b', $response['rows'][1]['dimensionValues'][0]['value']);

        $this->assertSame('3', $client->runRealtimeReport($credentials, $settings, [
            'dimensions' => [['name' => 'country']],
            'metrics' => [['name' => 'activeUsers']],
        ])['rows'][0]['metricValues'][0]['value']);

        $this->assertSame('country', $client->metadata($credentials, $settings)['dimensions'][0]['apiName']);

        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer ga-token'));
    }

    public function test_bing_web_search_client_returns_serp_signals_for_discovery_coverage(): void
    {
        Http::fake([
            'api.bing.microsoft.com/v7.0/search*' => Http::response([
                'webPages' => [
                    'value' => [[
                        'id' => 'https://api.bing.microsoft.com/api/v7/#WebPages.0',
                        'name' => 'Steam Turkey 100 TRY',
                        'url' => 'https://meanly.test/catalog/products/steam-turkey-100',
                        'snippet' => 'Buy Steam Turkey 100 TRY digital gift card.',
                    ]],
                ],
            ]),
        ]);

        $signals = app(BingWebSearchClient::class)->webResultSignals(
            ['subscription_key' => 'bing-key'],
            ['market' => 'en-US', 'count' => 5],
            ['steam turkey 100'],
        );

        $this->assertCount(1, $signals);
        $this->assertSame('bing_web_search', $signals[0]['source']);
        $this->assertSame('serp_result', $signals[0]['signal_type']);
        $this->assertSame(1, $signals[0]['position']);
        $this->assertSame('https://meanly.test/catalog/products/steam-turkey-100', $signals[0]['page_url']);

        Http::assertSent(fn ($request): bool => $request->hasHeader('Ocp-Apim-Subscription-Key', 'bing-key')
            && str_contains($request->url(), 'q=steam%20turkey%20100'));
    }

    public function test_google_search_console_client_normalizes_search_analytics_rows(): void
    {
        Http::fake([
            'www.googleapis.com/webmasters/v3/sites/*/searchAnalytics/query' => Http::response([
                'rows' => [[
                    'keys' => [
                        '2026-05-24',
                        'steam turkey 100',
                        'https://meanly.test/catalog/products/steam-turkey-100',
                        'usa',
                        'MOBILE',
                    ],
                    'clicks' => 12,
                    'impressions' => 300,
                    'ctr' => 0.04,
                    'position' => 4.2,
                ]],
            ]),
        ]);

        $signals = app(GoogleSearchConsoleClient::class)->searchAnalytics(
            ['access_token' => 'gsc-token'],
            ['site_url' => 'https://meanly.test/'],
            '2026-05-01',
            '2026-05-24',
        );

        $this->assertCount(1, $signals);
        $this->assertSame('google_search_console', $signals[0]['source']);
        $this->assertSame('search_query_page', $signals[0]['signal_type']);
        $this->assertSame('steam turkey 100', $signals[0]['query_text']);
        $this->assertSame('https://meanly.test/catalog/products/steam-turkey-100', $signals[0]['page_url']);
        $this->assertSame(300.0, $signals[0]['impressions']);

        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer gsc-token')
            && str_contains($request->url(), rawurlencode('https://meanly.test/'))
            && $request->data()['dimensions'] === ['date', 'query', 'page', 'country', 'device']);
    }

    public function test_indexnow_client_submits_urls_and_returns_index_push_signals(): void
    {
        Http::fake([
            'api.indexnow.org/indexnow' => Http::response('', 202),
        ]);

        $signals = app(IndexNowClient::class)->submit(
            ['key' => 'index-key'],
            ['host' => 'meanly.test'],
            [
                'https://meanly.test/store',
                'https://meanly.test/store',
                'https://meanly.test/catalog/products/steam-turkey-100',
            ],
        );

        $this->assertCount(2, $signals);
        $this->assertSame('indexnow', $signals[0]['source']);
        $this->assertSame('index_push', $signals[0]['signal_type']);
        $this->assertSame('meanly.test', $signals[0]['external_id']);

        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.indexnow.org/indexnow'
            && $request->data()['host'] === 'meanly.test'
            && $request->data()['key'] === 'index-key'
            && count($request->data()['urlList']) === 2);
    }

    public function test_google_ads_client_runs_gaql_search_stream_and_normalizes_paid_signals(): void
    {
        Http::fake([
            'googleads.googleapis.com/v24/customers/1234567890/googleAds:searchStream' => Http::response([[
                'results' => [[
                    'segments' => [
                        'date' => '2026-05-24',
                        'device' => 'MOBILE',
                    ],
                    'campaign' => [
                        'id' => '111',
                        'name' => 'Steam TR Search',
                    ],
                    'adGroup' => [
                        'id' => '222',
                        'name' => 'Steam Turkey',
                    ],
                    'adGroupCriterion' => [
                        'keyword' => [
                            'text' => 'steam turkey 100',
                        ],
                    ],
                    'metrics' => [
                        'impressions' => '300',
                        'clicks' => '12',
                        'costMicros' => '4500000',
                        'conversions' => '2',
                        'conversionsValue' => '18',
                    ],
                ]],
            ]]),
        ]);

        $signals = app(GoogleAdsClient::class)->paidSearchSignals(
            [
                'access_token' => 'ads-token',
                'developer_token' => 'dev-token',
                'customer_id' => '123-456-7890',
            ],
            ['login_customer_id' => '999-888-7777'],
            '2026-05-01',
            '2026-05-24',
        );

        $this->assertCount(1, $signals);
        $this->assertSame('google_ads', $signals[0]['source']);
        $this->assertSame('paid_search_keyword', $signals[0]['signal_type']);
        $this->assertSame('steam turkey 100', $signals[0]['query_text']);
        $this->assertSame(4.5, $signals[0]['cost']);
        $this->assertSame(4.0, $signals[0]['roas']);

        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer ads-token')
            && $request->hasHeader('developer-token', 'dev-token')
            && $request->hasHeader('login-customer-id', '9998887777')
            && str_contains((string) $request->data()['query'], 'FROM keyword_view'));
    }
}
