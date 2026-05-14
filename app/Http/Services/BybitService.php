<?php

namespace App\Http\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class BybitService
{
    private string $base_url = "https://api.bybit.com/";
    private PendingRequest $client;

    public function __construct()
    {
        $this->client = Http::baseUrl($this->base_url)
            ->timeout(30)
            ->withoutVerifying();
    }

    /**
     * Get P2P Rate from Bybit
     * @param string $fiat
     * @param string $asset
     * @return float
     */
    public function getP2PRate(string $fiat, string $asset = 'USDT'): float
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->withoutVerifying()
                ->post("https://api2.bybit.com/fiat/otc/item/list", [
                "asset" => $asset,
                "fiat" => $fiat,
                "page" => "1",
                "rows" => "1",
                "status" => "1"
            ]);

            if ($response->failed()) {
                return 0.0;
            }

            $price = (float)data_get($response->json(), 'result.items.0.price', 0);
            return round($price, 2);
        } catch (\Exception $e) {
            return 0.0;
        }
    }

    /**
     * Get ticker price from Bybit V5 API
     * @param string $symbol
     * @return float
     */
    public function tickerPrice(string $symbol): float
    {
        try {
            $response = $this->client->get("v5/market/tickers", [
                'category' => 'spot',
                'symbol' => $symbol
            ]);

            if ($response->failed()) {
                return 0.0;
            }

            $list = $response->json('result.list');
            if (empty($list)) {
                return 0.0;
            }

            return (float)($list[0]['lastPrice'] ?? 0.0);
        } catch (\Exception $e) {
            return 0.0;
        }
    }
}
