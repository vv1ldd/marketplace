<?php

namespace App\Http\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class YmService
{
    private string $base_url = "https://api.partner.market.yandex.ru/";
    private PendingRequest $client;

    public function __construct()
    {
        $this->client = Http::withHeaders([
            'Api-Key' => config('services.ym.api_key', 'ACMA:3mHDTfT7sVhGMb6xtQXGOoq5RzpHvLCjTq12Jd1M:bf243683'),
        ])
            ->withOptions([
                'timeout' => 60,
                'connect_timeout' => 40,
            ]);
    }

    public function offerStocks(array $offerStocks)
    {
        $campaign_id = config('services.ym.campaign_id', 143486522);

        $response = $this->client->baseUrl($this->base_url)->put("campaigns/$campaign_id/offers/stocks", [
            'skus' => $offerStocks,
        ]);

        if ($response->failed()) {
            throw new ConnectionException($response->body(), $response->status());
        }

        return $response->json();
    }

    public function offerMappingsUpdate(array $offerMappings)
    {
        $business_id = config('services.ym.business_id', 198666367);

        $response = $this->client->baseUrl($this->base_url)->post("businesses/$business_id/offer-mappings/update", [
            'offerMappings' => $offerMappings,
        ]);

        if ($response->failed()) {
            throw new ConnectionException($response->body(), $response->status());
        }

        return $response->json();
    }

    /**
     * @param int|string $offer_id
     * @param string|int $business_id
     * @return string|null
     * @throws ConnectionException
     */
    public function getImage(int|string $offer_id, string|int $business_id): string|null
    {
        $response = $this->client->baseUrl($this->base_url)->post("businesses/$business_id/offer-mappings", [
            'offerIds' => [$offer_id],
            'limit' => 1,
        ]);

        if ($response->failed()) {
            throw new ConnectionException($response->body(), $response->status());
        }

        return $response->json('result.offerMappings.0.offer.pictures.0');
    }

    /**
     * @param int $seller_id
     * @return mixed
     */
    public function getCampaigns(int $seller_id): mixed
    {
        return Cache::remember("ym:campaigns:$seller_id", 86400, function () {

            $response = $this->client->baseUrl($this->base_url)->get('campaigns');

            if ($response->failed()) {
                throw new ConnectionException($response->body(), $response->status());
            }

            return $response->json('campaigns');
        });
    }

    /**
     * @param int $campaign_id
     * @param string $action
     * @return array|mixed
     * @throws ConnectionException
     */
    public function getOrders(int $campaign_id, string $action = 'new'): mixed
    {
        $uri = "campaigns/{$campaign_id}/orders?";
        $current_date = now()->format('Y-m-d');

        $uri .= match ($action) {
            'cancelled' => "status=CANCELLED&substatus=USER_UNREACHABLE,USER_CHANGED_MIND,USER_REFUSED_DELIVERY,USER_REFUSED_PRODUCT,SHOP_FAILED,USER_REFUSED_QUALITY,REPLACING_ORDER,PROCESSING_EXPIRED,DELIVERY_SERVICE_UNDELIVERED,CANCELLED_COURIER_NOT_FOUND,USER_WANTS_TO_CHANGE_DELIVERY_DATE,RESERVATION_FAILED",
            default => "status=PROCESSING&substatus=STARTED"
        };

        $uri .= "&fromDate=$current_date&toDate=$current_date";

        $response = $this->client->baseUrl($this->base_url)->get($uri);

        if ($response->failed()) {
            throw new ConnectionException($response->body(), $response->status());
        }

        return $response->json('orders');
    }

    public function getReturns(int $campaign_id)
    {
        $uri = "campaigns/$campaign_id/returns?";

        $current_date = now()->format('Y-m-d');

        $uri .= "fromDate=$current_date&toDate=$current_date&limit=1";

        $response = $this->client->baseUrl($this->base_url)->get($uri);

        if ($response->failed()) {
            throw new ConnectionException($response->body(), $response->status());
        }

        return $response->json('result.returns');
    }
}
