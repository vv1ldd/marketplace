<?php

namespace App\Console\Commands;

use App\Models\CanonicalProductIdentity;
use App\Models\CatalogSearchLog;
use App\Services\CanonicalProductSearchSuggestService;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AnalyzeSearchQueriesCommand extends Command
{
    protected $signature = 'search-query:analyze
                            {--limit=25 : Number of query groups to analyze}
                            {--days=30 : Lookback window in days}
                            {--source=all : Search log source filter, or all}
                            {--json : Emit machine-readable JSON}';

    protected $description = 'Analyze completed search queries against current SearchProfile-backed suggest results';

    public function handle(CanonicalProductSearchSuggestService $suggest): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $days = max(1, (int) $this->option('days'));
        $source = trim((string) $this->option('source'));

        $rows = $this->queryGroups($limit, $days, $source)
            ->map(fn ($row): array => $this->analyzeRow($row, $suggest))
            ->values()
            ->all();

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'lookback_days' => $days,
            'source' => $source,
            'summary' => $this->summary($rows),
            'queries' => $rows,
        ];

        if ((bool) $this->option('json')) {
            $this->output->write(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL);

            return self::SUCCESS;
        }

        $this->info('Search query analysis');
        $this->line('source: '.$source);
        $this->line('lookback_days: '.$days);
        foreach ($payload['summary'] as $key => $value) {
            $this->line($key.': '.$value);
        }

        $this->newLine();
        $this->table(
            ['query', 'volume', 'zero_rate', 'current', 'top_result', 'diagnosis'],
            collect($rows)->map(fn (array $row): array => [
                $row['query'],
                $row['search_volume'],
                $row['zero_result_rate'],
                $row['current_results_count'],
                Str::limit((string) ($row['top_result']['name'] ?? '-'), 42),
                $row['diagnosis'],
            ])->all(),
        );

        return self::SUCCESS;
    }

    private function queryGroups(int $limit, int $days, string $source)
    {
        return CatalogSearchLog::query()
            ->select([
                'normalized_query',
                DB::raw('MAX(query) as sample_query'),
                DB::raw('COUNT(*) as search_volume'),
                DB::raw('SUM(CASE WHEN results_count = 0 THEN 1 ELSE 0 END) as zero_results_count'),
                DB::raw('AVG(results_count) as average_results_count'),
                DB::raw('MAX(id) as latest_log_id'),
                DB::raw('MAX(created_at) as last_seen_at'),
            ])
            ->when($source !== '' && $source !== 'all', fn ($query) => $query->where('source', $source))
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('normalized_query')
            ->orderByDesc('search_volume')
            ->orderByDesc('last_seen_at')
            ->limit($limit)
            ->get();
    }

    private function analyzeRow(object $row, CanonicalProductSearchSuggestService $suggest): array
    {
        $query = (string) ($row->normalized_query ?: $row->sample_query);
        $latestLog = CatalogSearchLog::query()->find((int) $row->latest_log_id);
        $filters = (array) ($latestLog?->filters ?? []);
        $suggestions = $suggest->suggestions(Request::create('/store/suggest', 'GET', ['q' => $query]));
        $results = $suggestions['results'];
        $topResult = $results[0] ?? null;
        $topIdentity = isset($topResult['id'])
            ? CanonicalProductIdentity::query()->with('searchProfile')->find((int) $topResult['id'])
            : null;
        $topMetadata = (array) ($topIdentity?->searchProfile?->search_metadata ?? []);
        $diagnosis = $this->diagnosis($filters, $results, $topMetadata);

        return [
            'query' => $query,
            'sample_query' => (string) $row->sample_query,
            'search_volume' => (int) $row->search_volume,
            'zero_results_count' => (int) $row->zero_results_count,
            'zero_result_rate' => $this->ratio((int) $row->zero_results_count, (int) $row->search_volume),
            'average_results_count' => round((float) $row->average_results_count, 2),
            'current_results_count' => count($results),
            'top_result' => $topResult ? [
                'id' => (int) $topResult['id'],
                'name' => $topResult['name'],
                'brand' => $topResult['brand'],
                'match_label' => $topResult['match_label'],
                'metadata' => [
                    'brand' => $topMetadata['brand'] ?? null,
                    'region' => $topMetadata['region'] ?? null,
                    'category' => $topMetadata['category'] ?? null,
                ],
            ] : null,
            'filters' => [
                'brand' => $filters['brand'] ?? null,
                'region' => $filters['region'] ?? null,
                'category' => $filters['category'] ?? null,
            ],
            'diagnosis' => $diagnosis,
            'last_seen_at' => optional($row->last_seen_at)->toIso8601String(),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @param array<int, array<string, mixed>> $results
     * @param array<string, mixed> $topMetadata
     */
    private function diagnosis(array $filters, array $results, array $topMetadata): string
    {
        if ($results === []) {
            return 'ZERO_RESULT_HOTSPOT';
        }

        $expectedBrand = $this->normalize($filters['brand'] ?? null);
        $actualBrand = $this->normalize($topMetadata['brand'] ?? null);
        if ($expectedBrand !== '' && $actualBrand !== '' && $expectedBrand !== $actualBrand) {
            return 'BRAND_MISMATCH';
        }

        $expectedRegion = $this->normalizeRegion($filters['region'] ?? null);
        $actualRegion = $this->normalizeRegion($topMetadata['region'] ?? null);
        if ($expectedRegion !== '' && $actualRegion !== '' && $expectedRegion !== $actualRegion) {
            return 'REGION_MISMATCH';
        }

        if (($expectedBrand !== '' && $expectedBrand === $actualBrand) || ($expectedRegion !== '' && $expectedRegion === $actualRegion)) {
            return 'COVERED';
        }

        if (count($results) <= 2) {
            return 'LOW_RESULT_HOTSPOT';
        }

        return 'COVERED';
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, int>
     */
    private function summary(array $rows): array
    {
        $diagnoses = collect($rows)
            ->countBy('diagnosis')
            ->sortKeys()
            ->all();

        return [
            'queries_analyzed' => count($rows),
            'covered' => (int) ($diagnoses['COVERED'] ?? 0),
            'zero_result_hotspots' => (int) ($diagnoses['ZERO_RESULT_HOTSPOT'] ?? 0),
            'low_result_hotspots' => (int) ($diagnoses['LOW_RESULT_HOTSPOT'] ?? 0),
            'brand_mismatches' => (int) ($diagnoses['BRAND_MISMATCH'] ?? 0),
            'region_mismatches' => (int) ($diagnoses['REGION_MISMATCH'] ?? 0),
        ];
    }

    private function ratio(int $part, int $total): float
    {
        return $total > 0 ? round($part / $total, 3) : 0.0;
    }

    private function normalize(mixed $value): string
    {
        return Str::lower(trim((string) $value));
    }

    private function normalizeRegion(mixed $value): string
    {
        $normalized = $this->normalize($value);

        return match ($normalized) {
            'us', 'usa', 'united states', 'united states of america', 'сша' => 'us',
            'gb', 'uk', 'united kingdom', 'great britain', 'британия', 'великобритания' => 'gb',
            'tr', 'turkey', 'turkiye', 'türkiye', 'турция' => 'tr',
            'ae', 'uae', 'united arab emirates', 'оаэ' => 'ae',
            'ar', 'argentina', 'аргентина' => 'ar',
            'br', 'brazil', 'бразилия' => 'br',
            'de', 'germany', 'германия' => 'de',
            'pl', 'poland', 'польша' => 'pl',
            default => $normalized,
        };
    }
}
