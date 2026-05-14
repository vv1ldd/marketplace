<?php

namespace App\Http\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SovereignExchangeService
{
    protected string $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36';

    public function getBestP2PRate(string $fiat, string $asset = 'USDT'): array
    {
        $startTime = microtime(true);

        // 0. Hard-coded truth: 1 USD is 1 USDT for cross-rate purposes
        if ($fiat === 'USD') {
            return [
                'rate' => 1.0,
                'source' => 'INTERNAL',
                'spot_rate' => 1.0,
                'p2p_rate' => 1.0,
                'capacity_usd' => 1000000, // Unlimited internal
                'max_fill_usd' => 1000000,
                'latency_ms' => 0,
                'all' => ['internal' => 1.0]
            ];
        }

        // 1. Collect Spot Rate if available
        $spotRate = 0;
        $spotSource = null;

        switch ($fiat) {
            case 'KRW': $spotRate = $this->getUpbitSpotRate(); $spotSource = 'upbit'; break;
            case 'JPY': $spotRate = $this->getBitflyerSpotRate(); $spotSource = 'bitflyer'; break;
            case 'THB': $spotRate = $this->getBitkubSpotRate(); $spotSource = 'bitkub'; break;
            case 'TWD': $spotRate = $this->getBitoProSpotRate(); $spotSource = 'bitopro'; break;
            case 'IDR': $spotRate = $this->getIndodaxSpotRate(); $spotSource = 'indodax'; break;
            case 'ZAR': $spotRate = $this->getValrSpotRate(); $spotSource = 'valr'; break;
            case 'SGD': $spotRate = $this->getIndependentReserveSpotRate(); $spotSource = 'indereserve'; break;
            case 'MXN':
            case 'ARS':
            case 'COP':
            case 'BRL': $spotRate = $this->getBitsoSpotRate($fiat); $spotSource = 'bitso'; break;
        }

        // Global Fallback to Binance Spot for ANY fiat if no specific exchange found it yet
        if ($spotRate <= 0) {
            $binanceSpot = $this->getBinanceSpotRate($fiat);
            if ($binanceSpot > 0) {
                $spotRate = $binanceSpot;
                $spotSource = 'binance_spot';
            }
        }

        // If Spot is highly reliable for these major fiats, we can skip P2P altogether to avoid dirty data
        if (in_array($fiat, ['EUR', 'GBP']) && $spotRate > 0) {
            return [
                'rate' => $spotRate,
                'source' => $spotSource,
                'spot_rate' => $spotRate,
                'p2p_rate' => 0,
                'capacity_usd' => 500000, // High liquidity spot estimation
                'max_fill_usd' => 50000,
                'latency_ms' => (int)((microtime(true) - $startTime) * 1000),
                'all' => [$spotSource => $spotRate]
            ];
        }

        // 2. Always attempt P2P discovery for telemetry
        $timeout = ($fiat === 'RUB') ? 7 : 3;
        $minFiatFloor = $this->estimateFiatAmount($fiat, 100);

        $responses = Http::pool(function (\Illuminate\Http\Client\Pool $pool) use ($fiat, $asset, $timeout) {
            return [
                $pool->as('bybit')
                    ->timeout($timeout)
                    ->withHeaders(['User-Agent' => $this->userAgent, 'Content-Type' => 'application/json'])
                    ->post('https://api2.bybit.com/fiat/otc/item/online', [
                        'tokenId' => $asset, 'currencyId' => $fiat,
                        'payment' => [], 'side' => '1', 'size' => '20', 'page' => '1', 'amount' => '',
                    ]),

                $pool->as('binance')
                    ->timeout($timeout)
                    ->withHeaders(['User-Agent' => $this->userAgent, 'Content-Type' => 'application/json'])
                    ->post('https://p2p.binance.com/bapi/c2c/v2/friendly/c2c/adv/search', [
                        'asset' => $asset, 'fiat' => $fiat, 'merchantCheck' => false,
                        'page' => 1, 'payTypes' => [], 'publisherType' => null,
                        'rows' => 20, 'tradeType' => 'BUY', 'transAmount' => '',
                    ]),
            ];
        });

        // Parse P2P Results
        $p2pRates = [];
        $p2pCounts = [];
        $capacityUsd = 0;
        $maxFillUsd = 0;

        if (!($responses['bybit'] instanceof \Exception) && ($responses['bybit']->json('ret_code') ?? -1) === 0) {
            $items = $responses['bybit']->json('result.items') ?? [];
            $validAds = array_filter($items, fn($i) => (float)($i['minAmount'] ?? 0) >= $minFiatFloor);
            $p2pCounts['bybit'] = count($validAds);
            if (!empty($validAds)) {
                $p2pRates['bybit'] = (float)reset($validAds)['price'];
                // Estimate capacity
                foreach ($validAds as $ad) {
                    $rawVol = (float)($ad['lastAmount'] ?? $ad['quantity'] ?? 0);
                    $adVol = $rawVol * ((float)$ad['price'] > 0 ? (1 / (float)$ad['price']) : 0);
                    if ($adVol <= 0) $adVol = (float)($ad['maxAmount'] ?? 0) / ((float)$ad['price'] ?: 1);
                    
                    $capacityUsd += $adVol;
                    $maxFillUsd = max($maxFillUsd, (float)($ad['maxAmount'] ?? 0) / ((float)$ad['price'] ?: 1));
                }
            }
        }

        if (!($responses['binance'] instanceof \Exception)) {
            $items = $responses['binance']->json('data') ?? [];
            $validAds = array_filter($items, fn($i) => (float)($i['adv']['minSingleTransAmount'] ?? 0) >= $minFiatFloor);
            $p2pCounts['binance'] = count($validAds);
            if (!empty($validAds)) {
                $p2pRates['binance'] = (float)reset($validAds)['adv']['price'];
                // Estimate capacity
                foreach ($validAds as $ad) {
                    $adVol = (float)$ad['adv']['surplusAmount']; // already in asset (USDT usually)
                    $capacityUsd += $adVol;
                    $maxFillUsd = max($maxFillUsd, (float)$ad['adv']['maxSingleTransAmount'] / ((float)$ad['adv']['price'] ?: 1));
                }
            }
        }

        $validP2p = array_filter($p2pRates, fn($r) => $r > 0);
        asort($validP2p);
        $bestP2p = empty($validP2p) ? 0 : reset($validP2p);
        $p2pSource = empty($validP2p) ? null : key($validP2p);
        $totalAds = array_sum($p2pCounts);

        $latencyMs = (int)((microtime(true) - $startTime) * 1000);

        // Final Logic
        $finalRate = ($spotRate > 0) ? $spotRate : $bestP2p;
        $finalSource = ($spotRate > 0) ? $spotSource : $p2pSource;

        return [
            'rate'      => $finalRate,
            'source'    => $finalSource,
            'spot_rate' => $spotRate,
            'p2p_rate'  => $bestP2p,
            'p2p_ads'   => $totalAds,
            'capacity_usd' => round($capacityUsd, 2),
            'max_fill_usd' => round($maxFillUsd, 2),
            'latency_ms'   => $latencyMs,
            'timestamp'    => time(),
            'all'       => $p2pRates
        ];
    }


    /**
     * Binance P2P with $100 Liquidity Floor
     */
    public function getBinanceP2PRate(string $fiat, string $asset = 'USDT', int $timeout = 3): float
    {
        try {
            $response = Http::timeout($timeout)
                ->withHeaders([
                    'User-Agent'   => $this->userAgent,
                    'Accept'       => '*/*',
                    'Content-Type' => 'application/json'
                ])
                ->post("https://p2p.binance.com/bapi/c2c/v2/friendly/c2c/adv/search", [
                    "asset"         => $asset,
                    "fiat"          => $fiat,
                    "merchantCheck" => false,
                    "page"          => 1,
                    "payTypes"      => [],
                    "publisherType" => null,
                    "rows"          => 20,
                    "tradeType"     => "BUY",
                    "transAmount"   => ""
                ]);

            $items = $response->json('data') ?? [];
            $minFiatFloor = $this->estimateFiatAmount($fiat, 100);

            foreach ($items as $item) {
                $minAmount = (float)($item['adv']['minSingleTransAmount'] ?? 0);
                if ($minAmount >= $minFiatFloor) {
                    return (float)($item['adv']['price'] ?? 0);
                }
            }

            return (float)data_get($items, '0.adv.price', 0);
        } catch (\Exception $e) { return 0; }
    }

    public function getBybitP2PRate(string $fiat, string $asset = 'USDT', int $timeout = 7): float
    {
        try {
            // Request more items so we can filter by liquidity floor
            $response = Http::timeout($timeout)
                ->withHeaders([
                    'User-Agent'   => $this->userAgent,
                    'Content-Type' => 'application/json',
                ])
                ->post("https://api2.bybit.com/fiat/otc/item/online", [
                    "tokenId"    => $asset,
                    "currencyId" => $fiat,
                    "payment"    => [],
                    "side"       => "1",
                    "size"       => "20",
                    "page"       => "1",
                    "amount"     => "",
                ]);

            if (($response->json('ret_code') ?? -1) !== 0) return 0;

            $items = $response->json('result.items') ?? [];

            // Liquidity floor: only accept offers with minAmount >= ~$100 equivalent in local fiat
            $minFiatFloor = $this->estimateFiatAmount($fiat, 100);

            foreach ($items as $item) {
                $minAmount = (float)($item['minAmount'] ?? 0);
                if ($minAmount >= $minFiatFloor) {
                    return (float)($item['price'] ?? 0);
                }
            }

            // Fallback: best available price if no liquid offer found
            return (float)data_get($items, '0.price', 0);
        } catch (\Exception $e) { return 0; }
    }

    protected function estimateFiatAmount(string $fiat, float $usdAmount): int
    {
        if ($fiat === 'RUB') return $usdAmount * 100; // Special case for RUB liquidity depth

        $currency = \App\Models\Currency::where('code', $fiat)->first();
        if (!$currency || $currency->official_rate <= 0) return (int)$usdAmount;

        // Formula: (RUB per USD) / (RUB per FIAT) = FIAT per USD
        $usdRub = 74.30; 
        $fiatPerUsd = $usdRub / $currency->official_rate;
        
        return (int)($fiatPerUsd * $usdAmount);
    }

    public function getOkxP2PRate(string $fiat, string $asset = 'USDT', int $timeout = 3): float
    {
        try {
            $response = Http::timeout($timeout)
                ->withHeaders(['User-Agent' => $this->userAgent])
                ->get("https://www.okx.com/v3/c2c/tradingOrders/books", [
                    'quoteCurrency' => $fiat,
                    'baseCurrency' => strtolower($asset),
                    'side' => 'sell',
                    'paymentMethod' => 'all',
                    'userType' => 'all'
                ]);

            return (float)data_get($response->json(), 'data.sell.0.price', 0);
        } catch (\Exception $e) { return 0; }
    }

    public function getBitgetP2PRate(string $fiat, string $asset = 'USDT', int $timeout = 3): float
    {
        try {
            $response = Http::timeout($timeout)
                ->withHeaders(['User-Agent' => $this->userAgent])
                ->post("https://www.bitget.com/api/v1/c2c/order/getAdvList", [
                    'fiatName'     => $fiat,
                    'coinName'     => $asset,
                    'side'         => 'buy',
                    'pageSize'     => 20,
                    'languageType' => 0
                ]);

            $items = $response->json('data') ?? [];
            $minFiatFloor = $this->estimateFiatAmount($fiat, 100);

            foreach ($items as $item) {
                $minAmount = (float)($item['minOrderAmount'] ?? 0);
                if ($minAmount >= $minFiatFloor) {
                    return (float)($item['price'] ?? 0);
                }
            }

            return (float)data_get($items, '0.price', 0);
        } catch (\Exception $e) { return 0; }
    }

    public function getUpbitSpotRate(): float
    {
        try {
            $response = Http::timeout(5)->get("https://api.upbit.com/v1/ticker?markets=KRW-USDT");
            return (float)data_get($response->json(), '0.trade_price', 0);
        } catch (\Exception $e) { return 0; }
    }

    public function getBitflyerSpotRate(): float
    {
        try {
            $response = Http::timeout(5)->get("https://api.bitflyer.com/v1/ticker?product_code=USDT_JPY");
            return (float)data_get($response->json(), 'ltp', 0);
        } catch (\Exception $e) { return 0; }
    }

    public function getBitkubSpotRate(): float
    {
        try {
            $response = Http::timeout(5)->get("https://api.bitkub.com/api/market/ticker?sym=USDT_THB");
            return (float)data_get($response->json(), 'USDT_THB.last', 0);
        } catch (\Exception $e) { return 0; }
    }

    public function getBitoProSpotRate(): float
    {
        try {
            $response = Http::timeout(5)->get("https://api.bitopro.com/v3/tickers/usdt_twd");
            return (float)data_get($response->json(), 'data.lastPrice', 0);
        } catch (\Exception $e) { return 0; }
    }

    public function getIndodaxSpotRate(): float
    {
        try {
            $response = Http::timeout(5)->get("https://indodax.com/api/ticker/usdtidr");
            return (float)data_get($response->json(), 'ticker.last', 0);
        } catch (\Exception $e) { return 0; }
    }

    public function getValrSpotRate(): float
    {
        try {
            $response = Http::timeout(5)->get("https://api.valr.com/v1/public/USDTZAR/ticker");
            return (float)data_get($response->json(), 'lastTradedPrice', 0);
        } catch (\Exception $e) { return 0; }
    }

    public function getIndependentReserveSpotRate(): float
    {
        try {
            $response = Http::timeout(5)->get("https://api.independentreserve.com/Public/GetMarketSummary", [
                'primaryCurrencyCode' => 'usdt',
                'secondaryCurrencyCode' => 'sgd'
            ]);
            return (float)data_get($response->json(), 'LastPrice', 0);
        } catch (\Exception $e) { return 0; }
    }

    public function getLunoSpotRate(string $fiat): float
    {
        try {
            $pair = "BTC{$fiat}";
            $response = Http::timeout(5)->get("https://api.luno.com/api/1/ticker?pair={$pair}");
            $btcFiat = (float)data_get($response->json(), 'last_trade', 0);
            
            if ($btcFiat > 0) {
                $btcUsdt = $this->getBinanceBtcUsdt();
                if ($btcUsdt > 0) return $btcFiat / $btcUsdt;
            }
        } catch (\Exception $e) { }
        return 0;
    }

    public function getKucoinP2PRate(string $fiat, string $asset = 'USDT', int $timeout = 3): float
    {
        try {
            $response = Http::timeout($timeout)
                ->get("https://www.kucoin.com/_api/otc/ad/list", [
                    'currency' => $fiat,
                    'side' => 'BUY',
                    'legal' => $fiat,
                    'coin' => $asset,
                    'status' => 'PAY'
                ]);

            return (float)data_get($response->json(), 'items.0.price', 0);
        } catch (\Exception $e) { return 0; }
    }

    public function getBitsoSpotRate(string $fiat): float
    {
        try {
            $pair = strtolower($fiat) . "_usdt";
            $response = Http::timeout(5)->get("https://api.bitso.com/v3/ticker/?book={$pair}");
            return (float)data_get($response->json(), 'payload.last', 0);
        } catch (\Exception $e) { return 0; }
    }

    protected function getBinanceBtcUsdt(): float
    {
        try {
            $response = Http::get("https://api.binance.com/api/v3/ticker/price?symbol=BTCUSDT");
            return (float)data_get($response->json(), 'price', 0);
        } catch (\Exception $e) { return 0; }
    }

    protected function getBinanceSpotRate(string $fiat): float
    {
        try {
            $symbol = "USDT{$fiat}";
            $response = Http::timeout(3)->get("https://api.binance.com/api/v3/ticker/price?symbol={$symbol}");
            if ($response->successful()) {
                $price = (float)$response->json('price');
                if ($price > 0) return $price;
            }

            $symbol = "{$fiat}USDT";
            $response = Http::timeout(3)->get("https://api.binance.com/api/v3/ticker/price?symbol={$symbol}");
            if ($response->successful()) {
                $price = (float)$response->json('price');
                if ($price > 0) return 1.0 / $price; // Invert to get USDT/FIAT rate
            }
        } catch (\Exception $e) {
            \Log::warning("Binance Spot failed for {$fiat}: " . $e->getMessage());
        }
        return 0;
    }
}
