<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\CatalogGroup;
use App\Services\MappingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillBrandCatalogGroups extends Command
{
    protected $signature = 'app:backfill-brand-catalog-groups
        {--dry-run : Show changes without writing them}
        {--limit= : Limit processed brands}
        {--reclassify-finance : Move already classified payment/crypto brands into the Finance group}';

    protected $description = 'Backfill missing Brand catalog_group_id values using the storefront catalog classifier';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $reclassifyFinance = (bool) $this->option('reclassify-finance');
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;

        $groupsById = CatalogGroup::query()->pluck('name', 'id');
        if ($groupsById->isEmpty()) {
            $this->error('No catalog groups found.');
            return self::FAILURE;
        }

        $financeGroupId = (int) (CatalogGroup::query()
            ->where('slug', 'finansy')
            ->orWhere('name', 'Финансы')
            ->value('id') ?: 0);

        $query = Brand::query()
            ->where(function ($brandQuery) {
                $brandQuery
                    ->whereHas('providerProducts')
                    ->orWhereHas('wildflowCatalogs')
                    ->orWhereHas('products')
                    ->orWhereHas('mappings');
            })
            ->orderBy('id');

        if (! $reclassifyFinance) {
            $query->whereNull('catalog_group_id');
        }

        if ($limit !== null && $limit > 0) {
            $query->limit($limit);
        }

        $brands = $query->get(['id', 'name', 'catalog_group_id']);
        $mode = $reclassifyFinance ? 'brands for finance reclassification' : 'brands without main category';
        $this->info(($dryRun ? 'Dry run: ' : '').'Processing '.$brands->count().' '.$mode.'...');

        $changed = 0;
        $unresolved = 0;
        $byGroup = [];

        $bar = $this->output->createProgressBar($brands->count());
        foreach ($brands as $brand) {
            $context = $this->contextForBrand((int) $brand->id);
            $groupId = MappingService::guessGroupIdByName((string) $brand->name, $context);

            if ($reclassifyFinance && $groupId !== $financeGroupId) {
                $unresolved++;
                $bar->advance();
                continue;
            }

            if ($groupId && $groupsById->has($groupId) && (int) $brand->catalog_group_id !== (int) $groupId) {
                $changed++;
                $byGroup[$groupId] = ($byGroup[$groupId] ?? 0) + 1;

                if (! $dryRun) {
                    $brand->update(['catalog_group_id' => $groupId]);
                }
            } else {
                $unresolved++;
            }

            $bar->advance();
        }
        $bar->finish();
        $this->newLine(2);

        foreach ($byGroup as $groupId => $count) {
            $this->line(($groupsById[$groupId] ?? "Group {$groupId}").": {$count}");
        }

        $this->info(($dryRun ? 'Would update' : 'Updated')." {$changed} brands. Unresolved: {$unresolved}.");

        return self::SUCCESS;
    }

    private function contextForBrand(int $brandId): string
    {
        $samples = DB::table('provider_products')
            ->where('brand_id', $brandId)
            ->orderByDesc('is_active')
            ->limit(12)
            ->get(['name', 'category', 'reward_type'])
            ->flatMap(fn ($row) => [
                $row->name,
                $row->category,
                $row->reward_type,
            ])
            ->filter()
            ->implode(' ');

        if ($samples !== '') {
            return $samples;
        }

        return DB::table('provider_brand_mappings')
            ->where('brand_id', $brandId)
            ->limit(12)
            ->pluck('external_name')
            ->filter()
            ->implode(' ');
    }
}
