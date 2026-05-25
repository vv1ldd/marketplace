<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DiscoveryHealthCommand extends Command
{
    protected $signature = 'discovery:health {--json : Output machine-readable JSON}';

    protected $description = 'Report discovery graph health: brands, regions, intersections, links, and broken nodes.';

    public function handle(): int
    {
        $stats = $this->stats();

        if ($this->option('json')) {
            $this->line(json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return $stats['broken'] > 0 ? self::FAILURE : self::SUCCESS;
        }

        $this->line('Discovery Health');
        $this->line('----------------');
        $this->line("Brands: {$stats['brands']}");
        $this->line("Regions: {$stats['regions']}");
        $this->line("Intersections: {$stats['intersections']}");
        $this->line("Canonical identities: {$stats['canonical_identities']}");
        $this->line("Commerce entities: {$stats['commerce_entities']}");
        $this->line("Commerce links: {$stats['commerce_links']}");
        $this->line("Metric rows: {$stats['commerce_metrics']}");
        $this->line("Broken: {$stats['broken']}");

        foreach ($stats['checks'] as $name => $value) {
            $label = str_replace('_', ' ', $name);
            $this->line(" - {$label}: {$value}");
        }

        if ($stats['broken'] > 0) {
            $this->error('Discovery graph has broken references or missing materialized rows.');

            return self::FAILURE;
        }

        $this->info('Discovery graph looks healthy.');

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    public function stats(): array
    {
        $missingTables = collect([
            'canonical_product_identities',
            'commerce_entities',
            'commerce_entity_links',
            'commerce_entity_metrics',
            'products',
            'provider_products',
        ])->reject(fn (string $table): bool => Schema::hasTable($table))->values();

        if ($missingTables->isNotEmpty()) {
            return [
                'brands' => 0,
                'regions' => 0,
                'intersections' => 0,
                'canonical_identities' => 0,
                'commerce_entities' => 0,
                'commerce_links' => 0,
                'commerce_metrics' => 0,
                'broken' => $missingTables->count(),
                'checks' => [
                    'missing_tables' => $missingTables->implode(', '),
                ],
            ];
        }

        $brands = DB::table('canonical_product_identities')
            ->whereNotNull('brand')
            ->where('brand', '<>', '')
            ->distinct()
            ->count('brand');

        $regions = DB::table('canonical_product_identities')
            ->whereNotNull('region')
            ->where('region', '<>', '')
            ->distinct()
            ->count('region');

        $intersections = DB::table('canonical_product_identities')
            ->whereNotNull('brand')
            ->where('brand', '<>', '')
            ->whereNotNull('region')
            ->where('region', '<>', '')
            ->selectRaw('lower(brand) as brand_key, lower(region) as region_key')
            ->distinct()
            ->get()
            ->count();

        $canonicalIdentities = DB::table('canonical_product_identities')->count();
        $commerceEntities = DB::table('commerce_entities')->count();
        $commerceLinks = DB::table('commerce_entity_links')->count();
        $commerceMetrics = DB::table('commerce_entity_metrics')->count();

        $missingCanonicalTargets = DB::table('commerce_entity_links as links')
            ->leftJoin('canonical_product_identities as targets', 'targets.id', '=', 'links.link_id')
            ->where('links.link_type', 'canonical_identity')
            ->whereNull('targets.id')
            ->count();

        $missingProductTargets = DB::table('commerce_entity_links as links')
            ->leftJoin('products as targets', 'targets.id', '=', 'links.link_id')
            ->where('links.link_type', 'product')
            ->whereNull('targets.id')
            ->count();

        $missingProviderTargets = DB::table('commerce_entity_links as links')
            ->leftJoin('provider_products as targets', 'targets.id', '=', 'links.link_id')
            ->where('links.link_type', 'provider_product')
            ->whereNull('targets.id')
            ->count();

        $entitiesWithoutLinks = DB::table('commerce_entities as entities')
            ->leftJoin('commerce_entity_links as links', 'links.commerce_entity_id', '=', 'entities.id')
            ->whereNull('links.id')
            ->count();

        $entitiesWithoutMetrics = DB::table('commerce_entities as entities')
            ->leftJoin('commerce_entity_metrics as metrics', 'metrics.commerce_entity_id', '=', 'entities.id')
            ->whereNull('metrics.id')
            ->count();

        $emptyCanonicalIdentities = $canonicalIdentities === 0 ? 1 : 0;

        $broken = $emptyCanonicalIdentities
            + $missingCanonicalTargets
            + $missingProductTargets
            + $missingProviderTargets
            + $entitiesWithoutLinks
            + $entitiesWithoutMetrics;

        return [
            'brands' => $brands,
            'regions' => $regions,
            'intersections' => $intersections,
            'canonical_identities' => $canonicalIdentities,
            'commerce_entities' => $commerceEntities,
            'commerce_links' => $commerceLinks,
            'commerce_metrics' => $commerceMetrics,
            'broken' => $broken,
            'checks' => [
                'empty_canonical_identities' => $emptyCanonicalIdentities,
                'missing_canonical_targets' => $missingCanonicalTargets,
                'missing_product_targets' => $missingProductTargets,
                'missing_provider_targets' => $missingProviderTargets,
                'entities_without_links' => $entitiesWithoutLinks,
                'entities_without_metrics' => $entitiesWithoutMetrics,
            ],
        ];
    }
}
