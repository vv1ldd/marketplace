<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class DaDataService
{
    protected string $token;
    protected string $baseUrl = 'https://suggestions.dadata.ru/suggestions/api/4_1/rs';

    public function __construct()
    {
        $this->token = config('services.dadata.token', env('DADATA_TOKEN', ''));
    }

    public function findByInn(string $inn): ?array
    {
        if (empty($this->token)) {
            return null;
        }

        $response = Http::withHeaders([
            'Authorization' => 'Token ' . $this->token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->post($this->baseUrl . '/findById/party', [
            'query' => $inn,
        ]);

        if ($response->failed()) {
            return null;
        }

        $suggestions = $response->json('suggestions');

        if (empty($suggestions)) {
            return null;
        }

        return $suggestions[0]['data'] ?? null;
    }
}
