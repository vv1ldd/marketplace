<?php

namespace App\Jobs;

use App\Models\PlayStation\PlayStationAlt;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use PlaystationStoreApi\Client;
use PlaystationStoreApi\Enum\RegionEnum;
use GuzzleHttp\Client as HTTPClient;
use PlaystationStoreApi\Request\RequestConceptByProductId;

class UpdatePlayStationSkuDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $sku,
        public string $regionSlug,
        public string    $regionId,
        public bool   $alt = false,
        public string $base_uri = 'https://web.np.playstation.com/api/graphql/v1/'
    )
    {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $client = new Client(RegionEnum::from($this->regionSlug), new HTTPClient(['base_uri' => $this->base_uri, 'timeout' => 5, 'verify' => false,]));

        $result = $client->get(new RequestConceptByProductId($this->sku));

        PlayStationAlt::where('region_id', $this->regionId)
            ->where('sku', $this->sku)
            ->update([
                'data' => json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'concept_id' => (int)data_get($result, 'data.productRetrieve.concept.id'),
                'base_price' => data_get($result, 'data.productRetrieve.price.basePriceValue', 0),
                'price_with_discount' => data_get($result, 'data.productRetrieve.price.discountedValue', 0),
                'name' => data_get($result, 'data.productRetrieve.concept.name'),
                'updated_at' => now()
            ]);
    }
}
