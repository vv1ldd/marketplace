<?php

namespace App\Services\ZeroLayer;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class YandexWebmasterClient
{
    private const DEFAULT_BASE_URL = 'https://api.webmaster.yandex.net';
    private const DEFAULT_VERSION = 'v4';

    public function user(array $credentials, array $settings = []): array
    {
        return $this->get($credentials, $settings, '/user');
    }

    public function hosts(array $credentials, array $settings = []): array
    {
        $userId = $this->resolveUserId($credentials, $settings);

        return $this->get($credentials, $settings, "/user/{$userId}/hosts");
    }

    public function addHost(array $credentials, array $settings, string $hostUrl): array
    {
        $userId = $this->resolveUserId($credentials, $settings);

        return $this->post($credentials, $settings, "/user/{$userId}/hosts", [
            'host_url' => $hostUrl,
        ]);
    }

    public function deleteHost(array $credentials, array $settings, ?string $hostId = null): bool
    {
        $userId = $this->resolveUserId($credentials, $settings);
        $hostId = $this->resolveHostId($credentials, $settings, $hostId);

        return $this->delete($credentials, $settings, "/user/{$userId}/hosts/{$hostId}");
    }

    public function host(array $credentials, array $settings, ?string $hostId = null): array
    {
        $userId = $this->resolveUserId($credentials, $settings);
        $hostId = $this->resolveHostId($credentials, $settings, $hostId);

        return $this->get($credentials, $settings, "/user/{$userId}/hosts/{$hostId}");
    }

    public function verification(array $credentials, array $settings, ?string $hostId = null): array
    {
        $userId = $this->resolveUserId($credentials, $settings);
        $hostId = $this->resolveHostId($credentials, $settings, $hostId);

        return $this->get($credentials, $settings, "/user/{$userId}/hosts/{$hostId}/verification");
    }

    public function startVerification(array $credentials, array $settings, string $verificationType, ?string $hostId = null): array
    {
        $userId = $this->resolveUserId($credentials, $settings);
        $hostId = $this->resolveHostId($credentials, $settings, $hostId);

        return $this->post($credentials, $settings, "/user/{$userId}/hosts/{$hostId}/verification", [], [
            'verification_type' => $verificationType,
        ]);
    }

    public function owners(array $credentials, array $settings, ?string $hostId = null): array
    {
        $userId = $this->resolveUserId($credentials, $settings);
        $hostId = $this->resolveHostId($credentials, $settings, $hostId);

        return $this->get($credentials, $settings, "/user/{$userId}/hosts/{$hostId}/owners");
    }

    public function sqiHistory(array $credentials, array $settings, Carbon|string|null $from = null, Carbon|string|null $to = null, ?string $hostId = null): array
    {
        $userId = $this->resolveUserId($credentials, $settings);
        $hostId = $this->resolveHostId($credentials, $settings, $hostId);

        return $this->get($credentials, $settings, "/user/{$userId}/hosts/{$hostId}/sqi-history", $this->dateRangeQuery($from, $to));
    }

    public function searchQueriesHistory(array $credentials, array $settings, Carbon|string|null $from = null, Carbon|string|null $to = null, ?string $hostId = null): array
    {
        $userId = $this->resolveUserId($credentials, $settings);
        $hostId = $this->resolveHostId($credentials, $settings, $hostId);

        return $this->get($credentials, $settings, "/user/{$userId}/hosts/{$hostId}/search-queries/all/history", $this->searchQueryHistoryParams($settings, $from, $to));
    }

    public function searchQueryHistory(array $credentials, array $settings, string $queryId, Carbon|string|null $from = null, Carbon|string|null $to = null, ?string $hostId = null): array
    {
        $userId = $this->resolveUserId($credentials, $settings);
        $hostId = $this->resolveHostId($credentials, $settings, $hostId);

        return $this->get($credentials, $settings, "/user/{$userId}/hosts/{$hostId}/search-queries/{$queryId}/history", $this->searchQueryHistoryParams($settings, $from, $to));
    }

    public function popularQueries(array $credentials, array $settings, Carbon|string $from, Carbon|string $to): array
    {
        $userId = $this->resolveUserId($credentials, $settings);
        $hostId = $this->resolveHostId($credentials, $settings);

        $response = $this->get($credentials, $settings, "/user/{$userId}/hosts/{$hostId}/search-queries/popular", [
            'order_by' => 'TOTAL_SHOWS',
            'query_indicator' => ['TOTAL_SHOWS', 'TOTAL_CLICKS', 'AVG_SHOW_POSITION', 'AVG_CLICK_POSITION'],
            'date_from' => Carbon::parse($from)->toDateString(),
            'date_to' => Carbon::parse($to)->toDateString(),
            'limit' => (int) ($settings['limit'] ?? 500),
        ]);

        return collect($response['queries'] ?? [])->map(function (array $row) use ($response): array {
            $indicators = $row['indicators'] ?? [];

            return [
                'source' => 'yandex_webmaster',
                'signal_type' => 'search_query',
                'signal_date' => $response['date_to'] ?? now()->toDateString(),
                'external_id' => $row['query_id'] ?? null,
                'query_text' => $row['query_text'] ?? null,
                'impressions' => (float) ($indicators['TOTAL_SHOWS'] ?? 0),
                'clicks' => (float) ($indicators['TOTAL_CLICKS'] ?? 0),
                'position' => isset($indicators['AVG_SHOW_POSITION']) ? (float) $indicators['AVG_SHOW_POSITION'] : null,
                'payload' => $row,
            ];
        })->values()->all();
    }

    public function sitemaps(array $credentials, array $settings, array $query = [], ?string $hostId = null): array
    {
        return $this->hostGet($credentials, $settings, '/sitemaps', $query, $hostId);
    }

    public function sitemap(array $credentials, array $settings, string $sitemapId, ?string $hostId = null): array
    {
        return $this->hostGet($credentials, $settings, "/sitemaps/{$sitemapId}", [], $hostId);
    }

    public function userAddedSitemaps(array $credentials, array $settings, array $query = [], ?string $hostId = null): array
    {
        return $this->hostGet($credentials, $settings, '/user-added-sitemaps', $query, $hostId);
    }

    public function userAddedSitemap(array $credentials, array $settings, string $sitemapId, ?string $hostId = null): array
    {
        return $this->hostGet($credentials, $settings, "/user-added-sitemaps/{$sitemapId}", [], $hostId);
    }

    public function addUserSitemap(array $credentials, array $settings, string $url, ?string $hostId = null): array
    {
        return $this->hostPost($credentials, $settings, '/user-added-sitemaps', ['url' => $url], [], $hostId);
    }

    public function deleteUserSitemap(array $credentials, array $settings, string $sitemapId, ?string $hostId = null): bool
    {
        $userId = $this->resolveUserId($credentials, $settings);
        $hostId = $this->resolveHostId($credentials, $settings, $hostId);

        return $this->delete($credentials, $settings, "/user/{$userId}/hosts/{$hostId}/user-added-sitemaps/{$sitemapId}");
    }

    public function summary(array $credentials, array $settings, ?string $hostId = null): array
    {
        return $this->hostGet($credentials, $settings, '/summary', [], $hostId);
    }

    public function indexingHistory(array $credentials, array $settings, Carbon|string|null $from = null, Carbon|string|null $to = null, ?string $hostId = null): array
    {
        return $this->hostGet($credentials, $settings, '/indexing/history', $this->dateRangeQuery($from, $to), $hostId);
    }

    public function indexingSamples(array $credentials, array $settings, array $query = [], ?string $hostId = null): array
    {
        return $this->hostGet($credentials, $settings, '/indexing/samples', $query, $hostId);
    }

    public function importantUrls(array $credentials, array $settings, ?string $hostId = null): array
    {
        return $this->hostGet($credentials, $settings, '/important-urls', [], $hostId);
    }

    public function importantUrlHistory(array $credentials, array $settings, string $url, ?string $hostId = null): array
    {
        return $this->hostGet($credentials, $settings, '/important-urls/history', ['url' => $url], $hostId);
    }

    public function searchUrlsInSearchHistory(array $credentials, array $settings, Carbon|string|null $from = null, Carbon|string|null $to = null, ?string $hostId = null): array
    {
        return $this->hostGet($credentials, $settings, '/search-urls/in-search/history', $this->dateRangeQuery($from, $to), $hostId);
    }

    public function searchUrlsInSearchSamples(array $credentials, array $settings, array $query = [], ?string $hostId = null): array
    {
        return $this->hostGet($credentials, $settings, '/search-urls/in-search/samples', $query, $hostId);
    }

    public function searchUrlsEventsHistory(array $credentials, array $settings, Carbon|string|null $from = null, Carbon|string|null $to = null, ?string $hostId = null): array
    {
        return $this->hostGet($credentials, $settings, '/search-urls/events/history', $this->dateRangeQuery($from, $to), $hostId);
    }

    public function searchUrlsEventsSamples(array $credentials, array $settings, array $query = [], ?string $hostId = null): array
    {
        return $this->hostGet($credentials, $settings, '/search-urls/events/samples', $query, $hostId);
    }

    public function submitRecrawl(array $credentials, array $settings, string $url, ?string $hostId = null): array
    {
        return $this->hostPost($credentials, $settings, '/recrawl/queue', ['url' => $url], [], $hostId);
    }

    public function recrawlQueue(array $credentials, array $settings, array $query = [], ?string $hostId = null): array
    {
        return $this->hostGet($credentials, $settings, '/recrawl/queue', $query, $hostId);
    }

    public function recrawlTask(array $credentials, array $settings, string $taskId, ?string $hostId = null): array
    {
        return $this->hostGet($credentials, $settings, "/recrawl/queue/{$taskId}", [], $hostId);
    }

    public function recrawlQuota(array $credentials, array $settings, ?string $hostId = null): array
    {
        return $this->hostGet($credentials, $settings, '/recrawl/quota', [], $hostId);
    }

    public function diagnostics(array $credentials, array $settings, ?string $hostId = null): array
    {
        return $this->hostGet($credentials, $settings, '/diagnostics', [], $hostId);
    }

    public function internalBrokenLinkSamples(array $credentials, array $settings, array $query = [], ?string $hostId = null): array
    {
        return $this->hostGet($credentials, $settings, '/links/internal/broken/samples', $query, $hostId);
    }

    public function internalBrokenLinksHistory(array $credentials, array $settings, Carbon|string|null $from = null, Carbon|string|null $to = null, ?string $hostId = null): array
    {
        return $this->hostGet($credentials, $settings, '/links/internal/broken/history', $this->dateRangeQuery($from, $to), $hostId);
    }

    public function externalLinkSamples(array $credentials, array $settings, array $query = [], ?string $hostId = null): array
    {
        return $this->hostGet($credentials, $settings, '/links/external/samples', $query, $hostId);
    }

    public function externalLinksHistory(array $credentials, array $settings, string $indicator = 'LINKS_TOTAL_COUNT', ?string $hostId = null): array
    {
        return $this->hostGet($credentials, $settings, '/links/external/history', ['indicator' => $indicator], $hostId);
    }

    public function addNewsFeed(array $credentials, array $settings, string $url, ?string $hostId = null): array
    {
        return $this->hostPost($credentials, $settings, '/news-feeds/add/', ['url' => $url], [], $hostId);
    }

    public function newsFeeds(array $credentials, array $settings, ?string $hostId = null): array
    {
        return $this->hostGet($credentials, $settings, '/news-feeds/list/', [], $hostId);
    }

    public function setNewsFeedEnabled(array $credentials, array $settings, string $url, bool $enabled, ?string $hostId = null): array
    {
        return $this->hostPost($credentials, $settings, '/news-feeds/set-enabled/', [
            'url' => $url,
            'isEnabled' => $enabled,
        ], [], $hostId);
    }

    public function get(array $credentials, array $settings, string $path, array $query = []): array
    {
        return $this->request($credentials, $settings)
            ->get($this->versionedPath($settings, $path), $query)
            ->throw()
            ->json();
    }

    public function post(array $credentials, array $settings, string $path, array $body = [], array $query = []): array
    {
        $response = $this->request($credentials, $settings)
            ->post($this->pathWithQuery($settings, $path, $query), $body)
            ->throw();

        return $response->json() ?? [];
    }

    public function delete(array $credentials, array $settings, string $path, array $query = []): bool
    {
        return $this->request($credentials, $settings)
            ->delete($this->pathWithQuery($settings, $path, $query))
            ->throw()
            ->successful();
    }

    private function hostGet(array $credentials, array $settings, string $suffix, array $query = [], ?string $hostId = null): array
    {
        $userId = $this->resolveUserId($credentials, $settings);
        $hostId = $this->resolveHostId($credentials, $settings, $hostId);

        return $this->get($credentials, $settings, "/user/{$userId}/hosts/{$hostId}{$suffix}", $query);
    }

    private function hostPost(array $credentials, array $settings, string $suffix, array $body = [], array $query = [], ?string $hostId = null): array
    {
        $userId = $this->resolveUserId($credentials, $settings);
        $hostId = $this->resolveHostId($credentials, $settings, $hostId);

        return $this->post($credentials, $settings, "/user/{$userId}/hosts/{$hostId}{$suffix}", $body, $query);
    }

    private function request(array $credentials, array $settings): PendingRequest
    {
        $token = $credentials['oauth_token']
            ?? $credentials['access_token']
            ?? $credentials['token']
            ?? config('services.yandex_webmaster.oauth_token');

        if (! $token) {
            throw new InvalidArgumentException('Yandex Webmaster requires oauth_token.');
        }

        return Http::baseUrl(rtrim((string) ($settings['base_url'] ?? config('services.yandex_webmaster.base_url', self::DEFAULT_BASE_URL)), '/'))
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'Authorization' => 'OAuth '.$token,
                'Accept-Charset' => 'utf-8',
            ]);
    }

    private function versionedPath(array $settings, string $path): string
    {
        $version = trim((string) ($settings['api_version'] ?? config('services.yandex_webmaster.version', self::DEFAULT_VERSION)), '/');
        $path = '/'.ltrim($path, '/');

        return "/{$version}{$path}";
    }

    private function pathWithQuery(array $settings, string $path, array $query): string
    {
        $path = $this->versionedPath($settings, $path);

        return $query === [] ? $path : $path.'?'.http_build_query($query);
    }

    private function resolveUserId(array $credentials, array $settings): int|string
    {
        $userId = $settings['user_id'] ?? $credentials['user_id'] ?? null;

        if ($userId) {
            return $userId;
        }

        $user = $this->user($credentials, $settings);

        if (! isset($user['user_id'])) {
            throw new InvalidArgumentException('Yandex Webmaster user response did not include user_id.');
        }

        return $user['user_id'];
    }

    private function resolveHostId(array $credentials, array $settings, ?string $hostId = null): string
    {
        $hostId ??= $settings['host_id'] ?? $credentials['host_id'] ?? null;

        if (! $hostId) {
            throw new InvalidArgumentException('Yandex Webmaster requires host_id.');
        }

        return (string) $hostId;
    }

    private function dateRangeQuery(Carbon|string|null $from, Carbon|string|null $to): array
    {
        return array_filter([
            'date_from' => $from ? Carbon::parse($from)->toDateString() : null,
            'date_to' => $to ? Carbon::parse($to)->toDateString() : null,
        ]);
    }

    private function searchQueryHistoryParams(array $settings, Carbon|string|null $from, Carbon|string|null $to): array
    {
        return array_filter([
            'query_indicator' => $settings['query_indicator'] ?? ['TOTAL_SHOWS', 'TOTAL_CLICKS', 'AVG_SHOW_POSITION', 'AVG_CLICK_POSITION'],
            'device_type_indicator' => $settings['device_type_indicator'] ?? null,
            ...$this->dateRangeQuery($from, $to),
        ]);
    }
}
