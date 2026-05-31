<?php

namespace App\Console\Commands;

use App\Models\CanonicalProductIdentity;
use App\Models\CanonicalProductSearchProfile;
use App\Models\ExternalSearchQuerySignal;
use App\Services\CanonicalProductSearchProfileBuilder;
use App\Services\CanonicalProductSearchSuggestService;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AnalyzeExternalSearchSignalsCommand extends Command
{
    protected $signature = 'search-signals:analyze
                            {--limit=25 : Number of external query groups to analyze}
                            {--days=90 : Lookback window in days}
                            {--source=all : Signal source filter, or all}
                            {--json : Emit machine-readable JSON}';

    protected $description = 'Analyze external demand signals against SearchProfile without mutating search state';

    public function handle(CanonicalProductSearchSuggestService $suggest): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $days = max(1, (int) $this->option('days'));
        $source = $this->normalize((string) $this->option('source'));

        $rows = $this->queryGroups($limit, $days, $source)
            ->map(fn ($row): array => $this->analyzeRow($row, $days, $source, $suggest))
            ->values()
            ->all();

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'lookback_days' => $days,
            'source' => $source,
            'summary' => $this->summary($rows),
            'insights' => $rows,
        ];

        if ((bool) $this->option('json')) {
            $this->output->write(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL);

            return self::SUCCESS;
        }

        $this->info('External search demand analysis');
        $this->line('source: '.$source);
        $this->line('lookback_days: '.$days);
        foreach ($payload['summary'] as $key => $value) {
            $this->line($key.': '.$value);
        }

        $this->newLine();
        $this->table(
            ['query', 'demand', 'current', 'top_result', 'insight', 'recommendation'],
            collect($rows)->map(fn (array $row): array => [
                $row['query'],
                $row['demand_weight'],
                $row['current_results_count'],
                Str::limit((string) ($row['top_result']['name'] ?? '-'), 40),
                $row['insight_type'],
                Str::limit($row['recommendation'], 46),
            ])->all(),
        );

        return self::SUCCESS;
    }

    private function queryGroups(int $limit, int $days, string $source): Collection
    {
        return ExternalSearchQuerySignal::query()
            ->select([
                'normalized_query',
                DB::raw('MAX(query) as sample_query'),
                DB::raw('COUNT(*) as signal_count'),
                DB::raw('SUM(impressions) as impressions'),
                DB::raw('SUM(clicks) as clicks'),
                DB::raw('SUM(COALESCE(volume, impressions, 0)) as demand_weight'),
                DB::raw('MAX(observed_at) as last_observed_at'),
            ])
            ->when($source !== '' && $source !== 'all', fn ($query) => $query->where('source', $source))
            ->where('observed_at', '>=', now()->subDays($days))
            ->groupBy('normalized_query')
            ->orderByDesc('demand_weight')
            ->orderByDesc('last_observed_at')
            ->limit($limit)
            ->get();
    }

    private function analyzeRow(object $row, int $days, string $source, CanonicalProductSearchSuggestService $suggest): array
    {
        $query = (string) ($row->normalized_query ?: $row->sample_query);
        $signals = $this->signalsForQuery($query, $days, $source);
        $suggestions = $suggest->suggestions(Request::create('/store/suggest', 'GET', ['q' => $query]));
        $results = $suggestions['results'];
        $resultMetadata = $this->resultMetadata($results);
        $topResult = $results[0] ?? null;
        $topMetadata = $topResult !== null ? ($resultMetadata[(int) $topResult['id']] ?? []) : [];
        $expected = [
            'brands' => $this->expectedValues($query, $signals, 'brand'),
            'regions' => $this->expectedValues($query, $signals, 'region'),
        ];
        $insightType = $this->insightType($results, $resultMetadata, $topMetadata, $expected);

        return [
            'query' => $query,
            'sample_query' => (string) $row->sample_query,
            'signal_count' => (int) $row->signal_count,
            'impressions' => (int) $row->impressions,
            'clicks' => (int) $row->clicks,
            'demand_weight' => (int) $row->demand_weight,
            'current_results_count' => count($results),
            'expected' => $expected,
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
            'insight_type' => $insightType,
            'recommendation' => $this->recommendation($insightType, $query, $expected),
            'last_observed_at' => (string) $row->last_observed_at,
        ];
    }

    private function signalsForQuery(string $query, int $days, string $source): Collection
    {
        return ExternalSearchQuerySignal::query()
            ->where('normalized_query', $query)
            ->when($source !== '' && $source !== 'all', fn ($builder) => $builder->where('source', $source))
            ->where('observed_at', '>=', now()->subDays($days))
            ->get();
    }

    /**
     * @param array<int, array<string, mixed>> $results
     * @return array<int, array<string, mixed>>
     */
    private function resultMetadata(array $results): array
    {
        $ids = collect($results)->pluck('id')->filter()->map(fn ($id): int => (int) $id)->all();

        return CanonicalProductIdentity::query()
            ->with('searchProfile')
            ->whereIn('id', $ids)
            ->get()
            ->mapWithKeys(fn (CanonicalProductIdentity $identity): array => [
                (int) $identity->id => (array) ($identity->searchProfile?->search_metadata ?? []),
            ])
            ->all();
    }

    /**
     * @param Collection<int, ExternalSearchQuerySignal> $signals
     * @return array<int, string>
     */
    private function expectedValues(string $query, Collection $signals, string $type): array
    {
        $metadataKeys = $type === 'brand'
            ? ['expected_brand', 'brand']
            : ['expected_region', 'target_region', 'region'];

        $fromSignals = $signals
            ->flatMap(function (ExternalSearchQuerySignal $signal) use ($metadataKeys, $type): array {
                $metadata = (array) ($signal->metadata ?? []);
                $values = [];

                foreach ($metadataKeys as $key) {
                    if (filled($metadata[$key] ?? null)) {
                        $values[] = (string) $metadata[$key];
                    }
                }

                return $values;
            });

        $fromProfiles = $this->profileExpectedValues($query, $type);

        return $fromSignals
            ->merge($fromProfiles)
            ->map(fn (string $value): string => $type === 'region' ? $this->normalizeRegion($value) : $this->normalize($value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, string>
     */
    private function profileExpectedValues(string $query, string $type): Collection
    {
        $context = $this->queryContext($query);
        $lookupTerms = array_values(array_unique(array_filter([$context['phrase'], ...$context['terms']])));

        return CanonicalProductSearchProfile::query()
            ->where('profile_version', CanonicalProductSearchProfileBuilder::PROFILE_VERSION)
            ->whereNull('last_error')
            ->where(function ($builder) use ($lookupTerms): void {
                foreach ($lookupTerms as $term) {
                    $builder->orWhere('search_aliases', 'like', '%"'.$this->escapeLike($term).'"%');
                    if (mb_strlen($term) > 2) {
                        $builder->orWhere('search_text', 'like', '%'.$this->escapeLike($term).'%');
                    }
                }
            })
            ->limit(80)
            ->get()
            ->filter(fn (CanonicalProductSearchProfile $profile): bool => $this->aliasMatches($profile, $context, $type))
            ->map(fn (CanonicalProductSearchProfile $profile): ?string => data_get($profile->search_metadata, $type))
            ->filter()
            ->values();
    }

    /**
     * @param array<string, mixed> $context
     */
    private function aliasMatches(CanonicalProductSearchProfile $profile, array $context, string $type): bool
    {
        $aliases = (array) data_get($profile->search_aliases, $type, []);

        foreach ($aliases as $alias) {
            $alias = $this->normalize((string) $alias);
            if ($alias === '') {
                continue;
            }

            if ($context['phrase'] === $alias || in_array($alias, $context['terms'], true)) {
                return true;
            }

            if (str_contains($context['phrase'], $alias) && mb_strlen($alias) > 2) {
                return true;
            }

            foreach ($context['terms'] as $term) {
                if (mb_strlen($term) >= 3 && str_starts_with($alias, $term)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<int, array<string, mixed>> $results
     * @param array<int, array<string, mixed>> $resultMetadata
     * @param array<string, mixed> $topMetadata
     * @param array{brands: array<int, string>, regions: array<int, string>} $expected
     */
    private function insightType(array $results, array $resultMetadata, array $topMetadata, array $expected): string
    {
        if ($results === []) {
            return $expected['brands'] !== [] || $expected['regions'] !== []
                ? 'ALIAS_GAP'
                : 'COVERAGE_GAP';
        }

        $topBrand = $this->normalize($topMetadata['brand'] ?? null);
        if ($expected['brands'] !== [] && ! in_array($topBrand, $expected['brands'], true)) {
            return $this->anyMetadataMatches($resultMetadata, 'brand', $expected['brands'])
                ? 'RANKING_GAP'
                : 'BRAND_GAP';
        }

        $topRegion = $this->normalizeRegion($topMetadata['region'] ?? null);
        if ($expected['regions'] !== [] && ! in_array($topRegion, $expected['regions'], true)) {
            return $this->anyMetadataMatches($resultMetadata, 'region', $expected['regions'])
                ? 'RANKING_GAP'
                : 'REGION_GAP';
        }

        if (($expected['brands'] !== [] && in_array($topBrand, $expected['brands'], true))
            || ($expected['regions'] !== [] && in_array($topRegion, $expected['regions'], true))) {
            return 'COVERED';
        }

        if (count($results) <= 2) {
            return 'LOW_COVERAGE';
        }

        return 'COVERED';
    }

    /**
     * @param array<int, array<string, mixed>> $metadataRows
     * @param array<int, string> $expected
     */
    private function anyMetadataMatches(array $metadataRows, string $key, array $expected): bool
    {
        foreach ($metadataRows as $metadata) {
            $value = $key === 'region'
                ? $this->normalizeRegion($metadata[$key] ?? null)
                : $this->normalize($metadata[$key] ?? null);

            if (in_array($value, $expected, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{brands: array<int, string>, regions: array<int, string>} $expected
     */
    private function recommendation(string $insightType, string $query, array $expected): string
    {
        return match ($insightType) {
            'COVERAGE_GAP' => "Review catalog demand for '{$query}' and decide whether a product/category is missing.",
            'ALIAS_GAP' => "Review aliases for existing entities matching '{$query}' before changing SearchProfile.",
            'RANKING_GAP' => "Correct entity exists for '{$query}', review ranking rules or availability signals.",
            'BRAND_GAP' => 'Review brand interpretation: expected '.implode(', ', $expected['brands']).'.',
            'REGION_GAP' => 'Review regional coverage: expected '.implode(', ', $expected['regions']).'.',
            'LOW_COVERAGE' => "Review assortment depth for '{$query}'.",
            default => "No action suggested for '{$query}'.",
        };
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, int>
     */
    private function summary(array $rows): array
    {
        $counts = collect($rows)->countBy('insight_type');

        return [
            'signals_analyzed' => array_sum(array_column($rows, 'signal_count')),
            'queries_analyzed' => count($rows),
            'covered' => (int) ($counts['COVERED'] ?? 0),
            'coverage_gaps' => (int) ($counts['COVERAGE_GAP'] ?? 0),
            'alias_gaps' => (int) ($counts['ALIAS_GAP'] ?? 0),
            'ranking_gaps' => (int) ($counts['RANKING_GAP'] ?? 0),
            'brand_gaps' => (int) ($counts['BRAND_GAP'] ?? 0),
            'region_gaps' => (int) ($counts['REGION_GAP'] ?? 0),
            'low_coverage' => (int) ($counts['LOW_COVERAGE'] ?? 0),
        ];
    }

    /**
     * @return array{phrase: string, terms: array<int, string>}
     */
    private function queryContext(string $query): array
    {
        $phrase = $this->normalize($query);
        $terms = collect(preg_split('/[^\pL\pN]+/u', $phrase) ?: [])
            ->map(fn (string $term): string => trim($term))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return ['phrase' => $phrase, 'terms' => $terms];
    }

    private function normalize(mixed $value): string
    {
        $value = Str::lower(trim((string) $value));
        $value = preg_replace('/\s+/u', ' ', $value) ?? '';

        return trim($value);
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

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '\\%_');
    }
}
