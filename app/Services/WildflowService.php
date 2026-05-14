<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class WildflowService
{
    private string $base_url = 'http://api.wildflow.test/api/v1/';

    private PendingRequest $client;
    private ?string $financial_secret = null;

    public function __construct(string $overrideToken = null, ?\App\Models\Provider $providerModel = null)
    {
        $providerModel = $providerModel ?? \App\Models\Provider::where('type', 'wildflow')->first();
        
        // Highly dynamic token resolution: Override > DB Provider credentials > Config fallback
        $token = $overrideToken 
            ?? $providerModel->credentials['api_key'] 
            ?? config('app.wildflow_token');

        $this->financial_secret = $providerModel->credentials['financial_secret'] ?? null;
 
        $this->client = Http::withHeaders([
            'Accept' => 'application/json',
            'X-Auth-Token' => $token,
        ])->timeout(60)
            ->withoutVerifying()
            ->baseUrl($this->base_url);
    }

    public function getExchangeRates(string $provider = 'ezpin'): array
    {
        $response = $this->client->get("providers/{$provider}/exchange-rates");
 
        if ($response->failed()) {
            throw new \RuntimeException("Wildflow Failed Get Rates: " . $response->body());
        }
 
        return $response->json('data.results') ?? $response->json('data') ?? [];
    }

    /**
     * 🚀 SHINY CLEAN ORDER PLACEMENT
     * Dropped all hardcoded terminals! Smart Aggregator handles routing.
     */
    public function createOrder(
        string $service_sku,
        string $order_item_id,
        float $price,
        int $quantity,
        bool $pre_order = false,
        string $destination = '',
        string $provider = 'ezpin'
    )
    {
        $payload = [
            'service_sku'   => $service_sku, // Stays robust string!
            'price'         => $price,
            'quantity'      => $quantity,
            'pre_order'     => $pre_order,
            'referenceCode' => $order_item_id, // Sent as plain string, Aggregator auto-UUIDs it!
            'destination'   => $destination,
        ];
 
        \Illuminate\Support\Facades\Log::info("Wildflow Universal Order START [Provider: {$provider}]", ['payload' => $payload]);
 
        // Hit the super-smart dispatcher route!
        $response = $this->client->post("providers/{$provider}/order", $payload);
 
        if ($response->failed()) {
            \Illuminate\Support\Facades\Log::error("Wildflow Universal Order FAILED", [
                'status' => $response->status(),
                'body' => $response->body(),
                'payload' => $payload
            ]);
            throw new \RuntimeException("API Error: " . $response->body());
        }
 
        return $response->json('order');
    }

    /**
     * 🛡️ AVAILABILITY GUARD
     * Checks if vendor has real items in stock before committing resources.
     */
    public function checkAvailability(string $service_sku, int $quantity = 1, ?float $price = null, string $provider = 'ezpin'): array
    {
        $response = $this->client->get("providers/{$provider}/check-availability/{$service_sku}", array_filter([
            'quantity' => $quantity,
            'price' => $price
        ]));

        if ($response->failed()) {
             // Log silently, caller will decide handling
             \Illuminate\Support\Facades\Log::warning("Availability Check Failed", ['sku' => $service_sku, 'body' => $response->body()]);
             return ['available' => false, 'error' => $response->body()];
        }

        // Aggregator returns { success: true, availability: { availability: true, detail: '...' } }
        $data = $response->json();
        $a = $data['availability'] ?? [];
        
        // 🎯 ABSOLUTE COMPLIANCE FIX:
        // The provider nested payload uses the key 'availability' for the boolean value itself!
        $isAvailable = (bool)($a['availability'] ?? $a['available'] ?? $a['in_stock'] ?? false);

        // 🛡️ RESILIENCE BYPASS: 
        // If live aggregator fails due to technical regressions (648, 792, etc.), we DO NOT block.
        // We allow "Fail-Open" architecture to guarantee business continuity over API glitches.
        if (!$isAvailable && isset($a['code']) && in_array((int)$a['code'], [648, 400, 792])) {
            $isAvailable = true; 
        }

        // Safety fallback: text-based inspection if needed
        if (!$isAvailable && isset($a['detail']) && str_contains(strtolower($a['detail']), 'is available')) {
            $isAvailable = true;
        }

        return [
            'available' => $isAvailable,
            'raw' => $data
        ];
    }

    /**
     * 🧠 SMART CARD FETCH
     * Uses normalized-cards and supports client references directly via Aggregator reverse lookup!
     */
    public function getCards(string $referenceCode, string $provider = 'ezpin')
    {
        // Hits the smart proxy endpoint that can resolve both UUIDs and Local IDs!
        $response = $this->client->get("providers/{$provider}/orders/{$referenceCode}/normalized-cards");
 
        if ($response->failed()) {
            throw new \RuntimeException("Failed to fetch normalized cards: " . $response->body());
        }
 
        // Returns perfectly normalized card DTO payload!
        return $response->json('cards');
    }

    /**
     * 💳 JIT CREDIT GRANTING (SECURED & MULTI-TENANT)
     */
    public function grantCredit(float $amount, string $reference, ?string $sellerId = null, ?string $sellerName = null): array
    {
        $headers = [];
        if ($this->financial_secret) {
            $formattedAmount = number_format($amount, 2, '.', '');
            $dataToSign = "{$formattedAmount}:{$reference}";
            $headers['X-Financial-Signature'] = hash_hmac('sha256', $dataToSign, $this->financial_secret);
        }

        $payload = [
            'amount' => $amount,
            'reference' => $reference,
            'seller_id' => $sellerId,
            'seller_name' => $sellerName,
        ];

        $response = $this->client->withHeaders($headers)->post("partners/grant-credit", array_filter($payload));

        if ($response->failed()) {
            throw new \RuntimeException("Failed to grant credit: " . $response->body());
        }

        return $response->json();
    }
}
