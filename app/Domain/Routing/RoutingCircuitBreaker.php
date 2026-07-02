<?php

namespace App\Domain\Routing;

use App\Services\Architecture\ArchitectureMetrics;
use Illuminate\Support\Facades\Cache;

class RoutingCircuitBreaker
{
    public function isTripped(int $providerId, RoutingPolicy $policy): bool
    {
        if ($providerId <= 0) {
            return false;
        }

        if (Cache::get($this->cacheKey($providerId), false)) {
            return true;
        }

        $threshold = $policy->circuitFailureThreshold();

        foreach ($policy->criticalAlertMetrics() as $metric) {
            if (ArchitectureMetrics::count($metric) >= $threshold) {
                return true;
            }
        }

        return false;
    }

    public function trip(int $providerId, RoutingPolicy $policy): void
    {
        if ($providerId <= 0) {
            return;
        }

        $coolDown = max(1, (int) ($policy->circuitBreaker['cool_down_seconds'] ?? 60));
        Cache::put($this->cacheKey($providerId), true, now()->addSeconds($coolDown));
    }

    private function cacheKey(int $providerId): string
    {
        return 'routing:circuit:open:'.$providerId;
    }
}
