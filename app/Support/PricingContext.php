<?php

namespace App\Support;

class PricingContext
{
    public function __construct(
        public readonly string $pricingScope,
        public readonly string $displayCurrency,
        public readonly string $settlementCurrency,
        public readonly string $storageCurrency,
    ) {}

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'pricing_scope' => $this->pricingScope,
            'display_currency' => $this->displayCurrency,
            'settlement_currency' => $this->settlementCurrency,
            'storage_currency' => $this->storageCurrency,
        ];
    }
}
