<?php

namespace App\Http\Controllers\PlayStation;

use App\Http\Controllers\Controller;
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

        $lang_region_id = $data['lang_region_id'];
        $price_region_id = $data['price_region_id'];

        $start = time();

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
            ->where('t1.price', '>', 0)
            ->whereNotNull('t2.data');

        if (!empty($data['sku'])) {
            $items = $items->where('t1.sku', '=', $data['sku']);
        }

        $items = $items->get();

        $finished_data = [];

        foreach ($items as $key => $item) {

            if ($item->price * 1.99 < 300) {
                continue;
            }

            $tags = [];

            $data = json_decode($item->data, true);

            if (count($data['images'])) {
                $pictures = [];
                foreach ($data['images'] as $image) {
                    $pictures[] = $image['url'];
                }
            }

            if (isset($data['mediaList']['screenshots']) && count($data['mediaList']['screenshots'])) {
                foreach ($data['mediaList']['screenshots'] as $screenshot) {
                    $pictures[] = $screenshot['url'];
                }
            }

            if (count($data['promomedia'])) {
                $videos = [];
                foreach ($data['promomedia'] as $promomedia) {
                    if (isset($promomedia['url'])) {
                        $videos[] = $promomedia['url'];
                    }
                }
            }

            if (count($data['metadata'])) {

                $metadata = $data['metadata'];

                $params = [];

                // Платформа/ы

                if ($platforms = data_get($data, 'skus.0.platforms')) {

                    $platformName = '';

                    if (in_array(18, $platforms)) {
                        $platformName .= 'PlayStation 5';
                        $tags[] = 'PS5';
                    }

                    if (in_array(13, $platforms)) {
                        $platformName .= ' & PlayStation 4';
                        $tags[] = 'PS4';
                    }

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
                    $mode = $players === '1' ? 'одиночный' : 'мультиплеер';

                    $params[] = [
                        'name' => 'Режим игры',
                        'value' => $mode,
                    ];
                }
            }

            if (isset($players)) {

                $count_players = "1";

                if ($players > 1 && $players < 100) {
                    $count_players = "до $players";
                }
            }

            $name = "Игра {$data['name']}";

            if (isset($platformName)) {
                $name .= " для $platformName";
            }

            $name .= ", электронный ключ активации, TR";

            $finished_data[] = [
                "offer" => [
                    "offerId" => $item->sku,
                    "name" => $name,
                    'marketCategoryId' => config('services.ym.category_id', 70301474),
                    'pictures' => $pictures ?? [],
                    ...(isset($data['provider_name']) ? ['vendor' => $data['provider_name']] : []),
                    "description" => $data['long_desc'],
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
                        "value" => $item->price * 1.99, // курс лиры
                        "currencyId" => "RUR",
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

        if (count($finished_data) > 100) {
            $finished_data_chunks = array_chunk($finished_data, 100);
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
            'time' => time() - $start
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
                    'price' => data_get($result, 'skus.0.price', 0),
                    'name' => data_get($result, 'name', ''),
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
