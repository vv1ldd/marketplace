<?php

namespace App\Support;

class MarketContext
{
    /**
     * @param array<int, string> $preferredProductRegions
     * @param array<int, string> $salesChannels
     */
    public function __construct(
        public readonly string $market,
        public readonly string $name,
        public readonly string $host,
        public readonly string $locale,
        public readonly string $currency,
        public readonly string $catalogScope,
        public readonly string $pricingScope,
        public readonly ?string $demandRegion,
        public readonly array $preferredProductRegions,
        public readonly array $salesChannels,
        public readonly bool $matchedDomain,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'market' => $this->market,
            'name' => $this->name,
            'host' => $this->host,
            'locale' => $this->locale,
            'currency' => $this->currency,
            'catalog_scope' => $this->catalogScope,
            'pricing_scope' => $this->pricingScope,
            'demand_region' => $this->demandRegion,
            'preferred_product_regions' => $this->preferredProductRegions,
            'sales_channels' => $this->salesChannels,
            'matched_domain' => $this->matchedDomain,
        ];
    }
}
