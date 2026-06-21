<?php

namespace App\Services;

use App\Contracts\SettlementAdapter;
use App\Support\SettlementAdapterConfig;
use InvalidArgumentException;

class SettlementAdapterRegistry
{
    /**
     * @param array<string, SettlementAdapter> $adapters
     */
    public function __construct(
        private readonly array $adapters,
    ) {}

    public function adapter(string $key): SettlementAdapter
    {
        $adapter = $this->adapters[$key] ?? null;
        if (! $adapter instanceof SettlementAdapter) {
            throw new InvalidArgumentException("No settlement adapter registered for [{$key}].");
        }

        return $adapter;
    }

    public function has(string $key): bool
    {
        return isset($this->adapters[$key]);
    }

    public function isEnabled(string $key): bool
    {
        return SettlementAdapterConfig::isEnabled($key);
    }

    public function mode(string $key): string
    {
        return SettlementAdapterConfig::mode($key);
    }

    public function allowsWrite(string $key): bool
    {
        return SettlementAdapterConfig::allowsWrite($key);
    }

    /**
     * @return array{status: string, healthy: bool, adapter: string, mode: string, failures: list<string>, checks: array<string, mixed>}|null
     */
    public function healthCheck(string $key): ?array
    {
        if (! $this->has($key)) {
            return null;
        }

        return $this->adapter($key)->healthCheck();
    }
}
