<?php

namespace App\Http\Controllers\PlayStation;

use App\Http\Controllers\Controller;
use App\Models\PlayStation\PlayStation;
use App\Models\PlayStation\PlayStationAlt;
use App\Models\PlayStation\PlayStationCategory;
use App\Models\PlayStation\PlayStationRegion;
use App\Models\PlayStation\PlayStationRegionCategory;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use PlaystationStoreApi\Client;
use PlaystationStoreApi\Enum\CategoryEnum;
use PlaystationStoreApi\Enum\RegionEnum;
use GuzzleHttp\Client as HTTPClient;
use PlaystationStoreApi\Request\RequestProductById;
use PlaystationStoreApi\Request\RequestProductList;
use PlaystationStoreApi\ValueObject\Pagination;

class MainController extends Controller
{
    public string $API_GRAPHQL = 'https://web.np.playstation.com/api/graphql/v1/';
    public string $API_REST = 'https://store.playstation.com/store/api/chihiro/00_09_000/container/';

    /**
     * @throws ConnectionException
     */
    public function sendToMarket(Request $request)
    {
        $data = $request->validate([
            'sku' => 'nullable|string',
            'lang_region_id' => 'required|uuid',
            'price_region_id' => 'required|uuid',
        ]);

        $items = \DB::table('play_station_alts as t1')
            ->select([
                't1.sku',
                \DB::raw('ROUND(t1.price / 100, 2) as price'),
                't2.data',
            ])
            ->leftJoin('play_station_alts as t2', function ($join) use ($data) {
                $join->on('t1.sku', '=', 't2.sku')
                    ->where('t2.region_id', '=', $data['lang_region_id']);
            })
            ->where('t1.region_id', '=', $data['price_region_id'])
            ->whereNotNull('t2.data');

        if (!empty($data['sku'])) {
            $items = $items->where('t1.sku', '=', $data['sku']);
        }

        $items = $items->get();

        $finished_data = [];

        foreach ($items as $item) {
            $data = json_decode($item->data, true);

            if (count($data['images'])) {
                $pictures = [];
                foreach ($data['images'] as $image) {
                    $pictures[] = $image['url'];
                }
            }

            if (count($data['promomedia'])) {
                $videos = [];
                foreach ($data['promomedia'] as $promomedia) {
                    if(isset($promomedia['url'])) {
                        $videos[] = $promomedia['url'];
                    }
                }
            }

            if (count($data['metadata'])) {

                $metadata = $data['metadata'];

                $params = [];

                // Платформа
                if (!empty($metadata['playable_platform']['values'][0])) {
                    $platform = $metadata['playable_platform']['values'][0];
                    $platformName = match ($platform) {
                        'PS4™' => 'PlayStation 4',
                        'PS5™' => 'PlayStation 5',
                        default => $platform,
                    };

                    $params[] = [
                        'name' => 'Платформа',
                        'value' => $platformName,
                    ];
                }

                // Жанр
                $genres = [];
                if (!empty($metadata['genre']['values'])) {
                    $genres = array_merge($genres, $metadata['genre']['values']);
                }
                if (!empty($metadata['game_genre']['values'])) {
                    $genres = array_merge($genres, $metadata['game_genre']['values']);
                }

                // Здесь можно добавить маппинг английских значений на русские, если нужно
                $genreMap = [
                    'ARCADE' => 'Аркада',
                    'SHOOTER' => 'Шутер',
                    'SIMULATOR' => 'Симулятор',
                    'FIGHTING' => 'Файтинг',
                    // Добавь другие по мере необходимости
                ];

                $genres = array_unique(array_map(function ($g) use ($genreMap) {
                    return $genreMap[$g] ?? $g;
                }, $genres));

                if (!empty($genres)) {
                    $params[] = [
                        'name' => 'Жанр',
                        'value' => implode(', ', $genres),
                    ];
                }

                // Режим игры
                if (!empty($metadata['cn_numberOfPlayers']['values'][0])) {
                    $players = $metadata['cn_numberOfPlayers']['values'][0];
                    $mode = $players === '1' ? 'Одиночный' : 'Многопользовательский';

                    $params[] = [
                        'name' => 'Режим игры',
                        'value' => $mode,
                    ];
                }
            }


            $finished_data[] = [
                "offer" => [
                    "offerId" => $item->sku,
                    "name" => $data['name'],
                    'marketCategoryId' => config('services.ym.category_id', 70301474),
                    'pictures' => $pictures ?? [],
                    ...(isset($data['provider_name']) ? ['providerName' => $data['provider_name']] : []),
                    "description" => $data['long_desc'],
                    "additionalExpenses" => [
                        "value" => $item->price * 1.99, // курс лиры
                        "currencyId" => "RUR",
                    ],
                    'basicPrice' => [
                        "value" => $item->price * 1.99, // курс лиры
                        "currencyId" => "RUR",
                    ],
                    'params' => $params ?? [],
                    ...(!empty($videos) ? ['videos' => $videos] : [])
                ]
            ];
        }

        dd($finished_data);

        $service = new \App\Http\Services\YmService();

        $response = $service->offerMappingsUpdate($finished_data);

        return response()->json($response);
    }

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

                sleep(rand(1, 3));
            }

            sleep(rand(2, 5));
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
            $query->whereNull('data');
            $query->orWhere('updated_at', '<', now()->subDays(1));
        })->get(['sku']);

        if (empty($parse_data)) {
            return response()->json(['success' => false,]);
        }

        ini_set('max_execution_time', 12000);

        $region_id = $data['id'];

        $region = PlayStationRegion::where('id', $region_id)->first();

        if ($alt) {
            [$lang, $region] = explode('-', $region->slug);

            $client = new \GuzzleHttp\Client(['base_uri' => $this->API_REST . $region . '/' . $lang . '/', 'timeout' => 5]);

        } else {
            $client = new Client(RegionEnum::from($region->slug), new HTTPClient(['base_uri' => $this->API_GRAPHQL, 'timeout' => 5]));
        }

        foreach ($parse_data as $item) {

            if ($alt) {
                $query = PlayStationAlt::where('region_id', $region_id);

                try {
                    $result = $client->get('999/' . $item->sku);
                } catch (\Exception $exception) {
                    continue;
                }

            } else {
                $query = PlayStation::where('region_id', $region_id);

                $result = $client->get(new RequestProductById($item->sku));
            }

            if ($result->getStatusCode() === 204) {
                continue;
            } else {
                $result = json_decode($result->getBody()->getContents(), true);
            }


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
