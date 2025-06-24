<?php

namespace App\Http\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class BinanceService
{
    private string $base_url = "https://api.binance.com/";
    private PendingRequest $client;

    public function __construct()
    {
        $this->client = Http::baseUrl($this->base_url);
    }

    /**
     * @param string $symbol
     * @return float
     * @throws ConnectionException
     */
    public function tickerPrice(string $symbol): float
    {
        $response = $this->client->get("api/v3/ticker/price?symbol=$symbol");

        if ($response->failed()) {
            throw new ConnectionException($response->body(), $response->status());
        }

        return round($response->json('price'), 2);
    }
}
