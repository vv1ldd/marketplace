<?php

namespace App\Services\Provider;

use App\Models\Provider;
use App\Services\WildflowService;
use Illuminate\Support\Facades\Log;

class WildflowDriver implements ProviderDriverInterface
{
    protected ?Provider $provider = null;
    protected ?WildflowService $service = null;
    protected ?array $lastOrderResponse = null;

    public function setProvider(Provider $provider): self
    {
        $this->provider = $provider;
        $this->service = new WildflowService(providerModel: $provider);
        return $this;
    }

    protected function getService(): WildflowService
    {
        if (!$this->service) {
            $this->service = new WildflowService(providerModel: $this->provider);
        }
        return $this->service;
    }

    public function createOrder(string $sku, string $reference, float $price, int $quantity, array $meta = []): string
    {
        $totalAmount = $price * $quantity;
        $upstreamProvider = $this->upstreamProvider();

        // 💳 JIT CREDIT GRANTING (With Multi-Tenant Auto-Registration)
        try {
            $terminalId = (string)($meta['terminal_id'] ?? '');
            $sellerName = (string)($meta['seller_name'] ?? '');

            Log::info("Wildflow JIT Credit: Attempting to grant {$totalAmount} for ref {$reference}", [
                'terminal_id' => $terminalId,
                'seller_name' => $sellerName
            ]);

            $this->getService()->grantCredit($totalAmount, $reference, $terminalId);
        } catch (\Throwable $e) {
            Log::error("Wildflow JIT Credit FAILED: " . $e->getMessage());
            throw $e;
        }

        // Wildflow createOrder returns the aggregator order object. Keep the
        // marketplace reference as the idempotent handle for later card polling.
        $order = $this->getService()->createOrder(
            service_sku: $sku,
            order_item_id: $reference,
            price: $price,
            quantity: $quantity,
            pre_order: $meta['pre_order'] ?? false,
            destination: $meta['email'] ?? '',
            provider: $upstreamProvider,
            terminalId: $terminalId,
            sellerId: $meta['seller_id'] ?? null,
            sellerName: $meta['seller_name'] ?? null
        );
        $this->lastOrderResponse = is_array($order) ? $order : null;

        return $reference;
    }

    public function lastOrderResponse(): ?array
    {
        return $this->lastOrderResponse;
    }

    public function getCodes(string $externalOrderId): array
    {
        $cards = $this->getService()->getCards($externalOrderId, $this->upstreamProvider());

        return collect($cards)
            ->map(fn (array $card) => $card['pinCode'] ?? $card['pin_code'] ?? $card['code'] ?? null)
            ->filter()
            ->values()
            ->toArray();
    }

    public function getBalance(): float
    {
        return 0.0; 
    }

    public function getRates(): array
    {
        return $this->getService()->getExchangeRates($this->upstreamProvider());
    }

    private function upstreamProvider(): string
    {
        return (string) (
            data_get($this->provider?->settings, 'upstream_provider')
            ?? data_get($this->provider?->settings, 'provider')
            ?? data_get($this->provider?->credentials, 'upstream_provider')
            ?? data_get($this->provider?->credentials, 'provider')
            ?? ($this->provider?->type === 'wildflow-sandbox' ? 'ezpin-sandbox' : null)
            ?? 'ezpin'
        );
    }
}
