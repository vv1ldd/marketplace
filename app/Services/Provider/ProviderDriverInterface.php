<?php

namespace App\Services\Provider;

use App\Models\Provider;

interface ProviderDriverInterface
{
    /**
     * Set the provider model instance.
     */
    public function setProvider(Provider $provider): self;

    /**
     * Create an order with the provider.
     * Returns the external order ID/Reference.
     */
    public function createOrder(string $sku, string $reference, float $price, int $quantity, array $meta = []): string;

    /**
     * Retrieve codes/cards for a given external order.
     * Returns an array of strings (the codes).
     */
    public function getCodes(string $externalOrderId): array;

    /**
     * Get the current balance with the provider.
     */
    public function getBalance(): float;

    /**
     * Get vendor-specific exchange rates.
     */
    public function getRates(): array;
}
