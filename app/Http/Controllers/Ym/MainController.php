<?php

namespace App\Http\Controllers\Ym;

use App\Http\Controllers\Controller;
use App\Http\Controllers\OrderController;
use App\Http\Services\BinanceService;
use App\Http\Services\YmService;
use App\Jobs\ItemsYmShow;
use App\Jobs\QuarantineRemove;
use App\Jobs\UpdateYmPrices;
use App\Models\PlayStation\PlayStationAlt;
use App\Models\Settings;
use App\Models\WildflowCatalog;
use App\Models\YmSenderLog;
use App\Services\DescriptionGenerator;
use App\Services\ImageGenerator;
use App\Services\MeanlyService;
use App\Services\WildflowService;
use Carbon\Carbon;
use Illuminate\Bus\Batch;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MainController extends Controller
{
    private int $ps_tax;

    public function __construct(int $tax = null)
    {
        $this->ps_tax = $tax ?? (int)Settings::get('PS_TAX', 35);
    }

    public function descriptionGenerate()
    {
        $generator = new DescriptionGenerator();
        WildflowCatalog::whereNotNull('bussiness_id')
            ->select(['id', 'sku', 'category', 'data']) // только нужное
            ->chunk(100, function ($items) use ($generator) {

                foreach ($items as $item) {
                    $data = $item->data['data'];
                    $category = $item->category;

                    if (!isset($data['product']['regions'][0]['code'])) {
                        continue;
                    }

                    $item->update([
                        'description' => $generator->generate(
                            country_code: $data['product']['regions'][0]['code'],
                            currency: $data['product']['currency']['code'],
                            category: $category
                        )
                    ]);
                }
            });

        return response()->json([
            'success' => true,
        ]);
    }

    public function imageGenerate(): \Illuminate\Http\JsonResponse
    {

        $image_generator = new ImageGenerator();

        WildflowCatalog::whereNotNull('bussiness_id')
            ->select(['id', 'sku', 'category', 'data']) // только нужное
            ->chunk(100, function ($items) use ($image_generator) {

                foreach ($items as $item) {
                    $data = $item->data['data'];

                    if(!isset($data['product']['regions'][0]['code'])){
                        continue;
                    }

                    $generateData = [
                        'sku' => $item->sku,
                        'price' => $data['price'],
                        'category' => $item->category,
                        'symbol' => $data['product']['currency']['symbol'],
                        'region_code' => $data['product']['regions'][0]['code'],
                    ];

                    try {

                        $savePath = $image_generator->generate($generateData);

                        $item->update([
                            'image' => $savePath,
                        ]);

                    } catch (\Exception $exception) {
                        \Log::error('Ошибка генерации фото', [$exception->getMessage()]);
                        continue;
                    }
                }
            });

        return response()->json([
            'success' => true,
            'msg' => 'Image generated successfully',
        ]);
    }

    /**
     * @throws ConnectionException
     */
    public function prepareToDeleteItems(Request $request)
    {
        $data = $request->validate([
            'skus' => 'nullable|array',
        ]);

        $query = WildflowCatalog::query();

        if (!empty($data['skus'])) {
            $query->whereIn('id', $data['skus']);
        }

        $skus = $query
            ->whereNotNull('bussiness_id')
            ->pluck('sku')
            ->toArray();

        $service = new YmService();

        return $service->offerDelete($skus);
    }

    public function prepareToItemsShow(Request $request)
    {
        $data = $request->validate([
            'skus' => 'nullable|array|min:1',
            'lang_region_id' => 'required|uuid',
            'price_region_id' => 'required|uuid',
        ]);

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
            $items = $items->whereIn('sku', $data['skus']);
        }

        $items = $items->get();

        if (empty($items)) {
            return response()->json([
                'success' => false,
                'message' => 'No items found',
            ], 400);
        }

        $finished_data = [];

        foreach ($items as $item) {
            $finished_data[] = [
                'offerId' => $item->sku,
            ];
        }

        $finished_data_chunk = array_chunk($finished_data, 500);

        foreach ($finished_data_chunk as $key => $chunk) {
            ItemsYmShow::dispatch($chunk)->delay(now()->addSeconds(10 + $key * 10));
        }

        return response()->json([
            'success' => true,
            'queued' => count($finished_data_chunk),
        ]);

    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws ConnectionException
     */
    public function prepareToUpdatePriceItems(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'skus' => 'nullable|array|min:1',
            'lang_region_id' => 'required|uuid',
            'price_region_id' => 'required|uuid',
        ]);

        $items = \DB::table('play_station_alts as t1')
            ->select([
                't1.sku',
                't1.end_discount_stamp',
                \DB::raw('ROUND(t1.base_price / 100, 2) as base_price'),
                \DB::raw('ROUND(t1.price_with_discount / 100, 2) as price_with_discount'),
                't2.name as name'
            ])
            ->leftJoin('play_station_alts as t2', function ($join) use ($data) {
                $join->on('t1.sku', '=', 't2.sku')
                    ->where('t2.region_id', '=', $data['lang_region_id']);
            })
            ->where('t1.region_id', '=', $data['price_region_id'])
            ->where('t1.is_manual', false)
            ->where('t1.price_with_discount', '>', 0);

        if (!empty($data['skus'])) {
            $items = $items->whereIn('sku', $data['skus']);
        }

        $items = $items->get();

        if (empty($items)) {
            return response()->json([
                'success' => false,
                'message' => 'No items found',
            ], 400);
        }

        $binance_service = new BinanceService();

        $usdt_try = $binance_service->tickerPrice('USDTTRY');
        $usdt_rub = $binance_service->tickerPrice('USDTRUB');

        $finished_data = [];

        foreach ($items as $item) {

            [$price_with_discount, $base_price] = $this->pricesCalc($item, $usdt_try, $usdt_rub);

            if ($price_with_discount < (Settings::get('YM_MIN_SUM_FOR_UPDATE') * 100)) {
                $price_with_discount = Settings::get('YM_MIN_SUM_FOR_UPDATE') * 100;
                $base_price = $price_with_discount;
            }

            if ($price_with_discount !== $base_price &&
                $item->end_discount_stamp &&
                now()->diffInHours($item->end_discount_stamp) < (float)Settings::get('YM_DIFF_IN_HOURS_FOR_SALE')
            ) {
                $price_with_discount = $base_price;
            }

            $finished_data[] = [
                'offerId' => $item->sku,
                'price' => [
                    "value" => $price_with_discount,
                    "currencyId" => "RUR",
                    ...($base_price > $price_with_discount ? [
                        "discountBase" => $base_price
                    ] : [])
                ]
            ];
        }

        $finished_data_chunks = array_chunk($finished_data, 500);

        $jobs_update_price = [];

        foreach ($finished_data_chunks as $key => $chunk) {
            $jobs_update_price[] = (new UpdateYmPrices($chunk))
                ->delay(now()->addSeconds(1200 + $key * 12))
                ->onQueue('low');
        }

        Bus::batch($jobs_update_price)
            ->then(function () use ($finished_data) {

                $finished_data_quarantine = array_column($finished_data, 'offerId');
                $finished_data_chunks = array_chunk($finished_data_quarantine, 200);

                $jobs_quarantine = [];

                foreach ($finished_data_chunks as $key => $chunk) {
                    $jobs_quarantine[] = (new QuarantineRemove($chunk))
                        ->delay(now()->addSeconds($key * 5))
                        ->onQueue('low');
                }

                Bus::batch($jobs_quarantine)->onQueue('low')->dispatch();
            })
            ->onQueue('low')
            ->dispatch();


        return response()->json([
            'success' => true,
            'queued' => count($finished_data_chunks),
        ]);
    }

    public function sendItemsWildflow(Request $request)
    {
        ini_set('max_execution_time', 12000);

        $start = time();

        $items = WildflowCatalog::whereNotNull('bussiness_id')
            ->get();

        $binance_service = new BinanceService();

        $usdt_rub = $binance_service->tickerPrice('USDTRUB');

        $tax = Settings::get('YM_TAX', 30);

        $category_id = (int)Settings::get('YM_CATEGORY_ID', config('services.ym.category_id', 70301474));

        $finished_data = [];

        $postfixes = \DB::table('mapping')
            ->pluck('postfix', 'tag');
        $vendors = \DB::table('mapping')
            ->pluck('vendor', 'tag');

        $wildflow_service = new WildflowService();

        $exchange_rates = $wildflow_service->getExchangeRates();

        $ratesMap = collect($exchange_rates)
            ->mapWithKeys(function ($item) {
                return [
                    $item['to_currency']['code'] => $item['rate']
                ];
            });

        foreach ($items as $item) {

            $sku = $item->sku;
            $data = $item->data['data'];
            $product = $data['product'];

            $name = '';

            if ($product['reward_type_text'] === 'Gift-Card') {
                $name .= 'Подарочная карта ';
            }

            $name .= $product['title'] . ' ' . $data['price'] . $product['currency']['code'];

            $tag = $item->category;
            $postfix = $postfixes[$tag] ?? null;
            $vendor = $vendors[$tag] ?? 'Нет бренда';

            if ($postfix) {
                $name .= ' ' . $postfix;
            }

            $currency = $product['currency']['code'];
            $price = $data['buying_price'];

            if ($currency !== 'USD') {
                if (isset($ratesMap[$currency])) {
                    $price = $price / $ratesMap[$currency];
                } else {
                    continue;
                }
            }

            $price = $price * $usdt_rub;
            $price = round($price * (1 + $tax / 100), 2);

            if($item->image) {
                $media = config('app.url') . '/' . $item->image;
            } else {
                $media = $product['image'];
            }


//            $description = $product['description'] ? json_decode($product['description'], true) : [];
//            $text = $desc['content'][0]['description'] ?? '';
//
//            $terms = data_get($description, 'content.2.description');
//            $description = $text . $terms;

            $finished_data[] = [
                "offer" => [
                    "offerId" => $sku,
                    "name" => $name,
                    'marketCategoryId' => $category_id,
                    'pictures' => [$media],
                    'vendor' => $vendor,
                    "description" => $item->description,
                    "parameterValues" => [
//                        ...(isset($platformName) ? [
//                            [
//                                "parameterId" => 45128695,
//                                "value" => $platformName,
//                            ]
//                        ] : []),
                        [
                            "parameterId" => 37821410,
                            "value" => (int)$data['price'],
                        ],
                        [
                            "parameterId" => 37919770,
                            "value" => "в течение 1 месяца",
                        ],
                        [
                            "parameterId" => 37978250,
                            "value" => "пополнение счета",
                        ],
//                        [
//                            "parameterId" => 37972050,
//                            "value" => $name,
//                        ],
                        [
                            "parameterId" => 37693330,
                            "value" => "электронный ключ",
                        ],
                        [
                            "parameterId" => 16382542,
                            "value" => "Ключ предназначен для активации на сервисе активации MEANLY. Инструкция по активации будет отправлена вам на электронную почту вместе с кодом в течение 10 минут после покупки.",
                        ],
//                        ...($is_group ? [
//                            [
//                                "parameterId" => 200,
//                                "value" => $item->concept_id
//                            ]
//                        ] : []),
                        [
                            "parameterId" => 37919810,
                            "value" => data_get($product, 'regions.0.name'),
                        ],
                    ],
                    "downloadable" => true,
                    'basicPrice' => [
                        "value" => $price,
                        "currencyId" => "RUR",
//                        ...($base_price > $price_with_discount ? [
//                            "discountBase" => $base_price
//                        ] : [])
                    ],
//                    ...(!empty($tags) ? ['tags' => $tags] : []),
//                    'params' => $params ?? [],
//                    ...(!empty($videos) ? ['videos' => $videos] : [])
                ]
            ];
        }

        $error_bag = [];

        $send_id = Str::random();

        $finished_data_chunks = array_chunk($finished_data, 20);

        foreach ($finished_data_chunks as $chunk) {
            $res = $this->sendItems(
                chunk: $chunk,
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
     * @throws ConnectionException
     */
    public function prepareToSendItems(Request $request)
    {
        $data = $request->validate([
            'skus' => 'nullable|array|min:1',
            'lang_region_id' => 'required|uuid',
            'price_region_id' => 'required|uuid',
        ]);

        $lang_region_id = $data['lang_region_id'];
        $price_region_id = $data['price_region_id'];

        ini_set('max_execution_time', 12000);

        $start = time();

        $items = \DB::table('play_station_alts as t1')
            ->select([
                't1.sku',
                \DB::raw('ROUND(t1.base_price / 100, 2) as base_price'),
                \DB::raw('ROUND(t1.price_with_discount / 100, 2) as price_with_discount'),
                't2.data',
                't2.name as name',
                't1.region_id as region_id',
                't1.concept_id as concept_id',
                't2.is_group'
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

        $finished_data = [];

        $binance_service = new BinanceService();

        $usdt_try = $binance_service->tickerPrice('USDTTRY');
        $usdt_rub = $binance_service->tickerPrice('USDTRUB');

        foreach ($items as $key => $item) {

            $tags = [];

            $is_group = $item->is_group;

            $data = json_decode($item->data, true);

            if (!$data) {
                continue;
            }

            [$price_with_discount, $base_price] = $this->pricesCalc($item, $usdt_try, $usdt_rub);

            if ($price_with_discount < 300) {
                continue;
            }

            if ($data['media'] = data_get($data, 'media')) {
                $data['media'] = array_reverse($data['media']);
                $pictures = [];
                $videos = [];
                foreach ($data['media'] as $media) {
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

            $params = [];

            // Платформа/ы
            if ($platforms = data_get($data, 'platforms')) {

                $platformName = implode(' & ', $platforms);

                $tags = $platforms;

                $params[] = [
                    'name' => 'Платформа',
                    'value' => $platformName,
                ];
            }

            // Жанр
            $genres = [];
            if (!empty($data['combinedLocalizedGenres'])) {
                foreach ($data['combinedLocalizedGenres'] as $genre) {
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
            if (!empty($data['compatibilityNotices'])) {

                foreach ($data['compatibilityNotices'] as $notice) {

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
//            }

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

            if (!empty($data['descriptions'])) {
                foreach ($data['descriptions'] as $desc) {
                    if ($desc['type'] === 'LONG' || $desc['type'] === 'COMPATIBILITY_NOTICE') {
                        $description .= $desc['value'];
                    }
                }
            }

            $description = str_replace('facebook', '(соц. сеть на букву ф)', $description);

            $finished_data[] = [
                "offer" => [
                    "offerId" => $item->sku,
                    "name" => $name,
                    'marketCategoryId' => (int)Settings::get('YM_CATEGORY_ID', config('services.ym.category_id', 70301474)),
                    'pictures' => $pictures ?? [],
                    ...(isset($data['publisherName']) ? ['vendor' => $data['publisherName']] : []),
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
                            "value" => "цифровое",
                        ],
                        [
                            "parameterId" => 45130810,
                            "value" => $item->name,
                        ],
                        [
                            "parameterId" => 16382542,
                            "value" => "Ключ предназначен для активации на сервисе активации ИГРОС. Инструкция по активации будет отправлена вам на электронную почту вместе с кодом в течение 10 минут после покупки.",
                        ],
                        ...($is_group ? [
                            [
                                "parameterId" => 200,
                                "value" => $item->concept_id
                            ]
                        ] : []),
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

        $error_bag = [];

        $send_id = Str::random();

        $finished_data_chunks = array_chunk($finished_data, 20);

        YmSenderLog::where('lang_region_id', $lang_region_id)
            ->where('price_region_id', $send_id)
            ->delete();

        foreach ($finished_data_chunks as $chunk) {
            $res = $this->sendItems(
                lang_region_id: $lang_region_id,
                price_region_id: $price_region_id,
                chunk: $chunk,
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

    public function prepareSendStockItemsWildflow(Request $request)
    {
        ini_set('max_execution_time', 12000);

        $start = time();

        $items = WildflowCatalog::whereNotNull('bussiness_id')
            ->get();

        $finished_data = [];

        $stock = Settings::get('YM_STOCK', 10);

        foreach ($items as $item) {

            $finished_data[] = [
                'sku' => $item->sku,
                'items' => [
                    [
                        'count' => $stock,
                    ]
                ]
            ];
        }

        $service = new YmService();

        $service->offerStocks($finished_data);

        return response()->json([
            'success' => true,
            'seconds_spent' => time() - $start
        ]);
    }

    public function prepareSendStockItems(Request $request)
    {
        $data = $request->validate([
            'skus' => 'nullable|array|min:1',
            'lang_region_id' => 'required|uuid',
            'price_region_id' => 'required|uuid',
            'stock' => 'nullable|integer|min:0|max:2000000000',
        ]);

        $start = time();

        $stock = $data['stock'] ?? 100;

        $items = \DB::table('play_station_alts as t1')
            ->select(['t1.sku'])
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

        ini_set('max_execution_time', 12000);

        $finished_data = [];

        foreach ($items as $item) {

            $finished_data[] = [
                'sku' => $item->sku,
                'items' => [
                    [
                        'count' => $stock,
                    ]
                ]
            ];
        }

        $service = new YmService();

        $finished_data_chunks = [];

        if (count($finished_data) > 1000) {
            $finished_data_chunks = array_chunk($finished_data, 1000);
        }

//        dd($finished_data_chunks[0]);

        if (!empty($finished_data_chunks)) {
            foreach ($finished_data_chunks as $chunk) {
                $service->offerStocks($chunk);
            }
        } else {
            $service->offerStocks($finished_data);
        }

        return response()->json([
            'success' => true,
            'seconds_spent' => time() - $start
        ]);
    }

    /**
     * @param string $lang_region_id
     * @param string $price_region_id
     * @param array $chunk
     * @param string $send_id
     * @return array
     */
    private function sendItems(array $chunk, string $send_id, string $lang_region_id = null, string $price_region_id = null): array
    {
//        $ym_sender_log = YmSenderLog::create([
//            'lang_region_id' => $lang_region_id,
//            'price_region_id' => $price_region_id,
//            'send_id' => $send_id,
//            'request' => json_encode($chunk, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
//            'status' => 'pending',
//            'created_at' => now()
//        ]);

        $service = new YmService();

        $meanly_service = new MeanlyService();

        try {
            $service->offerMappingsUpdate($chunk);

//            $res = $meanly_service->sendOffers($chunk);

//            $ym_sender_log->update([
//                'response' => json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
//                'updated_at' => now(),
//                'status' => 'success'
//            ]);

//            PlayStationAlt::whereIn('sku', array_column($chunk, 'sku'))->update(['send_to_ym_at' => now()]);

        } catch (\Exception $e) {

            \Log::error("send items error", [$e->getMessage()]);

//            $ym_sender_log->update([
//                'response' => json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
//                'updated_at' => now(),
//                'status' => 'error'
//            ]);

            return [
                'success' => false,
                'send_id' => $send_id,
                'error' => json_decode($e->getMessage(), true),
            ];
        }

        return ['success' => true, 'send_id' => $send_id];
    }

    private function updatePriceItems()
    {

    }

    /**
     * @param $item
     * @param $usdt_try
     * @param $usdt_rub
     * @return array
     */
    public function pricesCalc($item, $usdt_try, $usdt_rub): array
    {
        $price_with_discount = round((($item->price_with_discount / $usdt_try) * $usdt_rub) * (1 + $this->ps_tax / 100));
        $base_price = round((($item->base_price / $usdt_try) * $usdt_rub) * (1 + $this->ps_tax / 100));

        return [$price_with_discount, $base_price];
    }

    public function notification($token, Request $request): \Illuminate\Http\JsonResponse
    {
        $log = \Log::channel('ym_notification')->withContext([
            'log_id' => Str::random(8),
        ]);

        $log->info('-------------');

        $log->debug('Пришло уведомление', [
            'request' => $request->all(),
            'headers' => $request->headers->all(),
            'ip' => $request->ip()
        ]);

        $realToken = Settings::get('YM_NOTIFICATION_TOKEN', config('services.ym.notification_token'));

        if ($token !== $realToken) {

            $log->error("Невалидные токен", [
                'exception' => 'Unauthorized token',
                'token' => $token
            ]);

            return response()->json([
                'error' => [
                    'message' => 'Unauthorized token',
                    'type' => 'UNKNOWN'
                ]
            ], 400);
        }

        try {
            $data = $request->validate([
                'notificationType' => 'required|string|in:PING,ORDER_CREATED,ORDER_STATUS_UPDATED',
                'orderId' => 'nullable|numeric',
                'campaignId' => 'nullable|numeric',

                'status' => 'required_if:notificationType,ORDER_STATUS_UPDATED|string',
                'substatus' => 'required_if:notificationType,ORDER_STATUS_UPDATED|string',
            ]);
        } catch (ValidationException $exception) {

            $log->error("Невалидные данные", [
                'exception' => $exception->getMessage(),
                'errors' => $exception->errors()
            ]);

            return response()->json([
                'error' => [
                    'message' => $exception->getMessage(),
                    'type' => 'WRONG_EVENT_FORMAT'
                ]
            ], 400);

        }

        switch ($data['notificationType']) {
            case 'ORDER_CREATED':

                $log->info('notificationType ORDER_CREATED');

                $order_controller = new OrderController('ORDER_CREATED');
                $result = $order_controller->created($data);

                if (!$result['success']) {

                    $log->error("Не удалось создать заказ", [
                        'result' => $result
                    ]);

                    return response()->json([
                        'error' => [
                            'message' => $result['error'],
                            'type' => data_get($result, 'code') === 1 ? 'DUPLICATED_EVENT' : 'UNKNOWN'
                        ]
                    ], 400);
                }

                $log->debug("Заказ создан", ['result' => $result]);

                break;
            case 'ORDER_STATUS_UPDATED':

                $log->info('notificationType ORDER_STATUS_UPDATED');

                $order_controller = new OrderController('ORDER_STATUS_UPDATED');
                $result = $order_controller->updated($data);

                if (!$result['success']) {

                    $log->error("Не удалось обновить статус заказа", [
                        'result' => $result
                    ]);

                    return response()->json([
                        'error' => [
                            'message' => $result['error'],
                            'type' => data_get($result, 'code') === 1 ? 'DUPLICATED_EVENT' : 'UNKNOWN'
                        ]
                    ], 400);
                }

                $log->debug("Заказ обновлен", ['result' => $result]);

                break;
            case 'PING':
                $log->info('notificationType PING');
                break;
            default:
                return response()->json([
                    'error' => [
                        'message' => 'Неизвестное уведомление',
                        'type' => 'UNKNOWN'
                    ]
                ], 400);
        }

        return response()->json([
            'name' => 'marketplace.1gros.ru',
            'time' => now('UTC')->toIso8601String(),
            'version' => '0.0.1'
        ]);
    }
}
