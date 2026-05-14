<?php

namespace App\Console\Commands;

use App\Http\Services\YmService;
use App\Models\Category;
use App\Models\Shop;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class YmSyncCategories extends Command
{
    protected $signature = 'ym:sync-categories';
    protected $description = 'Fetch the entire Yandex Market categories tree and save it to the local categories table';

    public function handle()
    {
        $this->info('Starting Yandex Category Synchronization...');

        $shop = Shop::where('is_active', true)->whereNotNull('api_key')->first();

        if (!$shop) {
            $this->error('No active shop with API credentials found.');
            return 1;
        }

        $this->info("Using credentials from shop: {$shop->name}");

        $service = new YmService($shop);

        try {
            $tree = $service->getCategoriesTree();
            $this->processCategory($tree);
            $this->info('Synchronization completed successfully!');
        } catch (\Exception $e) {
            $this->error("Error: {$e->getMessage()}");
            return 1;
        }

        return 0;
    }

    protected function processCategory(array $node, ?int $parentId = null)
    {
        $ymId = $node['id'];
        $name = $node['name'];

        $this->comment("Processing: {$name} ($ymId)");

        $category = Category::updateOrCreate(
            ['ym_id' => $ymId],
            [
                'name' => $name,
                'parent_id' => $parentId,
                'is_active' => true,
            ]
        );

        if (!empty($node['children'])) {
            foreach ($node['children'] as $child) {
                $this->processCategory($child, $category->id);
            }
        }
    }
}
