<?php

namespace App\Services\ZeroLayer;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

class GoogleSearchConsoleClient
{
    public function searchAnalytics(array $credentials, array $settings, Carbon|string $from, Carbon|string $to): array
    {
        $token = $credentials['access_token'] ?? $credentials['oauth_token'] ?? config('services.google_search_console.access_token');
        $siteUrl = $settings['site_url'] ?? $credentials['site_url'] ?? config('services.google_search_console.site_url');

        if (! $token || ! $siteUrl) {
            throw new \InvalidArgumentException('Google Search Console requires site_url and access_token.');
        }

        $baseUrl = rtrim((string) ($settings['base_url'] ?? config('services.google_search_console.base_url', 'https://www.googleapis.com')), '/');
        $response = Http::baseUrl($baseUrl)
            ->withToken($token)
            ->acceptJson()
            ->post('/webmasters/v3/sites/'.rawurlencode($siteUrl).'/searchAnalytics/query', [
                'startDate' => Carbon::parse($from)->toDateString(),
                'endDate' => Carbon::parse($to)->toDateString(),
                'dimensions' => ['date', 'query', 'page', 'country', 'device'],
                'rowLimit' => (int) ($settings['row_limit'] ?? 25000),
            ])
            ->throw()
            ->json();

        return collect($response['rows'] ?? [])->map(function (array $row): array {
            $keys = $row['keys'] ?? [];

            return [
                'source' => 'google_search_console',
                'signal_type' => 'search_query_page',
                'signal_date' => $keys[0] ?? now()->toDateString(),
                'query_text' => $keys[1] ?? null,
                'page_url' => $keys[2] ?? null,
                'country' => $keys[3] ?? null,
                'device' => $keys[4] ?? null,
                'impressions' => (float) ($row['impressions'] ?? 0),
                'clicks' => (float) ($row['clicks'] ?? 0),
                'ctr' => isset($row['ctr']) ? (float) $row['ctr'] : null,
                'position' => isset($row['position']) ? (float) $row['position'] : null,
                'payload' => $row,
            ];
        })->values()->all();
    }
}
