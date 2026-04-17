<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class WildflowService
{
    private string $base_url = 'https://api.wildflow.dev/api/v1/';

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
        $response = $this->client->get('partners/exchange-rates');

        if ($response->failed()) {
            throw new \RuntimeException($response->body());
        }

        return $response->json('data.results');
    }

    public function createOrder(
        string $service_sku,
        string $order_item_id,
        int $price,
        int $qte,
        bool $pre_order = false,
        string $email = 'sataniyazow@gmail.com',
        string $terminal_pin = '1029',
        int $terminal_id = 9937
    )
    {
        $response = $this->client->post('codes/create-order', [
            'sku' => $service_sku,
            'price' => $price,
            'quantity' => $qte,
            'preOrder' => $pre_order,
            'referenceCode' => $order_item_id,
            'deliveryType' => 1,
            'destination' => $email,
            'terminal_pin' => $terminal_pin,
            'terminal_id' => $terminal_id
        ]);

        if ($response->failed()) {
            throw new \RuntimeException($response->body());
        }

        return $response->json('order');
    }

    public function getCards(string $referenceCode)
    {
        $response = $this->client->get('codes/orders/' . $referenceCode . '/cards');

        if ($response->failed()) {
            throw new \RuntimeException($response->body());
        }

        return $response->json('cards.results');
    }
}
