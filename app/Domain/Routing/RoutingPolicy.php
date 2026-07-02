<?php

namespace App\Domain\Routing;

class RoutingPolicy
{
    /**
     * @param  array<string, float>  $weights
     * @param  array<string, mixed>  $circuitBreaker
     * @param  array<int, array{provider_id: int, traffic_weight: int}>  $providerSplit
     */
    public function __construct(
        public readonly string $type,
        public readonly array $weights,
        public readonly string $version = 'v1',
        public readonly array $circuitBreaker = [],
        public readonly array $providerSplit = [],
    ) {}

    public static function fromConfig(?string $type = null): self
    {
        return new self(
            type: $type ?? (string) config('routing.default_policy', 'weighted'),
            weights: (array) config('routing.weights', []),
            version: (string) config('routing.policy_version', 'v1'),
            circuitBreaker: (array) config('routing.circuit_breaker', []),
            providerSplit: (array) config('routing.provider_split', []),
        );
    }

    public function isWeighted(): bool
    {
        return $this->type === 'weighted';
    }

    public function weight(string $metric): float
    {
        return (float) ($this->weights[$metric] ?? 0.0);
    }

    public function calculateStickySlot(string $entitlementId, string $intentId): int
    {
        return abs(crc32($entitlementId.$intentId.$this->version)) % 100;
    }

    /**
     * @return array<int, int> provider_id => traffic_weight
     */
    public function providerTrafficWeights(): array
    {
        $weights = [];

        foreach ($this->providerSplit as $entry) {
            $providerId = (int) ($entry['provider_id'] ?? 0);
            if ($providerId <= 0) {
                continue;
            }

            $weights[$providerId] = max(0, (int) ($entry['traffic_weight'] ?? 0));
        }

        return $weights;
    }

    public function circuitFailureThreshold(): int
    {
        return max(1, (int) ($this->circuitBreaker['failure_threshold'] ?? 5));
    }

    /**
     * @return array<int, string>
     */
    public function criticalAlertMetrics(): array
    {
        return array_values((array) ($this->circuitBreaker['critical_alerts'] ?? []));
    }
}
