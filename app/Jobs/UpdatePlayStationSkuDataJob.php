<?php

namespace App\Jobs;

use App\Models\PlayStation\PlayStationAlt;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use PlaystationStoreApi\Client;
use PlaystationStoreApi\Enum\RegionEnum;
use GuzzleHttp\Client as HTTPClient;
use PlaystationStoreApi\Request\RequestConceptByProductId;

class UpdatePlayStationSkuDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    public function __construct(
        public string $sku,
        public string $regionSlug,
        public string $regionId,
        public string $base_uri = 'https://web.np.playstation.com/api/graphql/v1/'
    )
    {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $client = new Client(RegionEnum::from($this->regionSlug), new HTTPClient(['base_uri' => $this->base_uri, 'timeout' => 30, 'verify' => false,]));

        $result = $client->get(new RequestConceptByProductId($this->sku));

        $data = data_get($result, 'data.productRetrieve.concept.selectableProducts.purchasableProducts');
        $concept_id = (int)data_get($result, 'data.productRetrieve.concept.id');

        if (data_get($result, 'errors.0')) {
            return;
        }

        if (empty($data)) {

            $data[] = data_get($result, 'data.productRetrieve');;

            if (empty($data)) {
                return;
            }
        }

        foreach ($data as $product) {
            if ($this->sku === $product['id']) {
                PlayStationAlt::where('region_id', $this->regionId)
                    ->where('sku', $this->sku)
                    ->update([
                        'data' => json_encode($product, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                        'concept_id' => $concept_id,
                        'base_price' => data_get($product, 'price.basePriceValue', 0),
                        'price_with_discount' => data_get($product, 'price.discountedValue', 0),
                        'end_discount_stamp' => data_get($product, 'price.endTime') ? Carbon::parse(data_get($product, 'price.endTime'))->format('Y-m-d H:i:s') : null,
                        'name' => $product['name'] ?? data_get($product, 'concept.name'),
                        'updated_at' => now()
                    ]);
            }
        }
    }
}
