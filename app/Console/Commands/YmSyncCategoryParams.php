<?php

namespace App\Console\Commands;

use App\Http\Services\YmService;
use App\Models\Category;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class YmSyncCategoryParams extends Command
{
    protected $signature = 'ym:sync-category-params
                            {--limit=50 : How many categories to process per run}
                            {--force : Re-fetch even if already fetched recently}
                            {--category= : Sync only a specific YM category ID}
                            {--sleep=1 : Seconds to sleep between API requests}';

    protected $description = 'Fetch and cache Yandex Market category parameters (product attribute schemas)';

    public function handle(): int
    {
        $limit      = (int) $this->option('limit');
        $force      = $this->option('force');
        $specificId = $this->option('category');
        $sleep      = (int) $this->option('sleep');

        // Use default credentials — category parameters are global, not shop-specific
        $service = new YmService();

        // If a specific category is provided, sync only that one
        if ($specificId) {
            $category = Category::where('ym_id', $specificId)->first();
            if (!$category) {
                $this->error("Category with ym_id={$specificId} not found in DB.");
                return self::FAILURE;
            }
            $this->info("Syncing: {$category->name} (ym_id: {$category->ym_id})...");
            $this->syncCategory($category, $service);
            $this->info('Done.');
            return self::SUCCESS;
        }

        // Build query: prioritize categories with the most products
        $query = Category::whereNotNull('ym_id')
            ->withCount('products')
            ->orderByDesc('products_count');

        if (!$force) {
            $query->where(function ($q) {
                $q->whereNull('parameters_fetched_at')
                  ->orWhere('parameters_fetched_at', '<', now()->subDays(30));
            });
        }

        $categories = $query->limit($limit)->get();

        if ($categories->isEmpty()) {
            $this->info('All categories are up to date. Use --force to re-fetch.');
            return self::SUCCESS;
        }

        $this->info("Syncing parameters for {$categories->count()} categories (limit: {$limit})...");
        $bar = $this->output->createProgressBar($categories->count());
        $bar->start();

        $success = 0;
        $failed  = 0;

        foreach ($categories as $category) {
            try {
                $this->syncCategory($category, $service);
                $success++;
            } catch (\Exception $e) {
                $msg = "Failed for category {$category->ym_id} ({$category->name}): " . $e->getMessage();
                Log::warning("YmSyncCategoryParams: {$msg}");
                $failed++;
            }

            $bar->advance();

            if ($sleep > 0) {
                sleep($sleep);
            }
        }

        $bar->finish();
        $this->newLine();
        $this->info("Done. ✅ Success: {$success}  ❌ Failed: {$failed}");

        return self::SUCCESS;
    }

    private function syncCategory(Category $category, YmService $service): void
    {
        $parameters = $service->getCategoryParameters((int) $category->ym_id);

        // Normalize raw API data into a clean format for the Filament form builder
        $schema = collect($parameters)->map(fn ($param) => [
            'id'           => $param['id'],
            'name'         => $param['name'],
            'type'         => $param['type'],           // ENUM | NUMERIC | BOOLEAN | TEXT
            'description'  => $param['description'] ?? null,
            'required'     => $param['required'] ?? false,
            'filtering'    => $param['filtering'] ?? false,
            'multivalue'   => $param['multivalue'] ?? false,
            'allow_custom' => $param['allowCustomValues'] ?? false,
            // For ENUM: key => label map (filter out nulls)
            'values'       => isset($param['values'])
                ? collect($param['values'])
                    ->mapWithKeys(fn ($v) => [(string) $v['id'] => $v['value']])
                    ->filter()  // remove null labels
                    ->all()
                : null,
            // For NUMERIC: unit label (e.g. "кг", "мм")
            'unit'         => $param['unit']['name'] ?? null,
            'constraints'  => $param['constraints'] ?? null,
        ])->values()->all();

        $category->update([
            'parameters_schema'    => $schema,
            'parameters_fetched_at' => now(),
        ]);
    }
}
