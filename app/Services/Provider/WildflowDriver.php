<?php

namespace App\Services\Provider;

use App\Models\Provider;
use App\Services\WildflowService;
use Illuminate\Support\Facades\Log;

class WildflowDriver implements ProviderDriverInterface
{
    protected ?Provider $provider = null;
    protected ?WildflowService $service = null;

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

        // Wildflow createOrder returns the order object
        $order = $this->getService()->createOrder(
            service_sku: $sku,
            order_item_id: $reference,
            price: $price,
            quantity: $quantity,
            pre_order: $meta['pre_order'] ?? false,
            destination: $meta['email'] ?? '',
            terminalId: $terminalId
        );

        return $reference;
    }

    public function getCodes(string $externalOrderId): array
    {
        $cards = $this->getService()->getCards($externalOrderId);
        return collect($cards)->pluck('pinCode')->toArray();
    }

    public function getBalance(): float
    {
        return 0.0; 
    }

    public function getRates(): array
    {
        return $this->getService()->getExchangeRates();
    }
}
