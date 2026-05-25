<?php

namespace App\Services\ZeroLayer;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class GoogleAnalyticsDataClient
{
    private const DEFAULT_BASE_URL = 'https://analyticsdata.googleapis.com';

    public function landingPages(array $credentials, array $settings, Carbon|string $from, Carbon|string $to): array
    {
        $response = $this->runReportWithPagination($credentials, $settings, [
            'dateRanges' => [[
                'startDate' => Carbon::parse($from)->toDateString(),
                'endDate' => Carbon::parse($to)->toDateString(),
            ]],
            'dimensions' => [
                ['name' => 'date'],
                ['name' => 'landingPagePlusQueryString'],
                ['name' => 'sessionDefaultChannelGroup'],
            ],
            'metrics' => [
                ['name' => 'sessions'],
                ['name' => 'conversions'],
                ['name' => 'totalRevenue'],
            ],
            'limit' => (string) ($settings['row_limit'] ?? 10000),
        ]);

        return collect($response['rows'] ?? [])->map(function (array $row): array {
            $dimensions = collect($row['dimensionValues'] ?? [])->pluck('value')->all();
            $metrics = collect($row['metricValues'] ?? [])->pluck('value')->all();
            $path = $dimensions[1] ?? null;

            return [
                'source' => 'google_analytics',
                'signal_type' => 'landing_page',
                'signal_date' => isset($dimensions[0]) ? Carbon::createFromFormat('Ymd', $dimensions[0])->toDateString() : now()->toDateString(),
                'page_url' => $path ? rtrim((string) config('app.url'), '/').$path : null,
                'external_id' => $dimensions[2] ?? null,
                'sessions' => (float) ($metrics[0] ?? 0),
                'conversions' => (float) ($metrics[1] ?? 0),
                'revenue' => (float) ($metrics[2] ?? 0),
                'currency' => config('app.currency', 'RUB'),
                'payload' => $row,
            ];
        })->values()->all();
    }

    /**
     * Runs a GA4 Data API report using the REST shape from Google's PHP docs samples.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function runReport(array $credentials, array $settings, array $body): array
    {
        $propertyId = $this->resolvePropertyId($credentials, $settings);

        return $this->post($credentials, $settings, "/v1beta/properties/{$propertyId}:runReport", $body);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function runReportWithPagination(array $credentials, array $settings, array $body, ?int $pageSize = null): array
    {
        $pageSize ??= (int) ($body['limit'] ?? $settings['row_limit'] ?? 10000);
        $offset = (int) ($body['offset'] ?? 0);
        $rows = [];
        $lastResponse = [];

        do {
            $pageBody = $body + [];
            $pageBody['limit'] = (string) $pageSize;
            $pageBody['offset'] = (string) $offset;

            $lastResponse = $this->runReport($credentials, $settings, $pageBody);
            $pageRows = $lastResponse['rows'] ?? [];
            $rows = array_merge($rows, $pageRows);
            $offset += count($pageRows);
            $rowCount = (int) ($lastResponse['rowCount'] ?? count($rows));
        } while (count($pageRows) > 0 && count($rows) < $rowCount);

        $lastResponse['rows'] = $rows;
        $lastResponse['rowCount'] = $lastResponse['rowCount'] ?? count($rows);

        return $lastResponse;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function runRealtimeReport(array $credentials, array $settings, array $body): array
    {
        $propertyId = $this->resolvePropertyId($credentials, $settings);

        return $this->post($credentials, $settings, "/v1beta/properties/{$propertyId}:runRealtimeReport", $body);
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(array $credentials, array $settings): array
    {
        $propertyId = $this->resolvePropertyId($credentials, $settings);

        return $this->get($credentials, $settings, "/v1beta/properties/{$propertyId}/metadata");
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function checkCompatibility(array $credentials, array $settings, array $body): array
    {
        $propertyId = $this->resolvePropertyId($credentials, $settings);

        return $this->post($credentials, $settings, "/v1beta/properties/{$propertyId}:checkCompatibility", $body);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function post(array $credentials, array $settings, string $path, array $body): array
    {
        return $this->request($credentials, $settings)
            ->post($path, $body)
            ->throw()
            ->json();
    }

    /**
     * @return array<string, mixed>
     */
    private function get(array $credentials, array $settings, string $path): array
    {
        return $this->request($credentials, $settings)
            ->get($path)
            ->throw()
            ->json();
    }

    private function request(array $credentials, array $settings): PendingRequest
    {
        $token = $credentials['access_token']
            ?? $credentials['oauth_token']
            ?? config('services.google_analytics.access_token');

        if (! $token) {
            throw new InvalidArgumentException('GA4 requires access_token.');
        }

        return Http::baseUrl(rtrim((string) ($settings['base_url'] ?? config('services.google_analytics.base_url', self::DEFAULT_BASE_URL)), '/'))
            ->withToken($token)
            ->acceptJson()
            ->asJson();
    }

    private function resolvePropertyId(array $credentials, array $settings): string
    {
        $propertyId = $settings['property_id'] ?? $credentials['property_id'] ?? config('services.google_analytics.property_id');

        if (! $propertyId) {
            throw new InvalidArgumentException('GA4 requires property_id.');
        }

        return (string) $propertyId;
    }
}
