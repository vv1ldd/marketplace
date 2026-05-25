<?php

namespace App\Services\ZeroLayer;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class YahooSearchClient
{
    private const DEFAULT_BASE_URL = 'https://www.searchapi.io/api/v1';
    private const RESULTS_PER_PAGE = 7;

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function search(array $credentials, array $settings, string $query, array $params = []): array
    {
        return $this->request($credentials, $settings)
            ->get('/search', array_filter([
                'engine' => 'yahoo',
                'q' => $query,
                'location' => $params['location'] ?? $settings['location'] ?? null,
                'yahoo_domain' => $params['yahoo_domain'] ?? $settings['yahoo_domain'] ?? null,
                'safe' => $params['safe'] ?? $settings['safe'] ?? null,
                'time_period' => $params['time_period'] ?? $settings['time_period'] ?? null,
                'allowed_domains' => $params['allowed_domains'] ?? $settings['allowed_domains'] ?? null,
                'strict_match' => $params['strict_match'] ?? $settings['strict_match'] ?? null,
                'page' => $params['page'] ?? $settings['page'] ?? null,
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
        $page = (int) ($params['page'] ?? $settings['page'] ?? 1);
        $pageOffset = max(0, $page - 1) * self::RESULTS_PER_PAGE;

        foreach ($queries as $query) {
            $response = $this->search($credentials, $settings, $query, $params);

            foreach (($response['organic_results'] ?? []) as $index => $row) {
                $signals[] = [
                    'source' => 'yahoo_search',
                    'signal_type' => 'serp_result',
                    'query_text' => $query,
                    'position' => (int) ($row['position'] ?? ($pageOffset + $index + 1)),
                    'page_url' => $row['link'] ?? null,
                    'displayed_url' => $row['displayed_link'] ?? null,
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
            ?? config('services.yahoo_search.api_key');

        if (! $apiKey) {
            throw new InvalidArgumentException('Yahoo Search requires SearchApi api_key.');
        }

        return Http::baseUrl(rtrim((string) ($settings['base_url'] ?? config('services.yahoo_search.base_url', self::DEFAULT_BASE_URL)), '/'))
            ->acceptJson()
            ->withToken($apiKey);
    }
}
