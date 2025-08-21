<?php

namespace App\Http\Services;

use App\Models\Settings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class YmService
{
    private string $base_url = "https://api.partner.market.yandex.ru/";
    private PendingRequest $client;

    private mixed $ym_business_id;

    private mixed $campaign_id;

    public function __construct()
    {
        $this->ym_business_id = (int)Settings::get('YM_BUSINESS_ID', config('services.ym.business_id', 143486522));
        $this->campaign_id = config('services.ym.campaign_id', 143486522);

        $this->client = Http::withHeaders([
            'Api-Key' => Settings::get('YM_API_KEY', config('services.ym.api_key', 'ACMA:3mHDTfT7sVhGMb6xtQXGOoq5RzpHvLCjTq12Jd1M:bf243683')),
        ])
            ->baseUrl($this->base_url)
            ->withOptions([
                'timeout' => 60,
                'connect_timeout' => 40,
                'verify' => false,
            ]);
    }

    public function sendMessage(int $chatId, string $message)
    {
        $business_id = $this->ym_business_id;

        $response = $this->client->post("businesses/$business_id/chats/message?chatId=$chatId", [
            'message' => $message,
        ]);

        if ($response->failed()) {
            throw new ConnectionException($response->body(), $response->status());
        }

        return $response->json();
    }

    public function newChat(int $orderId)
    {
        $business_id = $this->ym_business_id;

        $response = $this->client->post("businesses/$business_id/chats/new", [
            'context' => [
                'type' => 'ORDER',
                'id' => $orderId,
            ]
        ]);

        if ($response->failed()) {
            throw new ConnectionException($response->body(), $response->status());
        }

        return $response->json('result.chatId');
    }

    public function getNewOrders()
    {
        $campaign_id = $this->campaign_id;

        $current_date = now()->format('Y-m-d');

        $response = $this->client->get("campaigns/$campaign_id/orders?status=PROCESSING&substatus=STARTED&fromDate=$current_date&toDate=$current_date");

        if ($response->failed()) {
            throw new ConnectionException($response->body(), $response->status());
        }

        return $response->json('orders');
    }

    public function getOrder(int $campaignId, int $orderId)
    {
        $response = $this->client->get("campaigns/$campaignId/orders/$orderId");

        if ($response->failed()) {
            throw new ConnectionException($response->body(), $response->status());
        }

        return $response->json('order');
    }

    public function provideOrderDigitalCodes(array $keys, int $campaignId, int $orderId)
    {
        $response = $this->client->post("campaigns/$campaignId/orders/$orderId/deliverDigitalGoods", [
            'items' => $keys,
        ]);

        if ($response->failed()) {
            throw new ConnectionException($response->body(), $response->status());
        }

        return $response->json();
    }

    public function offerShow(array $offerIds)
    {
        if (count($offerIds) > 500) {
            throw new \Exception('Too many offerIds');
        }

        $campaign_id = $this->campaign_id;

        $response = $this->client->post("campaigns/$campaign_id/hidden-offers/delete", [
            'hiddenOffers' => $offerIds,
        ]);

        if ($response->failed()) {
            throw new ConnectionException($response->body(), $response->status());
        }

        return $response->json();
    }

    public function getOrderBuyerInfo(int $campaignId, int $orderId)
    {
        $response = $this->client->get("campaigns/$campaignId/orders/$orderId/buyer");

        if ($response->failed()) {
            throw new ConnectionException($response->body(), $response->status());
        }

        return $response->json('result');
    }

    public function offerPriceUpdate(array $offerPrices)
    {
        if (count($offerPrices) > 500) {
            throw new \Exception('Too many offerPrices');
        }

        $business_id = $this->ym_business_id;

        $response = $this->client->post("businesses/$business_id/offer-prices/updates", [
            'offers' => $offerPrices,
        ]);

        if ($response->failed()) {
            throw new ConnectionException($response->body(), $response->status());
        }

        return $response->json();
    }

    public function offerStocks(array $offerStocks)
    {
        if (count($offerStocks) > 1000) {
            throw new \Exception('Too many offerStocks');
        }

        $campaign_id = $this->campaign_id;

        $response = $this->client->put("campaigns/$campaign_id/offers/stocks", [
            'skus' => $offerStocks,
        ]);

        if ($response->failed()) {
            throw new ConnectionException($response->body(), $response->status());
        }

        return $response->json();
    }

    public function offerMappingsUpdate(array $offerMappings)
    {
        $business_id = $this->ym_business_id;

        $response = $this->client->post("businesses/$business_id/offer-mappings/update", [
            'offerMappings' => $offerMappings,
        ]);

        if ($response->failed()) {
            throw new ConnectionException($response->body(), $response->status());
        }

        return $response->json();
    }

    public function quarantineRemove(array $offerIds)
    {
        if (count($offerIds) > 200) {
            throw new \Exception('Too many offerIds');
        }

        $campaign_id = $this->campaign_id;

        $response = $this->client->post("campaigns/$campaign_id/price-quarantine/confirm", [
            'offerIds' => $offerIds,
        ]);

        if ($response->failed()) {
            throw new ConnectionException($response->body(), $response->status());
        }

        return $response->json();
    }
}
