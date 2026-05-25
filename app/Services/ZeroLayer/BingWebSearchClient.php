<?php

namespace App\Services\ZeroLayer;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class BingWebSearchClient
{
    private const DEFAULT_BASE_URL = 'https://api.bing.microsoft.com';

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function search(array $credentials, array $settings, string $query, array $params = []): array
    {
        return $this->request($credentials, $settings)
            ->get('/v7.0/search', array_filter([
                'q' => $query,
                'mkt' => $params['mkt'] ?? $settings['market'] ?? 'en-US',
                'count' => $params['count'] ?? $settings['count'] ?? 10,
                'offset' => $params['offset'] ?? null,
                'freshness' => $params['freshness'] ?? null,
                'responseFilter' => $params['response_filter'] ?? $params['responseFilter'] ?? 'Webpages',
                'safeSearch' => $params['safe_search'] ?? $params['safeSearch'] ?? 'Moderate',
                'textDecorations' => $params['text_decorations'] ?? $params['textDecorations'] ?? false,
                'textFormat' => $params['text_format'] ?? $params['textFormat'] ?? 'Raw',
            ], fn ($value): bool => $value !== null))
            ->throw()
            ->json();
    }

    /**
     * @param  array<int, string>  $queries
     * @return array<int, array<string, mixed>>
     */
    public function webResultSignals(array $credentials, array $settings, array $queries): array
    {
        $signals = [];

        foreach ($queries as $query) {
            $response = $this->search($credentials, $settings, $query);

            foreach (($response['webPages']['value'] ?? []) as $index => $row) {
                $signals[] = [
                    'source' => 'bing_web_search',
                    'signal_type' => 'serp_result',
                    'query_text' => $query,
                    'position' => $index + 1,
                    'page_url' => $row['url'] ?? null,
                    'title' => $row['name'] ?? null,
                    'snippet' => $row['snippet'] ?? null,
                    'external_id' => $row['id'] ?? null,
                    'payload' => $row,
                ];
            }
        }

        return $signals;
    }

    private function request(array $credentials, array $settings): PendingRequest
    {
        $key = $credentials['subscription_key']
            ?? $credentials['api_key']
            ?? $settings['subscription_key']
            ?? config('services.bing_web_search.subscription_key');

        if (! $key) {
            throw new InvalidArgumentException('Bing Web Search requires subscription_key.');
        }

        return Http::baseUrl(rtrim((string) ($settings['base_url'] ?? config('services.bing_web_search.base_url', self::DEFAULT_BASE_URL)), '/'))
            ->acceptJson()
            ->withHeaders([
                'Ocp-Apim-Subscription-Key' => $key,
            ]);
    }
}
