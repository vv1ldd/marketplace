<?php

namespace App\Domain\Routing;

use Illuminate\Support\Facades\Cache;

class RoutingCircuitBreaker
{
    public const STATUS_CLOSED = 'closed';
    public const STATUS_OPEN = 'open';
    public const STATUS_HALF_OPEN = 'half-open';

    public function isTripped(int $providerId, RoutingPolicy $policy): bool
    {
        if ($providerId <= 0) {
            return false;
        }

        return $this->status($providerId) === self::STATUS_OPEN;
    }

    public function status(int $providerId): string
    {
        return (string) Cache::get($this->statusKey($providerId), self::STATUS_CLOSED);
    }

    public function recordFailure(int $providerId, RoutingPolicy $policy, int $by = 1): void
    {
        if ($providerId <= 0) {
            return;
        }

        $failures = (int) Cache::increment($this->failuresKey($providerId), $by);
        if ($failures >= $policy->circuitFailureThreshold()) {
            $this->open($providerId, $policy);
        }
    }

    public function open(int $providerId, RoutingPolicy $policy): void
    {
        if ($providerId <= 0) {
            return;
        }

        $coolDown = max(1, (int) ($policy->circuitBreaker['cool_down_seconds'] ?? 60));
        Cache::put($this->statusKey($providerId), self::STATUS_OPEN, now()->addSeconds($coolDown));
    }

    /**
     * Backwards compatible alias (Phase A tests).
     */
    public function trip(int $providerId, RoutingPolicy $policy): void
    {
        $this->open($providerId, $policy);
    }

    public function reset(int $providerId): void
    {
        Cache::forget($this->failuresKey($providerId));
        Cache::forget($this->statusKey($providerId));
    }

    private function failuresKey(int $providerId): string
    {
        return 'routing:cb:provider:'.$providerId.':failures';
    }

    private function statusKey(int $providerId): string
    {
        return 'routing:cb:provider:'.$providerId.':status';
    }
}
