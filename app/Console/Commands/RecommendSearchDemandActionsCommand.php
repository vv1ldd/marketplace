<?php

namespace App\Console\Commands;

use App\Models\SearchDemandRecommendation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

class RecommendSearchDemandActionsCommand extends Command
{
    protected $signature = 'search-signals:recommend
                            {--limit=25 : Number of external query groups to analyze}
                            {--days=90 : Lookback window in days}
                            {--source=all : Signal source filter, or all}
                            {--min-score=1 : Minimum impact score to persist}
                            {--json : Emit machine-readable JSON}';

    protected $description = 'Rank possible interventions from Search Demand Intelligence insights without applying changes';

    public function handle(): int
    {
        $exitCode = Artisan::call('search-signals:analyze', [
            '--limit' => (int) $this->option('limit'),
            '--days' => (int) $this->option('days'),
            '--source' => (string) $this->option('source'),
            '--json' => true,
        ]);

        if ($exitCode !== self::SUCCESS) {
            $this->error('Unable to analyze external search signals.');

            return self::FAILURE;
        }

        $analysis = json_decode(Artisan::output(), true);
        if (! is_array($analysis)) {
            $this->error('Invalid analysis payload.');

            return self::FAILURE;
        }

        $minScore = max(0, (float) $this->option('min-score'));
        $recommendations = collect($analysis['insights'] ?? [])
            ->map(fn (array $insight): ?array => $this->recommendationPayload($insight))
            ->filter(fn (?array $payload): bool => $payload !== null && $payload['impact_score'] >= $minScore)
            ->sortByDesc('impact_score')
            ->values();

        $persisted = $recommendations
            ->map(fn (array $payload): SearchDemandRecommendation => $this->persistRecommendation($payload))
            ->values();

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'source' => (string) $this->option('source'),
            'lookback_days' => (int) $this->option('days'),
            'recommendations_created_or_updated' => $persisted->count(),
            'recommendations' => $persisted
                ->map(fn (SearchDemandRecommendation $recommendation): array => [
                    'type' => $recommendation->type,
                    'query' => $recommendation->query,
                    'insight_type' => $recommendation->insight_type,
                    'impact_score' => $recommendation->impact_score,
                    'confidence' => $recommendation->confidence,
                    'status' => $recommendation->status,
                    'expected_entity' => $recommendation->expected_entity,
                    'evidence' => $recommendation->evidence,
                ])
                ->all(),
        ];

        if ((bool) $this->option('json')) {
            $this->output->write(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL);

            return self::SUCCESS;
        }

        $this->info("Created or updated {$persisted->count()} search demand recommendation(s).");
        $this->table(
            ['type', 'query', 'insight', 'score', 'confidence', 'status'],
            $persisted->map(fn (SearchDemandRecommendation $recommendation): array => [
                $recommendation->type,
                Str::limit($recommendation->query, 28),
                $recommendation->insight_type,
                $recommendation->impact_score,
                $recommendation->confidence,
                $recommendation->status,
            ])->all(),
        );

        return self::SUCCESS;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function persistRecommendation(array $payload): SearchDemandRecommendation
    {
        $recommendation = SearchDemandRecommendation::where('recommendation_hash', $payload['recommendation_hash'])->first();

        if ($recommendation === null) {
            return SearchDemandRecommendation::create($payload);
        }

        unset($payload['status'], $payload['decided_at'], $payload['applied_at']);
        $recommendation->fill($payload);
        $recommendation->save();

        return $recommendation;
    }

    /**
     * @param array<string, mixed> $insight
     * @return array<string, mixed>|null
     */
    private function recommendationPayload(array $insight): ?array
    {
        $type = $this->actionType((string) ($insight['insight_type'] ?? ''));
        if ($type === null) {
            return null;
        }

        $query = (string) ($insight['query'] ?? '');
        $normalizedQuery = $this->normalize($query);
        $impactScore = $this->impactScore($insight, $type);
        $confidence = $this->confidence($insight);
        $expectedEntity = [
            'brands' => data_get($insight, 'expected.brands', []),
            'regions' => data_get($insight, 'expected.regions', []),
        ];

        return [
            'recommendation_hash' => hash('sha256', json_encode([
                $type,
                $normalizedQuery,
                $insight['insight_type'] ?? null,
                $expectedEntity,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
            'type' => $type,
            'query' => $query,
            'normalized_query' => $normalizedQuery,
            'insight_type' => (string) ($insight['insight_type'] ?? ''),
            'expected_entity' => $expectedEntity,
            'impact_score' => $impactScore,
            'confidence' => $confidence,
            'evidence' => [
                'signal_count' => (int) ($insight['signal_count'] ?? 0),
                'impressions' => (int) ($insight['impressions'] ?? 0),
                'clicks' => (int) ($insight['clicks'] ?? 0),
                'demand_weight' => (int) ($insight['demand_weight'] ?? 0),
                'current_results_count' => (int) ($insight['current_results_count'] ?? 0),
                'top_result' => $insight['top_result'] ?? null,
                'recommendation' => $insight['recommendation'] ?? null,
            ],
            'status' => SearchDemandRecommendation::STATUS_PROPOSED,
        ];
    }

    private function actionType(string $insightType): ?string
    {
        return match ($insightType) {
            'COVERAGE_GAP' => 'ADD_PRODUCT',
            'ALIAS_GAP' => 'ADD_ALIAS',
            'RANKING_GAP' => 'IMPROVE_RANKING',
            'BRAND_GAP' => 'ADD_ALIAS',
            'REGION_GAP' => 'ADD_REGION_VARIANT',
            'LOW_COVERAGE' => 'IMPROVE_SUPPLY',
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $insight
     */
    private function impactScore(array $insight, string $type): float
    {
        $demandWeight = (int) ($insight['demand_weight'] ?? 0);
        $clicks = (int) ($insight['clicks'] ?? 0);
        $base = min(70, log(max(1, $demandWeight), 1.6));
        $clickBoost = min(15, $clicks * 0.5);
        $typeWeight = match ($type) {
            'ADD_PRODUCT' => 15,
            'ADD_ALIAS' => 12,
            'ADD_REGION_VARIANT' => 10,
            'IMPROVE_RANKING' => 8,
            'IMPROVE_SUPPLY' => 6,
            default => 0,
        };

        return round(min(100, $base + $clickBoost + $typeWeight), 2);
    }

    /**
     * @param array<string, mixed> $insight
     */
    private function confidence(array $insight): float
    {
        $signalCount = (int) ($insight['signal_count'] ?? 0);
        $demandWeight = (int) ($insight['demand_weight'] ?? 0);
        $hasExpectedEntity = data_get($insight, 'expected.brands', []) !== []
            || data_get($insight, 'expected.regions', []) !== [];
        $score = 35 + min(25, $signalCount * 5) + min(25, $demandWeight / 20);

        if ($hasExpectedEntity) {
            $score += 15;
        }

        return round(min(100, $score), 2);
    }

    private function normalize(string $query): string
    {
        $query = Str::lower(trim($query));
        $query = preg_replace('/\s+/u', ' ', $query) ?? '';

        return trim($query);
    }
}
