<?php

namespace App\Services\ZeroLayer;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class TikTokAdsClient
{
    private const DEFAULT_BASE_URL = 'https://business-api.tiktok.com/open_api/v1.3';

    /**
     * @param  array<int, string>  $advertiserIds
     * @return array<string, mixed>
     */
    public function advertiserInfo(array $credentials, array $settings, array $advertiserIds): array
    {
        return $this->request($credentials, $settings)
            ->get('/advertiser/info/', [
                'advertiser_ids' => json_encode(array_values($advertiserIds), JSON_THROW_ON_ERROR),
            ])
            ->throw()
            ->json();
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function integratedReport(array $credentials, array $settings, array $params): array
    {
        return $this->request($credentials, $settings)
            ->get('/report/integrated/get/', $this->reportParams($credentials, $settings, $params))
            ->throw()
            ->json();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function campaignReportSignals(array $credentials, array $settings, Carbon|string $from, Carbon|string $to): array
    {
        $response = $this->integratedReport($credentials, $settings, [
            'report_type' => 'BASIC',
            'service_type' => 'AUCTION',
            'data_level' => 'AUCTION_CAMPAIGN',
            'dimensions' => ['campaign_id', 'stat_time_day'],
            'metrics' => [
                'campaign_name',
                'spend',
                'impressions',
                'clicks',
                'conversion',
                'total_purchase_value',
                'video_play_actions',
                'video_watched_6s',
            ],
            'start_date' => Carbon::parse($from)->toDateString(),
            'end_date' => Carbon::parse($to)->toDateString(),
            'page' => $settings['page'] ?? 1,
            'page_size' => $settings['page_size'] ?? 1000,
        ]);

        return collect($response['data']['list'] ?? [])
            ->map(fn (array $row): array => $this->campaignReportSignal($row))
            ->values()
            ->all();
    }

    private function request(array $credentials, array $settings): PendingRequest
    {
        $token = $credentials['access_token']
            ?? $credentials['oauth_token']
            ?? config('services.tiktok_ads.access_token');

        if (! $token) {
            throw new InvalidArgumentException('TikTok Ads requires access_token.');
        }

        return Http::baseUrl(rtrim((string) ($settings['base_url'] ?? config('services.tiktok_ads.base_url', self::DEFAULT_BASE_URL)), '/'))
            ->acceptJson()
            ->withHeaders([
                'Access-Token' => $token,
            ]);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function reportParams(array $credentials, array $settings, array $params): array
    {
        $advertiserId = $params['advertiser_id']
            ?? $settings['advertiser_id']
            ?? $credentials['advertiser_id']
            ?? config('services.tiktok_ads.advertiser_id');

        if (! $advertiserId) {
            throw new InvalidArgumentException('TikTok Ads requires advertiser_id.');
        }

        return array_filter($params + [
            'advertiser_id' => (string) $advertiserId,
            'report_type' => 'BASIC',
            'service_type' => 'AUCTION',
            'page' => 1,
            'page_size' => 1000,
        ], fn ($value): bool => $value !== null);
    }

    /**
     * @return array<string, mixed>
     */
    private function campaignReportSignal(array $row): array
    {
        $dimensions = $row['dimensions'] ?? [];
        $metrics = $row['metrics'] ?? [];
        $spend = (float) ($metrics['spend'] ?? 0);
        $revenue = (float) ($metrics['total_purchase_value'] ?? $metrics['purchase_value'] ?? 0);

        return [
            'source' => 'tiktok_ads',
            'signal_type' => 'paid_social_campaign',
            'signal_date' => $dimensions['stat_time_day'] ?? now()->toDateString(),
            'campaign_id' => $dimensions['campaign_id'] ?? $metrics['campaign_id'] ?? null,
            'campaign' => $metrics['campaign_name'] ?? $dimensions['campaign_name'] ?? null,
            'impressions' => (float) ($metrics['impressions'] ?? 0),
            'clicks' => (float) ($metrics['clicks'] ?? 0),
            'cost' => $spend,
            'conversions' => (float) ($metrics['conversion'] ?? $metrics['conversions'] ?? 0),
            'revenue' => $revenue,
            'roas' => $spend > 0 ? round($revenue / $spend, 4) : null,
            'video_views' => (float) ($metrics['video_play_actions'] ?? 0),
            'video_watched_6s' => (float) ($metrics['video_watched_6s'] ?? 0),
            'payload' => $row,
        ];
    }
}
