<?php

namespace App\Console\Commands;

use App\Models\CanonicalProductIdentity;
use App\Models\Product;
use App\Models\ProviderProduct;
use App\Models\WildflowCatalog;
use App\Services\CanonicalCategoryResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class ReclassifyDiscoveryIntentCommand extends Command
{
    protected $signature = 'catalog:reclassify-discovery-intent
                            {--dry-run : Compute discovery intent distribution without writing (default)}
                            {--apply : Persist discovery_intent to catalog tables}
                            {--source=wildflow : Source table: wildflow, identities, provider, products, all}
                            {--limit= : Limit rows scanned per source}
                            {--json : Emit report as JSON}
                            {--sample=10 : Sample rows per unclassified / migration bucket}';

    protected $description = 'Audit or backfill discovery_intent corridor assignments for live catalog SKUs (ADR 0040)';

    public function handle(CanonicalCategoryResolver $resolver): int
    {
        $apply = (bool) $this->option('apply');
        $dryRun = ! $apply;
        $source = (string) ($this->option('source') ?: ($apply ? 'all' : 'wildflow'));
        $limit = $this->option('limit');
        $limit = $limit !== null && $limit !== '' ? max(1, (int) $limit) : null;
        $sampleLimit = max(1, (int) ($this->option('sample') ?: 10));

        $rows = $this->collectRows($source, $limit);
        if ($rows->isEmpty()) {
            $this->warn('No catalog rows found for source='.$source.'.');

            return self::FAILURE;
        }

        $report = $this->buildReport($resolver, $rows, $source, $dryRun || ! $apply, $sampleLimit);

        if ($apply) {
            $report['persisted'] = $this->persistDiscoveryIntents($resolver, $source, $limit);
        }

        if ((bool) $this->option('json')) {
            $this->output->write(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL);

            return self::SUCCESS;
        }

        $this->renderReport($report, $dryRun || ! $apply);

        return self::SUCCESS;
    }

    /**
     * @return array<string, int>
     */
    private function persistDiscoveryIntents(CanonicalCategoryResolver $resolver, string $source, ?int $limit): array
    {
        if (! Schema::hasColumn('wildflow_catalogs', 'discovery_intent')) {
            $this->warn('discovery_intent column missing. Run migrations before --apply.');

            return ['updated' => 0];
        }

        $stats = [
            'wildflow_catalogs' => 0,
            'provider_products' => 0,
            'products' => 0,
            'canonical_product_identities' => 0,
        ];

        $sources = $source === 'all'
            ? ['wildflow', 'provider', 'products', 'identities']
            : [$source];

        foreach ($sources as $tableSource) {
            $rows = match ($tableSource) {
                'identities' => $this->identityRows($limit),
                'provider' => $this->providerProductRows($limit),
                'products' => $this->productRows($limit),
                default => $this->wildflowRows($limit),
            };

            foreach ($rows as $row) {
                $legacyCategory = is_string($row['legacy_category'] ?? null) && $row['legacy_category'] !== ''
                    ? $row['legacy_category']
                    : $resolver->fromPayload((array) ($row['payload'] ?? []), (array) ($row['context'] ?? []));

                $discoveryIntent = $resolver->discoveryIntent($legacyCategory, (array) ($row['context'] ?? []));

                $updated = match ($row['source']) {
                    'wildflow_catalogs' => WildflowCatalog::query()
                        ->whereKey($row['source_id'])
                        ->update(['discovery_intent' => $discoveryIntent]),
                    'provider_products' => ProviderProduct::query()
                        ->whereKey($row['source_id'])
                        ->update(['discovery_intent' => $discoveryIntent]),
                    'products' => Product::query()
                        ->whereKey($row['source_id'])
                        ->update(['discovery_intent' => $discoveryIntent]),
                    'canonical_product_identities' => CanonicalProductIdentity::query()
                        ->whereKey($row['source_id'])
                        ->update(['discovery_intent' => $discoveryIntent]),
                    default => 0,
                };

                if ($updated > 0) {
                    $stats[$row['source']] = ($stats[$row['source']] ?? 0) + 1;
                }
            }
        }

        return $stats;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function collectRows(string $source, ?int $limit): Collection
    {
        return match ($source) {
            'identities' => $this->identityRows($limit),
            'provider' => $this->providerProductRows($limit),
            'products' => $this->productRows($limit),
            'all' => $this->wildflowRows($limit)
                ->concat($this->identityRows($limit))
                ->concat($this->providerProductRows($limit))
                ->concat($this->productRows($limit)),
            default => $this->wildflowRows($limit),
        };
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function wildflowRows(?int $limit): Collection
    {
        if (! Schema::hasTable('wildflow_catalogs')) {
            return collect();
        }

        $query = WildflowCatalog::query()
            ->with('brand:id,name')
            ->orderBy('id');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get()->map(fn (WildflowCatalog $item) => [
            'source' => 'wildflow_catalogs',
            'source_id' => $item->id,
            'sku' => $item->sku,
            'legacy_category' => $item->canonical_category,
            'brand' => $item->brand?->name ?? $item->brand_name,
            'title' => $item->title,
            'payload' => $item->data ?? [],
            'context' => [
                $item->brand?->name ?? $item->brand_name,
                $item->title,
                $item->category,
                $item->reward_type,
                $item->type,
            ],
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function identityRows(?int $limit): Collection
    {
        if (! Schema::hasTable('canonical_product_identities')) {
            return collect();
        }

        $query = CanonicalProductIdentity::query()->orderBy('id');
        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get()->map(fn (CanonicalProductIdentity $identity) => [
            'source' => 'canonical_product_identities',
            'source_id' => $identity->id,
            'sku' => $identity->identity_slug,
            'legacy_category' => $identity->canonical_category,
            'brand' => $identity->brand,
            'title' => $identity->product_family,
            'payload' => [],
            'context' => [
                $identity->brand,
                $identity->product_family,
                $identity->platform,
                $identity->region,
            ],
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function providerProductRows(?int $limit): Collection
    {
        if (! Schema::hasTable('provider_products')) {
            return collect();
        }

        $query = ProviderProduct::query()->with('brand:id,name')->orderBy('id');
        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get()->map(fn (ProviderProduct $item) => [
            'source' => 'provider_products',
            'source_id' => $item->id,
            'sku' => $item->sku ?? (string) $item->id,
            'legacy_category' => $item->canonical_category,
            'brand' => $item->brand?->name,
            'title' => $item->name,
            'payload' => $item->data ?? [],
            'context' => [
                $item->brand?->name,
                $item->name,
                $item->category,
                $item->reward_type,
            ],
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function productRows(?int $limit): Collection
    {
        if (! Schema::hasTable('products')) {
            return collect();
        }

        $query = Product::query()->with('brand:id,name')->orderBy('id');
        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get()->map(fn (Product $item) => [
            'source' => 'products',
            'source_id' => $item->id,
            'sku' => $item->sku ?? (string) $item->id,
            'legacy_category' => $item->canonical_category,
            'brand' => $item->brand?->name,
            'title' => $item->name,
            'payload' => $item->data ?? [],
            'context' => [
                $item->brand?->name,
                $item->name,
                $item->category,
                $item->vendor,
            ],
        ]);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function buildReport(
        CanonicalCategoryResolver $resolver,
        Collection $rows,
        string $source,
        bool $dryRun,
        int $sampleLimit,
    ): array {
        $classified = [];
        $resolutionCounts = [];
        $legacyToIntent = [];
        $migrationShifts = [];
        $unclassifiedSamples = [];
        $shiftSamples = [];

        foreach ($rows as $row) {
            $legacyCategory = is_string($row['legacy_category'] ?? null) && $row['legacy_category'] !== ''
                ? $row['legacy_category']
                : $resolver->fromPayload((array) ($row['payload'] ?? []), (array) ($row['context'] ?? []));

            [$discoveryIntent, $resolution] = $resolver->discoveryIntentWithResolution(
                $legacyCategory,
                (array) ($row['context'] ?? [])
            );

            $classified[] = [
                ...$row,
                'legacy_category' => $legacyCategory,
                'discovery_intent' => $discoveryIntent,
                'discovery_intent_key' => $resolver->discoveryIntentKey($discoveryIntent),
                'resolution' => $resolution,
            ];

            $resolutionCounts[$resolution] = ($resolutionCounts[$resolution] ?? 0) + 1;
            $legacyToIntent[$legacyCategory][$discoveryIntent] = ($legacyToIntent[$legacyCategory][$discoveryIntent] ?? 0) + 1;

            $naiveIntent = $this->naiveLegacyIntent($legacyCategory);
            if ($naiveIntent !== $discoveryIntent) {
                $shiftKey = $legacyCategory.'→'.$discoveryIntent;
                $migrationShifts[$shiftKey] = ($migrationShifts[$shiftKey] ?? 0) + 1;

                if (count($shiftSamples[$shiftKey] ?? []) < $sampleLimit) {
                    $shiftSamples[$shiftKey][] = [
                        'source' => $row['source'],
                        'source_id' => $row['source_id'],
                        'sku' => $row['sku'],
                        'brand' => $row['brand'],
                        'title' => $row['title'],
                        'legacy_category' => $legacyCategory,
                        'discovery_intent' => $discoveryIntent,
                        'resolution' => $resolution,
                    ];
                }
            }

            if ($discoveryIntent === 'unclassified' && count($unclassifiedSamples) < $sampleLimit) {
                $unclassifiedSamples[] = [
                    'source' => $row['source'],
                    'source_id' => $row['source_id'],
                    'sku' => $row['sku'],
                    'brand' => $row['brand'],
                    'title' => $row['title'],
                    'legacy_category' => $legacyCategory,
                    'resolution' => $resolution,
                ];
            }
        }

        $intentCounts = collect($classified)
            ->countBy('discovery_intent')
            ->sortDesc()
            ->all();

        $total = count($classified);
        $unclassified = (int) ($intentCounts['unclassified'] ?? 0);

        arsort($migrationShifts);
        ksort($legacyToIntent);

        return [
            'generated_at' => now()->toIso8601String(),
            'dry_run' => $dryRun,
            'source' => $source,
            'summary' => [
                'rows_scanned' => $total,
                'intent_corridors' => count((array) config('catalog_taxonomy.intent_corridors', [])),
                'discovery_intent_distribution' => $intentCounts,
                'resolution_method_distribution' => $resolutionCounts,
                'unclassified_count' => $unclassified,
                'unclassified_pct' => $total > 0 ? round(($unclassified / $total) * 100, 2) : 0.0,
                'brand_override_count' => (int) ($resolutionCounts['brand_override'] ?? 0),
                'legacy_category_count' => (int) ($resolutionCounts['legacy_category'] ?? 0),
                'default_count' => (int) ($resolutionCounts['default'] ?? 0),
            ],
            'legacy_to_intent_matrix' => $legacyToIntent,
            'top_migration_shifts' => array_slice($migrationShifts, 0, 20, true),
            'samples' => [
                'unclassified' => $unclassifiedSamples,
                'migration_shifts' => $shiftSamples,
            ],
        ];
    }

    private function naiveLegacyIntent(string $legacyCategory): string
    {
        foreach ((array) config('catalog_taxonomy.intent_corridors', []) as $corridor => $config) {
            if (in_array($legacyCategory, (array) ($config['legacy_categories'] ?? []), true)) {
                return $corridor;
            }
        }

        return (string) config('catalog_taxonomy.discovery_default', 'unclassified');
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function renderReport(array $report, bool $dryRun): void
    {
        $summary = $report['summary'];

        $this->info(($dryRun ? 'Dry run' : 'Apply run').': discovery intent reclassification audit');
        $this->line('Source: '.$report['source']);
        $this->line('Rows scanned: '.$summary['rows_scanned']);
        $this->newLine();

        $this->info('Discovery intent distribution');
        foreach ($summary['discovery_intent_distribution'] as $intent => $count) {
            $pct = $summary['rows_scanned'] > 0
                ? round(($count / $summary['rows_scanned']) * 100, 1)
                : 0.0;
            $this->line(sprintf('  %-8s %5d  (%s%%)', $intent, $count, $pct));
        }

        $this->newLine();
        $this->info('Resolution methods');
        foreach ($summary['resolution_method_distribution'] as $method => $count) {
            $this->line(sprintf('  %-16s %5d', $method, $count));
        }

        $this->newLine();
        $this->line('Unclassified: '.$summary['unclassified_count'].' ('.$summary['unclassified_pct'].'%)');
        $this->line('Brand overrides: '.$summary['brand_override_count']);
        $this->line('Legacy category map: '.$summary['legacy_category_count']);

        if ($report['top_migration_shifts'] !== []) {
            $this->newLine();
            $this->info('Top migration shifts (naive legacy → resolved intent)');
            foreach ($report['top_migration_shifts'] as $shift => $count) {
                $this->line(sprintf('  %-55s %5d', $shift, $count));
            }
        }

        if (($report['samples']['unclassified'] ?? []) !== []) {
            $this->newLine();
            $this->warn('Unclassified samples');
            foreach ($report['samples']['unclassified'] as $sample) {
                $this->line(sprintf(
                    '  [%s:%s] %s | brand=%s | legacy=%s',
                    $sample['source'],
                    $sample['source_id'],
                    $sample['title'] ?: $sample['sku'],
                    $sample['brand'] ?: '-',
                    $sample['legacy_category']
                ));
            }
        }

        if (($report['persisted'] ?? []) !== []) {
            $this->newLine();
            $this->info('Persisted discovery_intent updates');
            foreach ($report['persisted'] as $table => $count) {
                $this->line(sprintf('  %-32s %5d', $table, $count));
            }
        }
    }
}
