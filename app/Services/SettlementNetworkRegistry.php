<?php

namespace App\Services;

use App\Contracts\SettlementNetworkAdapter;
use App\Contracts\SupportsMerchantDeposits;
use App\Support\SettlementNetwork;
use InvalidArgumentException;

class SettlementNetworkRegistry
{
    /**
     * @param array<string, SettlementNetworkAdapter> $adapters
     */
    public function __construct(
        private readonly SettlementNetworkResolver $resolver,
        private readonly array $adapters,
    ) {}

    public function defaultKey(): string
    {
        return $this->resolver->defaultKey();
    }

    public function defaultNetwork(): SettlementNetwork
    {
        return $this->resolver->default();
    }

    public function defaultAdapter(): SettlementNetworkAdapter
    {
        return $this->adapter($this->defaultKey());
    }

    public function adapter(string $key): SettlementNetworkAdapter
    {
        $adapter = $this->adapters[$key] ?? null;
        if (! $adapter instanceof SettlementNetworkAdapter) {
            throw new InvalidArgumentException("No adapter registered for settlement network [{$key}].");
        }

        return $adapter;
    }

    public function network(string $key): SettlementNetwork
    {
        return $this->resolver->resolve($key);
    }

    /**
     * @return array<string, mixed>
     */
    public function storefrontCatalog(): array
    {
        return $this->resolver->storefrontCatalog();
    }

    public function traceLabel(string $key): string
    {
        return $this->adapter($key)->traceNetworkLabel();
    }

    public function merchantCryptoNetworkKey(): string
    {
        return (string) config('blockchain_networks.merchant_crypto_network', 'polygon');
    }

    public function cryptoRailsEnabled(): bool
    {
        return (bool) config('blockchain_networks.crypto_rails_enabled', false);
    }

    public function merchantDepositAdapter(): SupportsMerchantDeposits
    {
        $adapter = $this->adapter($this->merchantCryptoNetworkKey());
        if (! $adapter instanceof SupportsMerchantDeposits) {
            throw new InvalidArgumentException('Merchant crypto settlement network has no deposit adapter.');
        }

        return $adapter;
    }

    public function payloadNetworkLabel(string $key): string
    {
        return $this->traceLabel($key);
    }
}
