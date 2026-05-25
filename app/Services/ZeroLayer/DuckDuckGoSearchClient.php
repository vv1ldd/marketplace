<?php

namespace App\Services\ZeroLayer;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class DuckDuckGoSearchClient
{
    private const DEFAULT_BASE_URL = 'https://www.searchapi.io/api/v1';

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function search(array $credentials, array $settings, string $query, array $params = []): array
    {
        return $this->request($credentials, $settings)
            ->get('/search', array_filter([
                'engine' => 'duckduckgo',
                'q' => $query,
                'locale' => $params['locale'] ?? $settings['locale'] ?? null,
                'safe' => $params['safe'] ?? $settings['safe'] ?? null,
                'time_period' => $params['time_period'] ?? $settings['time_period'] ?? null,
                'next_page_token' => $params['next_page_token'] ?? $settings['next_page_token'] ?? null,
                'zero_retention' => $params['zero_retention'] ?? $settings['zero_retention'] ?? null,
            ], fn ($value): bool => $value !== null))
            ->throw()
            ->json();
    }

    /**
     * @param  array<int, string>  $queries
     * @return array<int, array<string, mixed>>
     */
    public function organicResultSignals(array $credentials, array $settings, array $queries, array $params = []): array
    {
        $signals = [];

        foreach ($queries as $query) {
            $response = $this->search($credentials, $settings, $query, $params);

            foreach (($response['organic_results'] ?? []) as $index => $row) {
                $signals[] = [
                    'source' => 'duckduckgo_search',
                    'signal_type' => 'serp_result',
                    'query_text' => $query,
                    'position' => (int) ($row['position'] ?? ($index + 1)),
                    'page_url' => $row['link'] ?? null,
                    'title' => $row['title'] ?? null,
                    'snippet' => $row['snippet'] ?? null,
                    'payload' => $row,
                ];
            }
        }

        return $signals;
    }

    private function request(array $credentials, array $settings): PendingRequest
    {
        $apiKey = $credentials['api_key']
            ?? $credentials['searchapi_key']
            ?? $settings['api_key']
            ?? config('services.duckduckgo_search.api_key');

        if (! $apiKey) {
            throw new InvalidArgumentException('DuckDuckGo Search requires SearchApi api_key.');
        }

        return Http::baseUrl(rtrim((string) ($settings['base_url'] ?? config('services.duckduckgo_search.base_url', self::DEFAULT_BASE_URL)), '/'))
            ->acceptJson()
            ->withToken($apiKey);
    }
}
