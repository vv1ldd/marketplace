<?php

namespace Tests\Feature;

use App\Services\ZeroLayer\DuckDuckGoSearchClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DuckDuckGoSearchClientTest extends TestCase
{
    public function test_duckduckgo_search_client_returns_organic_serp_signals(): void
    {
        Http::fake([
            'www.searchapi.io/api/v1/search*' => Http::response([
                'organic_results' => [[
                    'position' => 1,
                    'title' => 'Steam Turkey 100 TRY',
                    'link' => 'https://meanly.test/catalog/products/steam-turkey-100',
                    'snippet' => 'Buy Steam Turkey 100 TRY digital gift card.',
                    'favicon' => 'https://external-content.duckduckgo.com/ip3/meanly.test.ico',
                ]],
                'pagination' => [
                    'next_page_token' => 'next-page-token',
                ],
            ]),
        ]);

        $signals = app(DuckDuckGoSearchClient::class)->organicResultSignals(
            ['api_key' => 'searchapi-key'],
            ['locale' => 'us-en', 'safe' => 'moderate'],
            ['steam turkey 100'],
            ['time_period' => 'past_month'],
        );

        $this->assertSame('duckduckgo_search', $signals[0]['source']);
        $this->assertSame('serp_result', $signals[0]['signal_type']);
        $this->assertSame('steam turkey 100', $signals[0]['query_text']);
        $this->assertSame(1, $signals[0]['position']);
        $this->assertSame('https://meanly.test/catalog/products/steam-turkey-100', $signals[0]['page_url']);

        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer searchapi-key')
            && str_contains($request->url(), 'engine=duckduckgo')
            && str_contains($request->url(), 'q=steam%20turkey%20100')
            && str_contains($request->url(), 'locale=us-en')
            && str_contains($request->url(), 'safe=moderate')
            && str_contains($request->url(), 'time_period=past_month'));
    }
}
