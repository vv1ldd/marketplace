<?php

namespace App\Console\Commands;

use App\Models\CanonicalProductIdentity;
use App\Models\CanonicalProductIdentityOverride;
use App\Models\CanonicalProductIdentitySource;
use App\Services\CanonicalProductIdentityCurationService;
use App\Services\CanonicalProductIdentityIndexService;
use App\Services\ProductIndexingPolicyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RefreshCatalogIndexing extends Command
{
    protected $signature = 'catalog:refresh-indexing
                            {--limit= : Limit provider and seller products processed during rebuild}
                            {--skip-audit : Skip compact audit/readiness checks}
                            {--json : Emit the refresh summary as JSON}
                            {--fail-on-internal-review-rate= : Fail if internal_review percentage exceeds this threshold}
                            {--dry-run : Compute rebuild groups without writing identity rows}';

    protected $description = 'Refresh the canonical product identity index and report indexing readiness metrics';

    public function handle(
        CanonicalProductIdentityIndexService $index,
        CanonicalProductIdentityCurationService $curation,
        ProductIndexingPolicyService $policy,
    ): int {
        $startedAt = now();
        $limit = $this->positiveIntegerOption('limit');
        $threshold = $this->percentageOption('fail-on-internal-review-rate');
        if ($threshold === false) {
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $skipAudit = (bool) $this->option('skip-audit');

        $rebuild = $index->rebuild($limit, $dryRun);
        $tablesExist = $this->identityTablesExist();

        $report = [
            'generated_at' => now()->toIso8601String(),
            'duration_ms' => 0,
            'options' => [
                'limit' => $limit,
                'dry_run' => $dryRun,
                'skip_audit' => $skipAudit,
                'fail_on_internal_review_rate' => $threshold,
            ],
            'rebuild' => $rebuild,
            'summary' => [
                'missing_tables' => ! $tablesExist,
            ],
        ];

        if (! $tablesExist) {
            $report['duration_ms'] = $startedAt->diffInMilliseconds(now());
            $report['summary']['warnings'] = [
                'Canonical product identity tables do not exist yet. Run migrations before refreshing indexing.',
            ];

            return $this->finish($report, self::FAILURE);
        }

        $report['summary'] = $this->summary($curation, $policy, $skipAudit);
        $report['thresholds'] = $this->thresholdSummary($report['summary'], $threshold);
        $report['duration_ms'] = $startedAt->diffInMilliseconds(now());

        return $this->finish(
            $report,
            $report['thresholds']['failed'] ? self::FAILURE : self::SUCCESS,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(
        CanonicalProductIdentityCurationService $curation,
        ProductIndexingPolicyService $policy,
        bool $skipAudit,
    ): array {
        $policySummary = $this->policySummary($curation, $policy);
        $identityTotal = CanonicalProductIdentity::query()->count();
        $sourceTotal = CanonicalProductIdentitySource::query()->count();

        return [
            'missing_tables' => false,
            'identities_total' => $identityTotal,
            'sources_total' => $sourceTotal,
            'confidence_distribution' => $this->confidenceDistribution(),
            'source_type_distribution' => $this->sourceTypeDistribution(),
            'indexing_policy' => $policySummary['indexing_policy'],
            'overrides' => $this->overrideSummary(),
            'review_queue' => $policySummary['review_queue'],
            'offer_coverage' => [
                'identities_with_seller_offers' => CanonicalProductIdentity::query()
                    ->where('seller_offers_count', '>', 0)
                    ->count(),
                'identities_with_best_offer' => CanonicalProductIdentity::query()
                    ->whereNotNull('best_offer_product_id')
                    ->count(),
            ],
            'audit' => $skipAudit
                ? ['skipped' => true]
                : $this->auditSummary($identityTotal, $sourceTotal),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function policySummary(
        CanonicalProductIdentityCurationService $curation,
        ProductIndexingPolicyService $policy,
    ): array {
        $surfaceDistribution = [
            'public_index' => 0,
            'llm_only' => 0,
            'internal_review' => 0,
        ];
        $indexable = 0;
        $noindex = 0;
        $reviewQueueCount = 0;
        $newInternalReviewCount = 0;
        $pendingOverrideReviewCount = 0;
        $hasOverrideTable = $this->overrideTableExists();

        $query = CanonicalProductIdentity::query()
            ->select([
                'id',
                'fingerprint',
                'identity_slug',
                'canonical_category',
                'brand',
                'product_family',
                'face_value',
                'face_value_currency',
                'region',
                'platform',
                'confidence',
                'signals',
                'provider_candidates_count',
                'seller_offers_count',
                'best_offer_product_id',
                'last_seen_at',
                'created_at',
                'updated_at',
            ])
            ->orderBy('id');

        if ($hasOverrideTable) {
            $query->with('override');
        }

        $query->chunkById(500, function ($identities) use (
            $curation,
            $policy,
            $hasOverrideTable,
            &$surfaceDistribution,
            &$indexable,
            &$noindex,
            &$reviewQueueCount,
            &$newInternalReviewCount,
            &$pendingOverrideReviewCount,
        ): void {
            foreach ($identities as $identity) {
                $canonicalIdentity = $curation->applyApprovedOverrides($identity->toArray(), $identity);
                $selectedOffer = $identity->best_offer_product_id !== null
                    ? ['indexing' => ['indexable' => true]]
                    : null;
                $policyResult = $policy->forCanonicalProduct(
                    $canonicalIdentity,
                    $selectedOffer,
                    ['provider_candidates_count' => $identity->provider_candidates_count],
                    $identity,
                );

                $surface = (string) ($policyResult['surface'] ?? 'unknown');
                $surfaceDistribution[$surface] = ($surfaceDistribution[$surface] ?? 0) + 1;

                if ((bool) ($policyResult['indexable'] ?? false)) {
                    $indexable++;
                } else {
                    $noindex++;
                }

                if ($surface !== 'internal_review') {
                    continue;
                }

                $overrideStatus = $hasOverrideTable && $identity->relationLoaded('override')
                    ? $identity->getRelationValue('override')?->review_status
                    : null;

                if (in_array($overrideStatus, [
                    CanonicalProductIdentityOverride::STATUS_APPROVED,
                    CanonicalProductIdentityOverride::STATUS_IGNORED,
                ], true)) {
                    continue;
                }

                $reviewQueueCount++;

                if ($overrideStatus === null) {
                    $newInternalReviewCount++;
                }

                if ($overrideStatus === CanonicalProductIdentityOverride::STATUS_PENDING) {
                    $pendingOverrideReviewCount++;
                }
            }
        });

        return [
            'indexing_policy' => [
                'public_index' => $surfaceDistribution['public_index'] ?? 0,
                'indexable' => $indexable,
                'llm_only' => $surfaceDistribution['llm_only'] ?? 0,
                'internal_review' => $surfaceDistribution['internal_review'] ?? 0,
                'noindex' => $noindex,
                'surface_distribution' => $surfaceDistribution,
            ],
            'review_queue' => [
                'count' => $reviewQueueCount,
                'new_internal_review_count' => $newInternalReviewCount,
                'pending_override_review_count' => $pendingOverrideReviewCount,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function overrideSummary(): array
    {
        if (! $this->overrideTableExists()) {
            return [
                'table_exists' => false,
                'total' => 0,
                'approved' => 0,
                'status_distribution' => [],
            ];
        }

        return [
            'table_exists' => true,
            'total' => CanonicalProductIdentityOverride::query()->count(),
            'approved' => CanonicalProductIdentityOverride::query()
                ->where('review_status', CanonicalProductIdentityOverride::STATUS_APPROVED)
                ->count(),
            'status_distribution' => $this->bucketCounts(
                'canonical_product_identity_overrides',
                'review_status',
                'review_status_bucket',
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function auditSummary(int $identityTotal, int $sourceTotal): array
    {
        $sellerSourcesWithoutBestOffer = CanonicalProductIdentity::query()
            ->where('seller_offers_count', '>', 0)
            ->whereNull('best_offer_product_id')
            ->count();
        $providerOnlyIdentities = CanonicalProductIdentity::query()
            ->where('provider_candidates_count', '>', 0)
            ->where('seller_offers_count', 0)
            ->count();
        $staleIdentityCount = CanonicalProductIdentity::query()
            ->whereNull('last_seen_at')
            ->count();
        $multiSourceIdentityCount = $this->groupedSourceCount('count(*) > 1');

        $warnings = [];

        if ($identityTotal === 0) {
            $warnings[] = 'No canonical product identities are currently persisted.';
        }

        if ($sourceTotal === 0) {
            $warnings[] = 'No canonical product identity sources are currently persisted.';
        }

        if ($sellerSourcesWithoutBestOffer > 0) {
            $warnings[] = 'Some identities have seller sources but no persisted best offer.';
        }

        if ($staleIdentityCount > 0) {
            $warnings[] = 'Some identities are missing last_seen_at and may predate the refresh pipeline.';
        }

        return [
            'skipped' => false,
            'ready' => $warnings === [],
            'warnings' => $warnings,
            'coverage' => [
                'multi_source_identity_count' => $multiSourceIdentityCount,
                'seller_sources_without_best_offer' => $sellerSourcesWithoutBestOffer,
                'provider_only_identities' => $providerOnlyIdentities,
                'stale_identity_count' => $staleIdentityCount,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    private function thresholdSummary(array $summary, ?float $threshold): array
    {
        $identityTotal = max(0, (int) ($summary['identities_total'] ?? 0));
        $internalReview = (int) data_get($summary, 'indexing_policy.internal_review', 0);
        $rate = $identityTotal > 0 ? round(($internalReview / $identityTotal) * 100, 2) : 0.0;

        return [
            'internal_review_rate_percent' => $rate,
            'fail_on_internal_review_rate_percent' => $threshold,
            'failed' => $threshold !== null && $rate > $threshold,
        ];
    }

    private function finish(array $report, int $exitCode): int
    {
        if ((bool) $this->option('json')) {
            $this->output->write(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL);

            return $exitCode;
        }

        $this->render($report, $exitCode);

        return $exitCode;
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function render(array $report, int $exitCode): void
    {
        if ((bool) data_get($report, 'summary.missing_tables')) {
            foreach ((array) data_get($report, 'summary.warnings', []) as $warning) {
                $this->warn($warning);
            }

            return;
        }

        $summary = $report['summary'];
        $rebuild = $report['rebuild'];
        $thresholds = $report['thresholds'];

        $this->info((bool) data_get($report, 'options.dry_run') ? 'Catalog indexing refresh dry run complete.' : 'Catalog indexing refreshed.');
        $this->line('Generated at: '.$report['generated_at'].'; duration: '.$report['duration_ms'].'ms');
        $this->line('Identities touched: '.$rebuild['identities_touched'].'; identity groups discovered: '.$rebuild['identity_groups']);

        $this->table(['Metric', 'Value'], [
            ['Identities total', $summary['identities_total']],
            ['Sources total', $summary['sources_total']],
            ['Overrides total', data_get($summary, 'overrides.total', 0)],
            ['Approved overrides', data_get($summary, 'overrides.approved', 0)],
            ['Review queue', data_get($summary, 'review_queue.count', 0)],
            ['New internal review', data_get($summary, 'review_queue.new_internal_review_count', 0)],
            ['With seller offers', data_get($summary, 'offer_coverage.identities_with_seller_offers', 0)],
            ['With best offer', data_get($summary, 'offer_coverage.identities_with_best_offer', 0)],
            ['Internal review rate', $thresholds['internal_review_rate_percent'].'%'],
        ]);

        $this->table(['Confidence', 'Count'], $this->keyValueRows($summary['confidence_distribution']));
        $this->table(['Indexing policy', 'Count'], $this->keyValueRows([
            'public_index' => data_get($summary, 'indexing_policy.public_index', 0),
            'indexable' => data_get($summary, 'indexing_policy.indexable', 0),
            'llm_only' => data_get($summary, 'indexing_policy.llm_only', 0),
            'internal_review' => data_get($summary, 'indexing_policy.internal_review', 0),
            'noindex' => data_get($summary, 'indexing_policy.noindex', 0),
        ]));

        if ((bool) data_get($summary, 'audit.skipped')) {
            $this->line('Audit checks skipped.');
        } else {
            $audit = data_get($summary, 'audit', []);
            $this->line('Audit readiness: '.((bool) ($audit['ready'] ?? false) ? 'ready' : 'needs attention'));

            foreach ((array) ($audit['warnings'] ?? []) as $warning) {
                $this->warn($warning);
            }
        }

        if ($exitCode !== self::SUCCESS && (bool) data_get($thresholds, 'failed')) {
            $this->error(sprintf(
                'Internal review rate %.2f%% exceeds threshold %.2f%%.',
                $thresholds['internal_review_rate_percent'],
                $thresholds['fail_on_internal_review_rate_percent'],
            ));
        }
    }

    /**
     * @return array<string, int>
     */
    private function confidenceDistribution(): array
    {
        return $this->bucketCounts('canonical_product_identities', 'confidence', 'confidence_bucket');
    }

    /**
     * @return array<string, int>
     */
    private function sourceTypeDistribution(): array
    {
        return $this->bucketCounts('canonical_product_identity_sources', 'source_type', 'source_type_bucket');
    }

    /**
     * @return array<string, int>
     */
    private function bucketCounts(string $table, string $column, string $alias): array
    {
        return DB::table($table)
            ->selectRaw("COALESCE(NULLIF({$column}, ''), 'unknown') as {$alias}, COUNT(*) as total")
            ->groupByRaw("COALESCE(NULLIF({$column}, ''), 'unknown')")
            ->orderBy($alias)
            ->pluck('total', $alias)
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    private function groupedSourceCount(string $having): int
    {
        $subquery = DB::table('canonical_product_identity_sources')
            ->select('canonical_product_identity_id')
            ->groupBy('canonical_product_identity_id')
            ->havingRaw($having);

        return (int) DB::query()->fromSub($subquery, 'source_groups')->count();
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function keyValueRows(array $values): array
    {
        if ($values === []) {
            return [['none', 0]];
        }

        return collect($values)
            ->map(fn ($value, string $key) => [$key, $value])
            ->values()
            ->all();
    }

    private function positiveIntegerOption(string $name): ?int
    {
        $value = $this->option($name);

        return $value !== null && $value !== '' ? max(1, (int) $value) : null;
    }

    private function percentageOption(string $name): float|false|null
    {
        $value = $this->option($name);
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            $this->error("The --{$name} option must be a numeric percentage.");

            return false;
        }

        $percentage = (float) $value;
        if ($percentage < 0 || $percentage > 100) {
            $this->error("The --{$name} option must be between 0 and 100.");

            return false;
        }

        return $percentage;
    }

    private function identityTablesExist(): bool
    {
        return Schema::hasTable('canonical_product_identities')
            && Schema::hasTable('canonical_product_identity_sources');
    }

    private function overrideTableExists(): bool
    {
        return Schema::hasTable('canonical_product_identity_overrides');
    }
}
