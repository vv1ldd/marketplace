<?php

namespace App\Console\Commands;

use App\Services\SearchSignals\ExternalSearchSignalIngestor;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PullExternalSearchSignalsCommand extends Command
{
    protected $signature = 'search-signals:pull
                            {provider : google_search_console, yandex_webmaster, google_suggest, or yandex_suggest}
                            {--from= : Start date, YYYY-MM-DD}
                            {--to= : End date, YYYY-MM-DD}
                            {--limit=500 : Max records to pull}
                            {--site= : Google Search Console site URL override}
                            {--user-id= : Yandex Webmaster user ID override}
                            {--host-id= : Yandex Webmaster host ID override}
                            {--query= : Seed query for suggest providers}
                            {--country= : Country context for suggest/import rows}
                            {--locale= : Locale context for suggest/import rows}
                            {--dry-run : Fetch and map records without persisting}
                            {--json : Emit machine-readable JSON}';

    protected $description = 'Pull external search system signals into Search Demand Intelligence adapters';

    public function handle(ExternalSearchSignalIngestor $ingestor): int
    {
        $provider = Str::snake((string) $this->argument('provider'));
        $from = $this->dateOption('from', now()->subDays(28));
        $to = $this->dateOption('to', now()->subDay());
        $limit = max(1, (int) $this->option('limit'));

        try {
            $records = match ($provider) {
                'google_search_console' => $this->googleSearchConsoleRecords($from, $to, min($limit, 25000)),
                'yandex_webmaster' => $this->yandexWebmasterRecords($from, $to, min($limit, 500)),
                'google_suggest' => $this->googleSuggestRecords($limit),
                'yandex_suggest' => $this->yandexSuggestRecords($limit),
                default => throw new \InvalidArgumentException("Unsupported provider: {$provider}"),
            };
        } catch (RequestException|\InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $result = (bool) $this->option('dry-run')
            ? ['imported' => 0, 'skipped' => 0]
            : $ingestor->persist($records, $provider);

        $payload = [
            'provider' => $provider,
            'from' => $from,
            'to' => $to,
            'dry_run' => (bool) $this->option('dry-run'),
            'records_mapped' => count($records),
            'imported' => $result['imported'],
            'skipped' => $result['skipped'],
            'records' => (bool) $this->option('dry-run') ? $records : [],
        ];

        if ((bool) $this->option('json')) {
            $this->output->write(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL);

            return self::SUCCESS;
        }

        $this->info("Pulled {$payload['records_mapped']} {$provider} signal record(s).");
        if ($payload['dry_run']) {
            $this->warn('Dry run: no records were persisted.');
        } else {
            $this->line("Imported: {$payload['imported']}");
            $this->line("Skipped: {$payload['skipped']}");
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function googleSearchConsoleRecords(string $from, string $to, int $limit): array
    {
        $token = (string) config('services.google_search_console.access_token');
        $siteUrl = (string) ($this->option('site') ?: config('services.google_search_console.site_url'));
        if ($token === '' || $siteUrl === '') {
            throw new \InvalidArgumentException('Google Search Console requires GOOGLE_SEARCH_CONSOLE_ACCESS_TOKEN and GOOGLE_SEARCH_CONSOLE_SITE_URL, or --site.');
        }

        $baseUrl = rtrim((string) config('services.google_search_console.base_url', 'https://www.googleapis.com'), '/');
        $response = Http::withToken($token)
            ->acceptJson()
            ->post($baseUrl.'/webmasters/v3/sites/'.rawurlencode($siteUrl).'/searchAnalytics/query', [
                'startDate' => $from,
                'endDate' => $to,
                'dimensions' => ['query', 'country', 'page'],
                'rowLimit' => $limit,
                'startRow' => 0,
            ])
            ->throw()
            ->json();

        return collect((array) ($response['rows'] ?? []))
            ->map(function (array $row) use ($to): ?array {
                $keys = (array) ($row['keys'] ?? []);
                $query = trim((string) ($keys[0] ?? ''));
                if ($query === '') {
                    return null;
                }

                return [
                    'query' => $query,
                    'source' => 'google_search_console',
                    'country' => $keys[1] ?? null,
                    'landing_url' => $keys[2] ?? null,
                    'impressions' => (int) ($row['impressions'] ?? 0),
                    'clicks' => (int) ($row['clicks'] ?? 0),
                    'ctr' => $row['ctr'] ?? null,
                    'observed_at' => $to,
                    'metadata' => [
                        'provider' => 'google_search_console',
                        'position' => $row['position'] ?? null,
                    ],
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function yandexWebmasterRecords(string $from, string $to, int $limit): array
    {
        $token = (string) config('services.yandex_webmaster.oauth_token');
        $userId = (string) ($this->option('user-id') ?: config('services.yandex_webmaster.user_id'));
        $hostId = (string) ($this->option('host-id') ?: config('services.yandex_webmaster.host_id'));
        if ($token === '' || $userId === '' || $hostId === '') {
            throw new \InvalidArgumentException('Yandex Webmaster requires YANDEX_WEBMASTER_OAUTH_TOKEN, YANDEX_WEBMASTER_USER_ID, and YANDEX_WEBMASTER_HOST_ID, or CLI overrides.');
        }

        $baseUrl = rtrim((string) config('services.yandex_webmaster.base_url', 'https://api.webmaster.yandex.net'), '/');
        $version = trim((string) config('services.yandex_webmaster.version', 'v4'), '/');
        $response = Http::withToken($token)
            ->acceptJson()
            ->get($baseUrl.'/'.$version.'/user/'.$userId.'/hosts/'.$hostId.'/search-queries/popular', [
                'order_by' => 'TOTAL_SHOWS',
                'date_from' => $from,
                'date_to' => $to,
                'offset' => 0,
                'limit' => $limit,
            ])
            ->throw()
            ->json();

        return collect((array) ($response['queries'] ?? []))
            ->map(function (array $row) use ($response, $to): ?array {
                $query = trim((string) ($row['query_text'] ?? ''));
                if ($query === '') {
                    return null;
                }

                $indicators = (array) ($row['indicators'] ?? []);

                return [
                    'query' => $query,
                    'source' => 'yandex_webmaster',
                    'impressions' => (int) ($indicators['TOTAL_SHOWS'] ?? 0),
                    'clicks' => (int) ($indicators['TOTAL_CLICKS'] ?? 0),
                    'observed_at' => $response['date_to'] ?? $to,
                    'metadata' => [
                        'provider' => 'yandex_webmaster',
                        'query_id' => $row['query_id'] ?? null,
                        'date_from' => $response['date_from'] ?? null,
                        'date_to' => $response['date_to'] ?? null,
                        'indicators' => $indicators,
                    ],
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function googleSuggestRecords(int $limit): array
    {
        $query = $this->requiredSuggestQuery();
        $baseUrl = rtrim((string) config('services.google_suggest.base_url', 'https://suggestqueries.google.com/complete/search'), '/');
        $response = Http::acceptJson()
            ->get($baseUrl, [
                'client' => 'firefox',
                'q' => $query,
                'hl' => (string) ($this->option('locale') ?: app()->getLocale()),
            ])
            ->throw()
            ->json();

        return $this->suggestRecords('google_suggest', $query, (array) ($response[1] ?? []), $limit);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function yandexSuggestRecords(int $limit): array
    {
        $query = $this->requiredSuggestQuery();
        $baseUrl = rtrim((string) config('services.yandex_suggest.base_url', 'https://suggest.yandex.com/suggest-ff.cgi'), '/');
        $response = Http::acceptJson()
            ->get($baseUrl, [
                'part' => $query,
                'lang' => (string) ($this->option('locale') ?: app()->getLocale()),
            ])
            ->throw()
            ->json();

        return $this->suggestRecords('yandex_suggest', $query, (array) ($response[1] ?? []), $limit);
    }

    /**
     * @param array<int, mixed> $suggestions
     * @return array<int, array<string, mixed>>
     */
    private function suggestRecords(string $source, string $seedQuery, array $suggestions, int $limit): array
    {
        return collect($suggestions)
            ->take($limit)
            ->values()
            ->map(fn (mixed $suggestion, int $index): array => [
                'query' => (string) $suggestion,
                'source' => $source,
                'country' => $this->option('country') ?: null,
                'locale' => $this->option('locale') ?: app()->getLocale(),
                'volume' => 1,
                'observed_at' => now()->toDateString(),
                'metadata' => [
                    'provider' => $source,
                    'seed_query' => $seedQuery,
                    'rank' => $index + 1,
                ],
            ])
            ->filter(fn (array $record): bool => trim($record['query']) !== '')
            ->all();
    }

    private function requiredSuggestQuery(): string
    {
        $query = trim((string) $this->option('query'));
        if ($query === '') {
            throw new \InvalidArgumentException('Suggest providers require --query.');
        }

        return $query;
    }

    private function dateOption(string $name, Carbon $default): string
    {
        $value = trim((string) $this->option($name));

        return ($value !== '' ? Carbon::parse($value) : $default)->toDateString();
    }
}
