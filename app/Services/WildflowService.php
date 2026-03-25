<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class WildflowService
{
    private string $base_url = 'https://api.wildflow.dev/api/v1/partners/';

    private PendingRequest $client;

    public function __construct()
    {
        $this->client = Http::withHeaders([
            'Accept' => 'application/json',
            'X-Auth-Token' => config('app.wildflow_token'),
        ])->timeout(60)
            ->withoutVerifying()
            ->baseUrl($this->base_url);
    }

    public function getExchangeRates(): array
    {
        $response = $this->client->get('exchange-rates');

        if ($response->failed()) {
            throw new \RuntimeException($response->body());
        }

        return $response->json('data.results');
    }
}
