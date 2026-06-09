<?php

namespace App\Services;

use App\Support\MarketContext;
use App\Support\PricingContext;

class PricingContextResolver
{
    public function resolve(MarketContext $market): PricingContext
    {
        $config = (array) config("markets.markets.{$market->market}", []);

        return new PricingContext(
            pricingScope: (string) ($config['pricing_scope'] ?? $market->pricingScope),
            displayCurrency: strtoupper((string) ($config['display_currency'] ?? $market->currency)),
            settlementCurrency: strtoupper((string) ($config['settlement_currency'] ?? 'RUB')),
            storageCurrency: strtoupper((string) ($config['storage_currency'] ?? 'RUB')),
        );
    }
}
