<?php

namespace App\Http\Controllers\PlayStation;

use App\Http\Controllers\Controller;
use App\Models\PlayStation\PlayStation;
use App\Models\PlayStation\PlayStationCategory;
use App\Models\PlayStation\PlayStationRegion;
use App\Models\PlayStation\PlayStationRegionCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PlaystationStoreApi\Client;
use PlaystationStoreApi\Enum\CategoryEnum;
use PlaystationStoreApi\Enum\RegionEnum;
use GuzzleHttp\Client as HTTPClient;
use PlaystationStoreApi\Request\RequestProductById;
use PlaystationStoreApi\Request\RequestProductList;
use PlaystationStoreApi\ValueObject\Pagination;

class MainController extends Controller
{
    public string $API_URL = 'https://web.np.playstation.com/api/graphql/v1/';

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws \PlaystationStoreApi\Exception\ResponseException
     */
    public function allFromRegion(Request $request): JsonResponse
    {
        $data = $request->validate(['id' => 'required|exists:App\Models\PlayStation\PlayStationRegion']);

        $categories = PlayStationCategory::all()->toArray();

        $region_id = $data['id'];

        $region = PlayStationRegion::where('id', $region_id)->first();

        ini_set('max_execution_time', 1200);

        $size = 1000;

        $client = new Client(RegionEnum::from($region->slug), new HTTPClient(['base_uri' => $this->API_URL, 'timeout' => 5]));

        foreach ($categories as $category) {

            $pre_request = RequestProductList::createFromCategory(CategoryEnum::from($category['id']), new Pagination(1, 0));
            $pre_request = $client->get($pre_request);
            $totalCount = $pre_request['data']['categoryGridRetrieve']['pageInfo']['totalCount'];

            $count = PlayStation::where('region_id', $region_id)
                ->where('category_id', $category['id'])->count();

            if ($count >= $totalCount) {
                continue;
            }

            for ($i = 0; $i < $totalCount; $i += $size) {
                $request = RequestProductList::createFromCategory(CategoryEnum::from($category['id']), new Pagination($size, $i));

                $request = $client->get($request);

                $products = $request['data']['categoryGridRetrieve']['products'];

                if (empty($products)) {
                    break;
                }

                $products_insert = [];

                foreach ($products as $product) {
                    $products_insert[] = [
                        'sku' => $product['id'],
                        'category_id' => $category['id'],
                        'region_id' => $region_id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                PlayStation::insertOrIgnore($products_insert);

                PlayStationRegionCategory::updateOrCreate([
                    'region_id' => $region_id,
                    'category_id' => $category['id'],
                ], [
                    'total_count' => $totalCount,
                ]);

                sleep(rand(1, 3));
            }

            sleep(rand(2, 5));
        }

        return response()->json(['message' => 'success',]);
    }

    public function detailFromRegion(Request $request)
    {
        $data = $request->validate(['id' => 'required|exists:App\Models\PlayStation\PlayStationRegion']);

        $region_id = $data['id'];

        $parse_data = PlayStation::where('region_id', $region_id)
            ->where(function ($query) {
                $query->whereNull('data');
                $query->orWhere('updated_at', '<', now()->subDays(1));
            })
            ->get(['sku']);

        if (empty($parse_data)) {
            return response()->json(['success' => false,]);
        }

        ini_set('max_execution_time', 1200);

        $region_id = $data['id'];

        $region = PlayStationRegion::where('id', $region_id)->first();

        $client = new Client(RegionEnum::from($region->slug), new HTTPClient(['base_uri' => $this->API_URL, 'timeout' => 5]));

        foreach ($parse_data as $item) {
            $query = PlayStation::where('region_id', $region_id);

            $result = $client->get(new RequestProductById($item->sku));

            if (!empty($result)) {
                $query->where('sku', $item->sku)->update([
                    'data' => json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'updated_at' => now()
                ]);
            }
        }

        return response()->json([
            'success' => true
        ]);
    }
}
