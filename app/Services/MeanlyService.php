<?php

namespace App\Services;

use App\Models\Settings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class MeanlyService
{
    private string $base_url = "https://meanly.ru/api/v1/";
    private PendingRequest $client;

    public function __construct(?string $token = null)
    {
        $token = $token ?: Settings::get('MEANLY_TOKEN');

        $this->client = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->baseUrl($this->base_url);
    }

    public function sendOffers(array $offers)
    {
        $response = $this->client->post("import/offers", $offers);

        if ($response->failed()) {
            throw new ConnectionException($response->body(), $response->status());
        }

        return $response->json();
    }

    public function getOffers()
    {
        $allOffers = [];
        $page = 1;

        do {
            $response = $this->client->get("catalog/offers", ['page' => $page]);

            if ($response->failed()) {
                throw new ConnectionException($response->body(), $response->status());
            }

            $offers = $response->json('data');
            if (empty($offers)) break;

            $allOffers = array_merge($allOffers, $offers);
            
            $lastPage = $response->json('meta.last_page') ?? 1;
            $page++;
        } while ($page <= $lastPage);

        return $allOffers;
    }
}
