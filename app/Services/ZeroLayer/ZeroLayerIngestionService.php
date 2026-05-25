<?php

namespace App\Services\ZeroLayer;

use App\Models\ZeroLayerIntegration;
use App\Models\ZeroLayerSignal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Arr;
use InvalidArgumentException;

class ZeroLayerIngestionService
{
    public function __construct(
        private readonly YandexWebmasterClient $yandexWebmaster,
        private readonly GoogleSearchConsoleClient $googleSearchConsole,
        private readonly BingWebSearchClient $bingWebSearch,
        private readonly GoogleAnalyticsDataClient $googleAnalytics,
        private readonly GoogleAdsClient $googleAds,
        private readonly IndexNowClient $indexNow,
        private readonly YandexDirectClient $yandexDirect,
        private readonly YahooSearchClient $yahooSearch,
        private readonly DuckDuckGoSearchClient $duckDuckGoSearch,
        private readonly MetaGraphClient $metaGraph,
        private readonly TikTokAdsClient $tikTokAds,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function sync(?string $source = null, Carbon|string|null $from = null, Carbon|string|null $to = null): array
    {
        $query = ZeroLayerIntegration::query()->where('status', 'active');

        if ($source) {
            $query->where('source', $source);
        }

        return $query->get()
            ->map(fn (ZeroLayerIntegration $integration): array => $this->syncIntegration($integration, $from, $to))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function syncIntegration(ZeroLayerIntegration $integration, Carbon|string|null $from = null, Carbon|string|null $to = null): array
    {
        $settings = $integration->settings ?? [];
        $credentials = $integration->credentials ?? [];
        $from ??= $settings['from'] ?? now()->subDay()->toDateString();
        $to ??= $settings['to'] ?? now()->toDateString();
        $signals = match ($integration->source) {
            'yandex_webmaster' => $this->yandexWebmaster->popularQueries($credentials, $settings, $from, $to),
            'google_search_console' => $this->googleSearchConsole->searchAnalytics($credentials, $settings, $from, $to),
            'bing_web_search' => $this->bingWebSearch->webResultSignals($credentials, $settings, $this->queries($settings)),
            'google_analytics', 'google_analytics_data' => $this->googleAnalytics->landingPages($credentials, $settings, $from, $to),
            'google_ads' => $this->googleAds->paidSearchSignals($credentials, $settings, $from, $to),
            'indexnow' => $this->indexNow->submit($credentials, $settings, $this->urls($settings)),
            'yandex_direct' => $this->yandexDirect->paidSearchSignals($credentials, $settings, $from, $to),
            'yahoo_search' => $this->yahooSearch->organicResultSignals($credentials, $settings, $this->queries($settings), $settings['params'] ?? []),
            'duckduckgo_search' => $this->duckDuckGoSearch->organicResultSignals($credentials, $settings, $this->queries($settings), $settings['params'] ?? []),
            'meta_ads', 'meta_graph' => $this->metaGraph->paidSocialSignals($credentials, $settings, $from, $to),
            'tiktok_ads' => $this->tikTokAds->campaignReportSignals($credentials, $settings, $from, $to),
            default => throw new InvalidArgumentException("Unsupported zero-layer source [{$integration->source}]."),
        };

        $saved = $this->persistSignals($integration, $signals);

        $integration->forceFill(['last_synced_at' => now()])->save();

        return [
            'integration_id' => $integration->id,
            'source' => $integration->source,
            'signals_count' => count($saved),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $signals
     * @return array<int, ZeroLayerSignal>
     */
    public function persistSignals(ZeroLayerIntegration $integration, array $signals): array
    {
        return collect($signals)
            ->map(fn (array $signal): ZeroLayerSignal => $this->persistSignal($integration, $signal))
            ->values()
            ->all();
    }

    private function persistSignal(ZeroLayerIntegration $integration, array $signal): ZeroLayerSignal
    {
        $source = (string) ($signal['source'] ?? $integration->source);
        $sourceKey = $this->sourceKey($source, $signal);

        return ZeroLayerSignal::query()->updateOrCreate([
            'source' => $source,
            'source_key' => $sourceKey,
        ], [
            'zero_layer_integration_id' => $integration->id,
            'signal_type' => $signal['signal_type'] ?? 'unknown',
            'signal_date' => $signal['signal_date'] ?? null,
            'external_id' => $signal['external_id'] ?? null,
            'query_text' => $signal['query_text'] ?? null,
            'page_url' => $signal['page_url'] ?? null,
            'displayed_url' => $signal['displayed_url'] ?? null,
            'title' => $signal['title'] ?? null,
            'snippet' => $signal['snippet'] ?? null,
            'campaign_id' => $signal['campaign_id'] ?? null,
            'campaign' => $signal['campaign'] ?? null,
            'ad_group_id' => $signal['ad_group_id'] ?? null,
            'ad_group' => $signal['ad_group'] ?? null,
            'ad_id' => $signal['ad_id'] ?? null,
            'ad' => $signal['ad'] ?? null,
            'position' => $signal['position'] ?? null,
            'impressions' => $signal['impressions'] ?? null,
            'clicks' => $signal['clicks'] ?? null,
            'link_clicks' => $signal['link_clicks'] ?? null,
            'cost' => $signal['cost'] ?? null,
            'conversions' => $signal['conversions'] ?? null,
            'revenue' => $signal['revenue'] ?? null,
            'roas' => $signal['roas'] ?? null,
            'video_views' => $signal['video_views'] ?? null,
            'video_watched_6s' => $signal['video_watched_6s'] ?? null,
            'payload' => $signal['payload'] ?? $signal,
        ]);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<int, string>
     */
    private function queries(array $settings): array
    {
        return array_values(array_filter(Arr::wrap($settings['queries'] ?? [])));
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<int, string>
     */
    private function urls(array $settings): array
    {
        return array_values(array_filter(Arr::wrap($settings['urls'] ?? [])));
    }

    /**
     * @param  array<string, mixed>  $signal
     */
    private function sourceKey(string $source, array $signal): string
    {
        if (! empty($signal['external_id'])) {
            return hash('sha256', $source.'|'.$signal['external_id']);
        }

        $identity = Arr::only($signal, [
            'source',
            'signal_type',
            'signal_date',
            'query_text',
            'page_url',
            'position',
            'campaign_id',
            'campaign',
            'ad_group_id',
            'ad_id',
        ]);

        return hash('sha256', json_encode($identity, JSON_THROW_ON_ERROR));
    }
}
