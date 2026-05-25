<?php

namespace App\Services\ZeroLayer;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class MetaGraphClient
{
    private const DEFAULT_BASE_URL = 'https://graph.facebook.com';
    private const DEFAULT_VERSION = 'v25.0';

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function get(array $credentials, array $settings, string $path, array $params = []): array
    {
        return $this->request($credentials, $settings)
            ->get($this->versionedPath($settings, $path), $params)
            ->throw()
            ->json();
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function post(array $credentials, array $settings, string $path, array $params = []): array
    {
        return $this->request($credentials, $settings)
            ->post($this->versionedPath($settings, $path), $params)
            ->throw()
            ->json();
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function adAccountInsights(array $credentials, array $settings, array $params = []): array
    {
        $accountId = $this->resolveAdAccountId($credentials, $settings);

        return $this->get($credentials, $settings, "/{$accountId}/insights", $params + [
            'fields' => implode(',', $this->defaultInsightFields()),
            'level' => $settings['level'] ?? 'campaign',
        ]);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function campaigns(array $credentials, array $settings, array $params = []): array
    {
        $accountId = $this->resolveAdAccountId($credentials, $settings);

        return $this->get($credentials, $settings, "/{$accountId}/campaigns", $params + [
            'fields' => 'id,name,status,effective_status,objective,created_time,updated_time',
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function paidSocialSignals(array $credentials, array $settings, Carbon|string $from, Carbon|string $to): array
    {
        $response = $this->adAccountInsights($credentials, $settings, [
            'time_range' => [
                'since' => Carbon::parse($from)->toDateString(),
                'until' => Carbon::parse($to)->toDateString(),
            ],
            'time_increment' => $settings['time_increment'] ?? 1,
        ]);

        return collect($response['data'] ?? [])
            ->map(fn (array $row): array => $this->paidSocialSignal($row))
            ->values()
            ->all();
    }

    private function request(array $credentials, array $settings): PendingRequest
    {
        $token = $credentials['access_token']
            ?? $credentials['oauth_token']
            ?? config('services.meta_graph.access_token');

        if (! $token) {
            throw new InvalidArgumentException('Meta Graph requires access_token.');
        }

        return Http::baseUrl(rtrim((string) ($settings['base_url'] ?? config('services.meta_graph.base_url', self::DEFAULT_BASE_URL)), '/'))
            ->withToken($token)
            ->acceptJson()
            ->asJson();
    }

    private function versionedPath(array $settings, string $path): string
    {
        $version = trim((string) ($settings['version'] ?? config('services.meta_graph.version', self::DEFAULT_VERSION)), '/');

        return "/{$version}/".ltrim($path, '/');
    }

    private function resolveAdAccountId(array $credentials, array $settings): string
    {
        $accountId = $settings['ad_account_id']
            ?? $credentials['ad_account_id']
            ?? config('services.meta_graph.ad_account_id');

        if (! $accountId) {
            throw new InvalidArgumentException('Meta Graph requires ad_account_id.');
        }

        $accountId = (string) $accountId;

        return str_starts_with($accountId, 'act_') ? $accountId : 'act_'.$accountId;
    }

    /**
     * @return array<int, string>
     */
    private function defaultInsightFields(): array
    {
        return [
            'date_start',
            'date_stop',
            'account_id',
            'account_name',
            'campaign_id',
            'campaign_name',
            'adset_id',
            'adset_name',
            'ad_id',
            'ad_name',
            'impressions',
            'clicks',
            'inline_link_clicks',
            'spend',
            'actions',
            'action_values',
            'purchase_roas',
            'website_purchase_roas',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function paidSocialSignal(array $row): array
    {
        $spend = (float) ($row['spend'] ?? 0);
        $revenue = $this->actionValue($row['action_values'] ?? [], ['purchase', 'omni_purchase']);
        $roas = $this->actionValue($row['purchase_roas'] ?? [], ['omni_purchase', 'purchase'])
            ?? $this->actionValue($row['website_purchase_roas'] ?? [], ['offsite_conversion.fb_pixel_purchase', 'purchase']);

        return [
            'source' => 'meta_ads',
            'signal_type' => 'paid_social_campaign',
            'signal_date' => $row['date_start'] ?? now()->toDateString(),
            'campaign_id' => $row['campaign_id'] ?? null,
            'campaign' => $row['campaign_name'] ?? null,
            'ad_group_id' => $row['adset_id'] ?? null,
            'ad_group' => $row['adset_name'] ?? null,
            'ad_id' => $row['ad_id'] ?? null,
            'ad' => $row['ad_name'] ?? null,
            'impressions' => (float) ($row['impressions'] ?? 0),
            'clicks' => (float) ($row['clicks'] ?? 0),
            'link_clicks' => (float) ($row['inline_link_clicks'] ?? 0),
            'cost' => $spend,
            'conversions' => $this->actionValue($row['actions'] ?? [], ['purchase', 'omni_purchase']) ?? 0.0,
            'revenue' => $revenue ?? 0.0,
            'roas' => $roas ?? ($spend > 0 && $revenue !== null ? round($revenue / $spend, 4) : null),
            'payload' => $row,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $actions
     * @param  array<int, string>  $types
     */
    private function actionValue(array $actions, array $types): ?float
    {
        foreach ($actions as $action) {
            if (in_array($action['action_type'] ?? null, $types, true)) {
                return (float) ($action['value'] ?? 0);
            }
        }

        return null;
    }
}
