<?php

namespace Tests\Feature;

use App\Services\ZeroLayer\YahooSearchClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class YahooSearchClientTest extends TestCase
{
    public function test_yahoo_search_client_returns_organic_serp_signals(): void
    {
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
        ]);

        $signals = app(YahooSearchClient::class)->organicResultSignals(
            ['api_key' => 'searchapi-key'],
            ['location' => 'United States', 'safe' => 'moderate'],
            ['steam turkey 100'],
            ['page' => 1],
        );

        $this->assertSame('yahoo_search', $signals[0]['source']);
        $this->assertSame('serp_result', $signals[0]['signal_type']);
        $this->assertSame('steam turkey 100', $signals[0]['query_text']);
        $this->assertSame(1, $signals[0]['position']);
        $this->assertSame('https://meanly.test/catalog/products/steam-turkey-100', $signals[0]['page_url']);

        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer searchapi-key')
            && str_contains($request->url(), 'engine=yahoo')
            && str_contains($request->url(), 'q=steam%20turkey%20100')
            && str_contains($request->url(), 'location=United%20States')
            && str_contains($request->url(), 'safe=moderate'));
    }
}
