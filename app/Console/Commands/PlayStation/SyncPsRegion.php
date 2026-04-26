<?php

namespace App\Console\Commands\PlayStation;

use App\Models\PlayStation\PlayStationAlt;
use App\Models\PlayStation\PlayStationCategory;
use App\Models\PlayStation\PlayStationRegion;
use App\Jobs\UpdatePlayStationSkuDataJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Client as HTTPClient;
use PlaystationStoreApi\Client;
use PlaystationStoreApi\Enum\CategoryEnum;
use PlaystationStoreApi\Enum\RegionEnum;
use PlaystationStoreApi\Request\RequestProductList;
use PlaystationStoreApi\ValueObject\Pagination;

class SyncPsRegion extends Command
{
    protected $signature = 'ps:sync-region {region_id?}';

    protected $description = 'Fetch all SKUs and queue detail updates for a PlayStation region';

    public function handle()
    {
        $regionId = $this->argument('region_id') ?? '44d8bb20-653e-431e-8ad0-c0a365f68d2f'; // Default to US if not provided
        $region = PlayStationRegion::find($regionId);

        if (!$region) {
            $this->error("Region not found: $regionId");
            return;
        }

        $this->info("Starting full sync for region: {$region->name} ({$region->slug})");

        // Phase 1: Fetch all SKUs
        $categories = PlayStationCategory::all();
        $client = new Client(
            RegionEnum::from($region->slug), 
            new HTTPClient(['base_uri' => 'https://web.np.playstation.com/api/graphql/v1/', 'timeout' => 10])
        );

        foreach ($categories as $category) {
            $this->info("Scanning category: {$category->name}...");
            
            try {
                $pre_request = RequestProductList::createFromCategory(CategoryEnum::from($category->id), new Pagination(1, 0));
                $pre_request = $client->get($pre_request);
                $totalCount = $pre_request['data']['categoryGridRetrieve']['pageInfo']['totalCount'];

                $this->info("Found $totalCount products. Importing SKUs...");

                $size = 1000;
                for ($i = 0; $i < $totalCount; $i += $size) {
                    $request = RequestProductList::createFromCategory(CategoryEnum::from($category->id), new Pagination($size, $i));
                    $request = $client->get($request);
                    $products = $request['data']['categoryGridRetrieve']['products'];

                    if (empty($products)) break;

                    $products_insert = [];
                    foreach ($products as $product) {
                        $products_insert[] = [
                            'sku' => $product['id'],
                            'category_id' => $category->id,
                            'name' => $product['name'],
                            'region_id' => $region->id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }

                    PlayStationAlt::insertOrIgnore($products_insert);
                    $this->output->write(".");
                }
                $this->info("\nCategory {$category->name} SKUs imported.");

            } catch (\Exception $e) {
                $this->error("Error fetching category {$category->name}: " . $e->getMessage());
            }
        }

        // Phase 2: Queue Details Update
        $this->info("Queueing detail updates for all SKUs in region...");
        $items = PlayStationAlt::where('region_id', $region->id)->get(['sku']);
        
        foreach ($items as $item) {
            UpdatePlayStationSkuDataJob::dispatch(
                sku: $item->sku,
                regionSlug: $region->slug,
                regionId: $region->id
            )->onQueue('high');
        }

        $this->info("Successfully queued " . $items->count() . " updates on the 'high' queue.");
    }
}
