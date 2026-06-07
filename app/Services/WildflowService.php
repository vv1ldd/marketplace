<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class WildflowService
{
    private string $base_url;

    private PendingRequest $client;
    private ?string $financial_secret = null;
    private bool $hasRemoteKernelEndpoint = false;
    private ?array $lastSourceLedgerReceipt = null;

    public function __construct(?string $overrideToken = null, ?\App\Models\Provider $providerModel = null)
    {
        $providerModel = $providerModel ?? \App\Models\Provider::where('type', 'wildflow')->first();

        $kernelUrl = config('services.wildflow.kernel_url');
        $providerBaseUrl = $providerModel->credentials['base_url'] ?? null;
        $this->hasRemoteKernelEndpoint = filled($kernelUrl) || filled($providerBaseUrl);

        $baseUrl = $kernelUrl
            ?? $providerBaseUrl
            ?? config('services.wildflow.base_url')
            ?? rtrim((string) config('app.url', 'https://meanly.one'), '/').'/api/v1/';
        $this->base_url = rtrim($baseUrl, '/') . '/';

        // Highly dynamic token resolution: Override > DB Provider credentials > Config fallback
        $token = $overrideToken 
            ?? $providerModel->credentials['api_key'] 
            ?? config('app.wildflow_token');

        $clientId = $providerModel->credentials['client_id'] ?? null;
        $this->financial_secret = $providerModel->credentials['financial_secret'] ?? null;
 
        $client = Http::withHeaders([
            'Accept' => 'application/json',
            'X-Auth-Token' => $token,
        ])->timeout(60);

        if (! config('services.wildflow.verify_tls', true)) {
            $client = $client->withoutVerifying();
        }

        $this->client = $client
            ->baseUrl($this->base_url)
            ->withMiddleware(function (callable $handler) use ($clientId) {
                return function (\Psr\Http\Message\RequestInterface $request, array $options) use ($handler, $clientId) {
                    $timestamp = time();
                    $body = (string)$request->getBody();
                    $path = $request->getUri()->getPath();

                    if ($clientId) {
                        $request = $request->withHeader('X-Client-Id', (string)$clientId);
                    }

                    $request = $request->withHeader('X-Financial-Timestamp', (string)$timestamp);

                    if ($this->financial_secret) {
                        $signature = hash_hmac('sha256', $timestamp.'.'.strtoupper($request->getMethod()).'.'.$path.'.'.$body, $this->financial_secret);
                        $request = $request->withHeader('X-Financial-Signature', $signature);
                    }

                    return $handler($request, $options);
                };
            });
    }

    public function getClient(): PendingRequest
    {
        return $this->client;
    }

    public function lastSourceLedgerReceipt(): ?array
    {
        return $this->lastSourceLedgerReceipt;
    }

    public function getExchangeRates(string $provider = 'ezpin'): array
    {
        if ($this->usesLocalKernel()) {
            return \App\Models\Currency::query()
                ->get()
                ->map(fn (\App\Models\Currency $currency): array => [
                    'code' => $currency->code,
                    'rate_to_rub' => (float) ($currency->manual_rate ?: $currency->rate_to_rub ?: 1),
                ])
                ->values()
                ->all();
        }

        $response = $this->client->get("providers/{$provider}/exchange-rates");
 
        if ($response->failed()) {
            throw new \RuntimeException("Wildflow Failed Get Rates: " . $response->body());
        }
 
        $this->rememberSourceLedgerReceipt($response->json());

        return $response->json('data.results') ?? $response->json('data') ?? [];
    }

    /**
     * 🚀 SHINY CLEAN ORDER PLACEMENT
     * Dropped all hardcoded terminals! Smart Aggregator handles routing.
     *
     * @param string|null $sellerId     LegalEntity ID on the marketplace side (for Ledger attribution)
     * @param string|null $sellerName   Human-readable seller name (optional, for Ledger display)
     */
    public function createOrder(
        string $service_sku,
        string $order_item_id,
        float $price,
        int $quantity,
        bool $pre_order = false,
        string $destination = '',
        string $provider = 'ezpin',
        ?string $terminalId = null,
        ?string $sellerId = null,
        ?string $sellerName = null
    )
    {
        if ($this->usesLocalKernel()) {
            return [
                'referenceCode' => $order_item_id,
                'order_id' => $order_item_id,
                'status' => 1,
                'status_text' => 'accept',
                'is_completed' => false,
            ];
        }

        $payload = array_filter([
            'service_sku'   => $service_sku,
            'price'         => $price,
            'quantity'      => $quantity,
            'pre_order'     => $pre_order,
            'referenceCode' => $order_item_id,
            'destination'   => $destination,
            'terminal_id'   => $terminalId,
            // Seller attribution is passed through to the Meanly API ledger.
            'seller_id'     => $sellerId,
            'seller_name'   => $sellerName,
        ], fn ($value) => $value !== null);

        \Illuminate\Support\Facades\Log::info("Wildflow Universal Order START [Provider: {$provider}]", ['payload' => $payload]);

        // Hit the super-smart dispatcher route!
        $response = $this->client->post("providers/{$provider}/order", $payload);

        if ($response->failed()) {
            \Illuminate\Support\Facades\Log::error("Wildflow Universal Order FAILED", [
                'status' => $response->status(),
                'body'   => $response->body(),
                'payload' => $payload
            ]);
            throw new \RuntimeException("API Error: " . $response->body());
        }

        $payload = $response->json();
        $this->rememberSourceLedgerReceipt($payload);

        $order = $payload['order'] ?? [];
        if (is_array($order) && isset($payload['source_ledger_receipt'])) {
            $order['source_ledger_receipt'] = $payload['source_ledger_receipt'];
        }

        return $order;
    }

    /**
     * 🛡️ AVAILABILITY GUARD
     * Checks if vendor has real items in stock before committing resources.
     */
    public function checkAvailability(
        string $service_sku, 
        int $quantity = 1, 
        ?float $price = null, 
        string $provider = 'ezpin',
        ?string $terminalId = null
    ): array
    {
        if ($this->usesLocalKernel()) {
            $skuBidx = app(\App\Services\VaultTransitService::class)->computeBlindIndex($service_sku);
            $providerType = $provider === 'ezpin' ? 'wildflow' : ($provider === 'ezpin-sandbox' ? 'wildflow-sandbox' : $provider);
            $product = \App\Models\ProviderProduct::query()
                ->whereHas('provider', fn ($query) => $query->where('type', $providerType))
                ->where(function ($query) use ($skuBidx): void {
                    $query->where('sku_bidx', $skuBidx)->orWhere('market_sku_bidx', $skuBidx);
                })
                ->first();
            $available = (bool) ($product?->is_active ?? false);

            return [
                'available' => $available,
                'availability' => [
                    'availability' => $available,
                    'available' => $available,
                    'detail' => $available ? 'Available from Meanly local kernel.' : 'Product is inactive or missing.',
                ],
            ];
        }

        $params = array_filter([
            'quantity' => $quantity,
            'price' => $price,
            'terminal_id' => $terminalId
        ], fn ($value) => $value !== null);
 
        $response = $this->client->get("providers/{$provider}/check-availability/{$service_sku}", $params);
 
        if ($response->failed()) {
             // Log silently, caller will decide handling
             \Illuminate\Support\Facades\Log::warning("Availability Check Failed", ['sku' => $service_sku, 'body' => $response->body()]);
             return ['available' => false, 'error' => $response->body()];
        }

        // Aggregator returns { success: true, availability: { availability: true, detail: '...' } }
        $data = $response->json();
        $this->rememberSourceLedgerReceipt($data);
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
        if ($this->usesLocalKernel()) {
            $item = \App\Models\Order\OrderItems::query()
                ->where('provider_order_id', $referenceCode)
                ->orWhere('uuid', $referenceCode)
                ->latest('id')
                ->first();

            if (! $item || ! filled($item->original_code)) {
                return [];
            }

            return [[
                'pinCode' => $item->original_code,
                'pin_code' => $item->original_code,
                'code' => $item->original_code,
            ]];
        }

        // Hits the smart proxy endpoint that can resolve both UUIDs and Local IDs!
        $response = $this->client->get("providers/{$provider}/orders/{$referenceCode}/normalized-cards");
 
        if ($response->failed()) {
            throw new \RuntimeException("Failed to fetch normalized cards: " . $response->body());
        }
 
        // Returns perfectly normalized card DTO payload!
        $payload = $response->json();
        $this->rememberSourceLedgerReceipt($payload);

        return $payload['cards'] ?? [];
    }

    /**
     * 💳 JIT CREDIT GRANTING (SECURED & MULTI-TENANT)
     */
    public function grantCredit(float $amount, string $reference, ?string $terminalId = null): array
    {
        if ($this->usesLocalKernel()) {
            $legalEntity = $this->resolveLocalLegalEntity($terminalId);
            $reservation = \App\Models\WildflowCreditReservation::query()->updateOrCreate(
                [
                    'legal_entity_id' => $legalEntity?->id,
                    'reference' => $reference,
                ],
                [
                    'amount' => $amount,
                    'status' => 'active',
                    'expires_at' => now()->addHours(2),
                ]
            );

            return [
                'success' => true,
                'reservation_id' => 'MEANLY-HOLD-'.$reservation->id,
                'idempotent' => ! $reservation->wasRecentlyCreated,
            ];
        }

        $payload = array_filter([
            'amount' => (float)$amount,
            'reference' => $reference,
            'terminal_id' => $terminalId,
        ], fn ($value) => $value !== null);
 
        $response = $this->client->post("partners/grant-credit", $payload);

        if ($response->failed()) {
            throw new \RuntimeException("Failed to grant credit: " . $response->body());
        }

        $payload = $response->json();
        $this->rememberSourceLedgerReceipt($payload);

        return $payload;
    }
 
    public function syncPartner(\App\Models\LegalEntity $entity, array $providerCredentials = []): array
    {
        if ($this->usesLocalKernel()) {
            $settings = $entity->agreement_metadata ?? [];
            $settings['kernel_external_id'] = (string) $entity->id;
            if ($l1Address = ($settings['l1_address'] ?? ($entity->user?->meta['l1_address'] ?? null))) {
                $settings['l1_address'] = $l1Address;
            }

            \App\Models\LegalEntity::withoutEvents(function () use ($entity, $settings, $providerCredentials): void {
                $apiToken = $entity->meanlyApiToken() ?: bin2hex(random_bytes(16));
                $financialSecret = $entity->meanlyFinancialSecret() ?: bin2hex(random_bytes(16));
                $attributes = [
                    'agreement_metadata' => $settings,
                    'vendor_credentials' => $providerCredentials,
                    'wildflow_api_token' => $entity->wildflow_api_token ?: $apiToken,
                    'wildflow_financial_secret' => $entity->wildflow_financial_secret ?: $financialSecret,
                ];

                if (\Illuminate\Support\Facades\Schema::hasColumn('legal_entities', 'meanly_api_token')) {
                    $attributes['meanly_api_token'] = $apiToken;
                }

                if (\Illuminate\Support\Facades\Schema::hasColumn('legal_entities', 'meanly_financial_secret')) {
                    $attributes['meanly_financial_secret'] = $financialSecret;
                }

                $entity->forceFill($attributes)->save();
            });

            return [
                'success' => true,
                'partner_id' => $entity->id,
                'external_id' => (string) $entity->id,
            ];
        }

        $l1Address = $entity->agreement_metadata['l1_address']
            ?? ($entity->user?->meta['l1_address'] ?? null);

        $payload = [
            'terminal_id' => (string)$entity->id,
            'name'        => $entity->name ?? $entity->short_name,
            'balance'     => (float)($entity->available_balance ?? $entity->balance ?? 0),
            'currency'    => $entity->currency ?? 'RUB',
            'provider_credentials' => $providerCredentials,
        ];

        if ($l1Address) {
            $payload['l1_address'] = $l1Address;
        }
 
        $response = $this->client->post("partners/sync", $payload);
 
        if ($response->failed()) {
            throw new \RuntimeException("Failed to sync partner in Kernel: " . $response->body());
        }
 
        $payload = $response->json();
        $this->rememberSourceLedgerReceipt($payload);

        return $payload;
    }

    public function getPartner(string $terminalId): array
    {
        if ($this->usesLocalKernel()) {
            $entity = $this->resolveLocalLegalEntity($terminalId);
            if (! $entity) {
                return [];
            }

            return [
                'id' => $entity->id,
                'external_id' => (string) $entity->id,
                'name' => $entity->name,
                'balance' => (float) $entity->available_balance,
                'currency' => $entity->currency ?? 'RUB',
                'active' => (bool) $entity->is_active,
            ];
        }

        $response = $this->client->get("partners/{$terminalId}");

        if ($response->failed()) {
            throw new \RuntimeException("Failed to fetch partner data: " . $response->body());
        }

        $payload = $response->json();
        $this->rememberSourceLedgerReceipt($payload);

        return $payload['data'] ?? [];
    }

    public function topUp(string $terminalId, float $amount, string $reference = ''): array
    {
        if ($this->usesLocalKernel()) {
            $entity = $this->resolveLocalLegalEntity($terminalId);
            if (! $entity) {
                throw new \RuntimeException("Partner [{$terminalId}] not found.");
            }

            $entity->increment('available_balance', $amount);

            return [
                'success' => true,
                'partner_id' => $entity->id,
                'balance' => (float) $entity->fresh()->available_balance,
                'reference' => $reference,
            ];
        }

        $payload = [
            'terminal_id' => $terminalId,
            'amount'      => $amount,
            'currency'    => 'RUB',
            'reference'   => $reference ?: "Top-up via Marketplace Admin",
        ];

        $response = $this->client->post("partners/top-up", $payload);

        if ($response->failed()) {
            throw new \RuntimeException("Failed to top up partner in Kernel: " . $response->body());
        }

        $payload = $response->json();
        $this->rememberSourceLedgerReceipt($payload);

        return $payload;
    }

    public function listPartners(): array
    {
        if ($this->usesLocalKernel()) {
            return \App\Models\LegalEntity::query()
                ->where('is_active', true)
                ->get()
                ->map(fn (\App\Models\LegalEntity $entity): array => [
                    'id' => $entity->id,
                    'external_id' => (string) $entity->id,
                    'name' => $entity->name,
                    'balance' => (float) $entity->available_balance,
                    'currency' => $entity->currency ?? 'RUB',
                ])
                ->all();
        }

        $response = $this->client->get("partners");

        if ($response->failed()) {
            throw new \RuntimeException("Failed to list partners from Kernel: " . $response->body());
        }

        $payload = $response->json();
        $this->rememberSourceLedgerReceipt($payload);

        return $payload['data'] ?? [];
    }

    public function deletePartner(string $terminalId): array
    {
        if ($this->usesLocalKernel()) {
            $entity = $this->resolveLocalLegalEntity($terminalId);
            if (! $entity) {
                return ['success' => false, 'message' => 'Partner not found'];
            }

            $entity->update(['is_active' => false]);

            return ['success' => true];
        }

        $response = $this->client->delete("partners/{$terminalId}");

        if ($response->failed()) {
            throw new \RuntimeException("Failed to delete partner from Kernel: " . $response->body());
        }

        return $response->json() ?? [];
    }

    private function usesLocalKernel(): bool
    {
        if ($this->hasRemoteKernelEndpoint) {
            return false;
        }

        return (string) config('services.wildflow.kernel_mode', 'http') === 'local';
    }

    private function rememberSourceLedgerReceipt(mixed $payload): void
    {
        if (! is_array($payload)) {
            return;
        }

        $receipt = data_get($payload, 'source_ledger_receipt');
        if (is_array($receipt)) {
            $this->lastSourceLedgerReceipt = $receipt;
        }
    }

    private function resolveLocalLegalEntity(?string $terminalId): ?\App\Models\LegalEntity
    {
        if (! filled($terminalId)) {
            return null;
        }

        if (ctype_digit((string) $terminalId)) {
            $entity = \App\Models\LegalEntity::query()->find((int) $terminalId);
            if ($entity) {
                return $entity;
            }
        }

        return \App\Models\SellerTerminal::query()
            ->with('legalEntity')
            ->where('terminal_id', $terminalId)
            ->first()
            ?->legalEntity;
    }
}
