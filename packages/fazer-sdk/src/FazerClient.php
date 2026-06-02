<?php

namespace FazerSdk;

use FazerSdk\Endpoints\Account;
use FazerSdk\Endpoints\Catalog;
use FazerSdk\Endpoints\Orders;
use FazerSdk\Endpoints\Utilities;
use FazerSdk\Endpoints\Webhooks;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class FazerClient
{
    protected string $apiKey;

    protected string $baseUrl = 'https://api.fazercards.com/api/v1';

    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = $apiKey ?? config('services.fazer.api_key');
    }

    public function catalog(): Catalog
    {
        return new Catalog($this);
    }

    public function orders(): Orders
    {
        return new Orders($this);
    }

    public function account(): Account
    {
        return new Account($this);
    }

    public function webhooks(): Webhooks
    {
        return new Webhooks($this);
    }

    public function utilities(): Utilities
    {
        return new Utilities($this);
    }

    public function client(): PendingRequest
    {
        return Http::withHeaders([
            'X-API-Key' => $this->apiKey,
            'Accept' => 'application/json',
        ])
            ->baseUrl($this->baseUrl)
            ->timeout(60);
    }
}
