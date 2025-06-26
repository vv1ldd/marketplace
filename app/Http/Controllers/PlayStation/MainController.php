<?php

namespace App\Http\Controllers\PlayStation;

use App\Http\Controllers\Controller;
use App\Http\Services\BinanceService;
use App\Http\Services\YmService;
use App\Models\PlayStation\PlayStation;
use App\Models\PlayStation\PlayStationAlt;
use App\Models\PlayStation\PlayStationCategory;
use App\Models\PlayStation\PlayStationRegion;
use App\Models\PlayStation\PlayStationRegionCategory;
use App\Models\YmSenderLog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PlaystationStoreApi\Client;
use PlaystationStoreApi\Enum\CategoryEnum;
use PlaystationStoreApi\Enum\RegionEnum;
use GuzzleHttp\Client as HTTPClient;
use PlaystationStoreApi\Request\RequestConceptByProductId;
use PlaystationStoreApi\Request\RequestProductById;
use PlaystationStoreApi\Request\RequestProductList;
use PlaystationStoreApi\ValueObject\Pagination;

class MainController extends Controller
{
    public string $API_GRAPHQL = 'https://web.np.playstation.com/api/graphql/v1/';
    public string $API_REST = 'https://store.playstation.com/store/api/chihiro/00_09_000/container/';

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws \PlaystationStoreApi\Exception\ResponseException
     */
    public function allFromRegion(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => 'required|exists:App\Models\PlayStation\PlayStationRegion',
        ]);

        $categories = PlayStationCategory::all()->toArray();

        $region_id = $data['id'];
        $alt = $data['alt'];

        $region = PlayStationRegion::where('id', $region_id)->first();

        ini_set('max_execution_time', 1200);

        $size = 1000;

        $client = new Client(RegionEnum::from($region->slug), new HTTPClient(['base_uri' => $this->API_GRAPHQL, 'timeout' => 5]));

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

                PlayStationAlt::insertOrIgnore([
                    'region_id' => $region_id,
                    'sku' => $product['id'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                PlayStationRegionCategory::updateOrCreate([
                    'region_id' => $region_id,
                    'category_id' => $category['id'],
                ], [
                    'total_count' => $totalCount,
                ]);

            }

        }

        return response()->json(['message' => 'success',]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws \PlaystationStoreApi\Exception\ResponseException
     */
    public function detailFromRegion(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => 'required|exists:App\Models\PlayStation\PlayStationRegion',
            'alt' => 'required|boolean',
        ]);

        $region_id = $data['id'];
        $alt = $data['alt'];

        if ($alt) {
            $query = PlayStationAlt::where('region_id', $region_id);
        } else {
            $query = PlayStation::where('region_id', $region_id);
        }

        $parse_data = $query->where(function ($query) {
            $query->whereNotNull('data');
            $query->orWhere('updated_at', '<', now()->subHours(3));
        })->get(['sku']);

        if (empty($parse_data)) {
            return response()->json(['success' => false,]);
        }

        ini_set('max_execution_time', 12000);

        $region_id = $data['id'];

        $region = PlayStationRegion::where('id', $region_id)->first();

        if ($alt) {
//            [$lang, $region] = explode('-', $region->slug);

//            $client_old = new \GuzzleHttp\Client(['base_uri' => $this->API_REST . $region . '/' . $lang . '/', 'timeout' => 5]);
            $client = new Client(RegionEnum::from($region->slug), new HTTPClient(['base_uri' => $this->API_GRAPHQL, 'timeout' => 5]));

        } else {
            $client = new Client(RegionEnum::from($region->slug), new HTTPClient(['base_uri' => $this->API_GRAPHQL, 'timeout' => 5]));
        }

//        dd($parse_data);

        foreach ($parse_data as $item) {

//            dd($item);

            if ($alt) {
                $query = PlayStationAlt::where('region_id', $region_id);

                try {
                    $result = $client->get(new RequestConceptByProductId($item->sku));
                } catch (\Exception $exception) {
                    continue;
                }

            } else {
                $query = PlayStation::where('region_id', $region_id);

                $result = $client->get(new RequestProductById($item->sku));
            }

            if (!empty($result)) {
                $query->where('sku', $item->sku)->update([
                    'data' => json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'concept_id' => (int)data_get($result, 'data.productRetrieve.concept.id'),
                    'base_price' => data_get($result, 'data.productRetrieve.price.basePriceValue', 0),
                    'price_with_discount' => data_get($result, 'data.productRetrieve.price.discountedValue', 0),
                    'name' => data_get($result, 'data.productRetrieve.concept.name'),
                    'updated_at' => now()
                ]);
            }
        }

        return response()->json([
            'success' => true
        ]);
    }

    /**
     * @return JsonResponse
     */
    public function categories(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => PlayStationCategory::all(),
        ]);
    }

    /**
     * @return JsonResponse
     */
    public function regions(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => PlayStationRegion::all(),
        ]);
    }
}
