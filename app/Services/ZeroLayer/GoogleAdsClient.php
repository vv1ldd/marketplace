<?php

namespace App\Services\ZeroLayer;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class GoogleAdsClient
{
    private const DEFAULT_BASE_URL = 'https://googleads.googleapis.com';
    private const DEFAULT_VERSION = 'v24';

    public function search(array $credentials, array $settings, string $query, ?string $pageToken = null): array
    {
        $customerId = $this->resolveCustomerId($credentials, $settings);

        return $this->request($credentials, $settings)
            ->post($this->path($settings, "/customers/{$customerId}/googleAds:search"), array_filter([
                'query' => $query,
                'page_token' => $pageToken,
            ], fn ($value): bool => $value !== null))
            ->throw()
            ->json();
    }

    public function searchStream(array $credentials, array $settings, string $query): array
    {
        $customerId = $this->resolveCustomerId($credentials, $settings);

        return $this->request($credentials, $settings)
            ->post($this->path($settings, "/customers/{$customerId}/googleAds:searchStream"), [
                'query' => $query,
            ])
            ->throw()
            ->json();
    }

    public function paidSearchSignals(array $credentials, array $settings, Carbon|string $from, Carbon|string $to): array
    {
        $query = $settings['query'] ?? $this->keywordPerformanceQuery($from, $to);
        $responses = $this->searchStream($credentials, $settings, $query);

        return collect($this->resultsFromStream($responses))
            ->map(fn (array $row): array => $this->paidSearchSignal($row))
            ->values()
            ->all();
    }

    private function request(array $credentials, array $settings): PendingRequest
    {
        $accessToken = $credentials['access_token']
            ?? $credentials['oauth_token']
            ?? config('services.google_ads.access_token');
        $developerToken = $credentials['developer_token']
            ?? $settings['developer_token']
            ?? config('services.google_ads.developer_token');

        if (! $accessToken || ! $developerToken) {
            throw new InvalidArgumentException('Google Ads requires access_token and developer_token.');
        }

        $headers = [
            'developer-token' => $developerToken,
        ];

        $loginCustomerId = $settings['login_customer_id']
            ?? $credentials['login_customer_id']
            ?? config('services.google_ads.login_customer_id');

        if ($loginCustomerId) {
            $headers['login-customer-id'] = $this->normalizeCustomerId((string) $loginCustomerId);
        }

        return Http::baseUrl(rtrim((string) ($settings['base_url'] ?? config('services.google_ads.base_url', self::DEFAULT_BASE_URL)), '/'))
            ->withToken($accessToken)
            ->acceptJson()
            ->asJson()
            ->withHeaders($headers);
    }

    private function path(array $settings, string $path): string
    {
        $version = trim((string) ($settings['api_version'] ?? config('services.google_ads.version', self::DEFAULT_VERSION)), '/');

        return "/{$version}".'/'.ltrim($path, '/');
    }

    private function resolveCustomerId(array $credentials, array $settings): string
    {
        $customerId = $settings['customer_id'] ?? $credentials['customer_id'] ?? config('services.google_ads.customer_id');

        if (! $customerId) {
            throw new InvalidArgumentException('Google Ads requires customer_id.');
        }

        return $this->normalizeCustomerId((string) $customerId);
    }

    private function normalizeCustomerId(string $customerId): string
    {
        return str_replace('-', '', $customerId);
    }

    private function keywordPerformanceQuery(Carbon|string $from, Carbon|string $to): string
    {
        $start = Carbon::parse($from)->toDateString();
        $end = Carbon::parse($to)->toDateString();

        return <<<GAQL
SELECT
  segments.date,
  segments.device,
  campaign.id,
  campaign.name,
  ad_group.id,
  ad_group.name,
  ad_group_criterion.keyword.text,
  metrics.impressions,
  metrics.clicks,
  metrics.cost_micros,
  metrics.conversions,
  metrics.conversions_value
FROM keyword_view
WHERE segments.date BETWEEN '{$start}' AND '{$end}'
GAQL;
    }

    private function resultsFromStream(array $responses): array
    {
        return collect($responses)
            ->flatMap(fn (array $chunk): array => $chunk['results'] ?? [])
            ->values()
            ->all();
    }

    private function paidSearchSignal(array $row): array
    {
        $metrics = $row['metrics'] ?? [];
        $cost = ((float) ($metrics['costMicros'] ?? 0)) / 1_000_000;
        $conversionValue = (float) ($metrics['conversionsValue'] ?? 0);

        return [
            'source' => 'google_ads',
            'signal_type' => 'paid_search_keyword',
            'signal_date' => $row['segments']['date'] ?? now()->toDateString(),
            'campaign_id' => $row['campaign']['id'] ?? null,
            'campaign' => $row['campaign']['name'] ?? null,
            'ad_group_id' => $row['adGroup']['id'] ?? null,
            'ad_group' => $row['adGroup']['name'] ?? null,
            'query_text' => $row['adGroupCriterion']['keyword']['text'] ?? null,
            'device' => $row['segments']['device'] ?? null,
            'impressions' => (float) ($metrics['impressions'] ?? 0),
            'clicks' => (float) ($metrics['clicks'] ?? 0),
            'cost' => $cost,
            'conversions' => (float) ($metrics['conversions'] ?? 0),
            'revenue' => $conversionValue,
            'roas' => $cost > 0 ? round($conversionValue / $cost, 4) : null,
            'payload' => $row,
        ];
    }
}
