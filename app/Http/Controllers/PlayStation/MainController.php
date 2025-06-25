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
     * @throws ConnectionException
     */
    public function sendToMarket(Request $request)
    {
        $data = $request->validate([
            'skus' => 'nullable|array|min:1',
            'lang_region_id' => 'required|uuid',
            'price_region_id' => 'required|uuid',
        ]);

        $lang_region_id = $data['lang_region_id'];
        $price_region_id = $data['price_region_id'];

        $start = time();

        $items = \DB::table('play_station_alts as t1')
            ->select([
                't1.sku',
                \DB::raw('ROUND(t1.base_price / 100, 2) as base_price'),
                \DB::raw('ROUND(t1.price_with_discount / 100, 2) as price_with_discount'),
                't2.data',
                't2.name as name'
            ])
            ->leftJoin('play_station_alts as t2', function ($join) use ($data) {
                $join->on('t1.sku', '=', 't2.sku')
                    ->where('t2.region_id', '=', $data['lang_region_id']);
            })
            ->where('t1.region_id', '=', $data['price_region_id'])
            ->where('t1.price_with_discount', '>', 0)
            ->whereNotNull('t2.data');

        if (!empty($data['skus'])) {
            $items = $items->whereIn('t1.sku', $data['skus']);
        }

        $items = $items->get();

        if (empty($items)) {
            return response()->json([
                'success' => false,
                'message' => 'No items found',
            ], 400);
        }

        ini_set('max_execution_time', 1200);

        $finished_data = [];

        $binance_service = new BinanceService();

        $usdt_try = $binance_service->tickerPrice('USDTTRY');
        $usdt_rub = $binance_service->tickerPrice('USDTRUB');

        foreach ($items as $key => $item) {

            $tags = [];

            $data = json_decode($item->data, true);

            $concept = data_get($data, 'data.productRetrieve.concept');

            if (!$concept) {
                continue;
            }

            $price_with_discount = round((($item->price_with_discount / $usdt_try) * $usdt_rub) * (1 + env('PS_TAX', 35) / 100));
            $base_price = round((($item->base_price / $usdt_try) * $usdt_rub) * (1 + env('PS_TAX', 35) / 100));

            if ($price_with_discount < 300) {
                continue;
            }

            if (count($concept['media'])) {
                $concept['media'] = array_reverse($concept['media']);
                $pictures = [];
                $videos = [];
                foreach ($concept['media'] as $media) {
                    if ($media['type'] !== 'VIDEO') {
                        $pictures[] = $media['url'];
                    } else {
                        $videos[] = $media['url'];
                    }

                }

                if (count($videos) > 6) {
                    $videos = array_slice($videos, 0, 6);
                }
            }

            if (count($concept['selectableProducts']['purchasableProducts'])) {

                $metadata = $concept['selectableProducts']['purchasableProducts'];

                $params = [];

                // Платформа/ы

                if ($platforms = data_get($metadata, '0.platforms')) {

                    $platformName = implode(' & ', $platforms);

                    $tags = $platforms;

                    $params[] = [
                        'name' => 'Платформа',
                        'value' => $platformName,
                    ];
                }

                // Жанр
                $genres = [];
                if (!empty($concept['combinedLocalizedGenres'])) {
                    foreach ($concept['combinedLocalizedGenres'] as $genre) {
                        $genres[] = $genre['value'];
                    }
                }

                if (!empty($genres)) {
                    $params[] = [
                        'name' => 'Жанр',
                        'value' => implode(', ', $genres),
                    ];
                }

                // Режим игры
                if (!empty($concept['compatibilityNotices'])) {

                    foreach ($concept['compatibilityNotices'] as $notice) {

                        if ($notice['type'] === 'NO_OF_PLAYERS') {
                            $players = $notice['value'];
                            $mode = $players == '1' ? 'одиночный' : 'мультиплеер';

                            $params[] = [
                                'name' => 'Режим игры',
                                'value' => $mode,
                            ];
                        }
                    }
                }
            }

            if (isset($players)) {

                $count_players = "1";

                if ($players > 1 && $players < 100) {
                    $count_players = "до $players";
                }
            }

            $name = "Игра {$item->name}";

            if (isset($platformName)) {
                $name .= " для $platformName";
            }

            $name .= ", электронный ключ активации, TR";

            $description = '';

            if (!empty($concept['descriptions'])) {
                foreach ($concept['descriptions'] as $desc) {
                    if ($desc['type'] !== 'SHORT') {
                        $description .= $desc['value'];
                    }
                }
            }

            $finished_data[] = [
                "offer" => [
                    "offerId" => $item->sku,
                    "name" => $name,
                    'marketCategoryId' => config('services.ym.category_id', 70301474),
                    'pictures' => $pictures ?? [],
                    ...(isset($concept['publisherName']) ? ['vendor' => $concept['publisherName']] : []),
                    "description" => $description,
                    "parameterValues" => [
                        ...(isset($platformName) ? [
                            [
                                "parameterId" => 45128695,
                                "value" => $platformName,
                            ]
                        ] : []),
                        [
                            "parameterId" => 37693330,
                            "value" => "электронный ключ",
                        ],
                        [
                            "parameterId" => 37972050,
                            "value" => "без сервиса активации",
                        ],
                        [
                            "parameterId" => 45132091,
                            "value" => "сервис активации",
                        ],
                        [
                            "parameterId" => 16382542,
                            "value" => "Инструкции по активации будут отправлены вам по электронной почте вместе с кодом активации в течение 10 минут после покупки.",
                        ],
                        [
                            "parameterId" => 37919810,
                            "value" => "все страны",
                        ],
                        ...(isset($mode) ? [
                            [
                                "parameterId" => 45131673,
                                "value" => $mode
                            ]
                        ] : []),
                        ...(isset($count_players) ? [
                            [
                                "parameterId" => 39984210,
                                "value" => $count_players
                            ]
                        ] : [])
                    ],
                    "downloadable" => true,
                    'basicPrice' => [
                        "value" => $price_with_discount,
                        "currencyId" => "RUR",
                        ...($base_price > $price_with_discount ? [
                            "discountBase" => $base_price
                        ] : [])
                    ],
                    ...(!empty($tags) ? ['tags' => $tags] : []),
                    'params' => $params ?? [],
                    ...(!empty($videos) ? ['videos' => $videos] : [])
                ]
            ];
        }

        $finished_data_chunks = [];

        $error_bag = [];

        $send_id = Str::random();

        if (count($finished_data) > 20) {
            $finished_data_chunks = array_chunk($finished_data, 20);
        }

        if (!empty($finished_data_chunks)) {
            foreach ($finished_data_chunks as $chunk) {

                $res = $this->senderToYm(
                    lang_region_id: $lang_region_id,
                    price_region_id: $price_region_id,
                    chunk: $chunk,
                    send_id: $send_id
                );

                if (!$res['success']) {
                    $error_bag[] = $res['error'];
                }
            }
        } else {
            $res = $this->senderToYm(
                lang_region_id: $lang_region_id,
                price_region_id: $price_region_id,
                chunk: $finished_data,
                send_id: $send_id
            );

            if (!$res['success']) {
                $error_bag[] = $res['error'];
            }
        }

        return response()->json([
            'success' => empty($error_bag),
            'error_bag' => $error_bag,
            'send_id' => $send_id,
            'total' => count($finished_data),
            'on_sent' => count($finished_data) - count($error_bag) * 100,
            'seconds_spent' => time() - $start
        ], empty($error_bag) ? 200 : 400);

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
     * @param string $lang_region_id
     * @param string $price_region_id
     * @param array $chunk
     * @param string $send_id
     * @return array
     */
    private function senderToYm(string $lang_region_id, string $price_region_id, array $chunk, string $send_id): array
    {
        $ym_sender_log = YmSenderLog::create([
            'lang_region_id' => $lang_region_id,
            'price_region_id' => $price_region_id,
            'send_id' => $send_id,
            'request' => json_encode($chunk, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'status' => 'pending',
            'created_at' => now()
        ]);

        $service = new YmService();

        try {
            $response = $service->offerMappingsUpdate($chunk);

            $ym_sender_log->update([
                'response' => json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => now(),
                'status' => 'success'
            ]);

        } catch (\Exception $e) {
            $ym_sender_log->update([
                'response' => $e->getMessage(),
                'updated_at' => now(),
                'status' => 'error'
            ]);

            return [
                'success' => false,
                'send_id' => $send_id,
                'error' => json_decode($e->getMessage(), true),
            ];
        }

        return ['success' => true, 'send_id' => $send_id];
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
