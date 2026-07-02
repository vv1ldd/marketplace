<?php

namespace App\Domain\Routing;

use App\Models\Architecture\ExecutionRecord;
use Illuminate\Support\Facades\Cache;

class ExecutionRecordMetricsProvider implements ProviderMetricsProviderInterface
{
    public function getSignalsForProvider(int $providerId): ProviderRuntimeSignals
    {
        if ($providerId <= 0) {
            return new ProviderRuntimeSignals(successRate: 0.5, p50LatencyMs: 5000, stockStatus: 1.0);
        }

        $ttlSeconds = (int) config('routing.metrics.cache_seconds', 1800);
        $ttlSeconds = max(60, $ttlSeconds);

        return Cache::remember(
            key: 'routing:metrics:provider:'.$providerId,
            ttl: now()->addSeconds($ttlSeconds),
            callback: fn () => $this->computeSignals($providerId),
        );
    }

    private function computeSignals(int $providerId): ProviderRuntimeSignals
    {
        $windowDays = (int) config('routing.metrics.window_days', 7);
        $windowDays = max(1, min(30, $windowDays));
        $since = now()->subDays($windowDays);

        $states = ExecutionRecord::query()
            ->where('provider_id', $providerId)
            ->where('created_at', '>=', $since)
            ->whereIn('state', [
                ExecutionRecord::STATE_ISSUED,
                ExecutionRecord::STATE_FAILED,
                ExecutionRecord::STATE_MANUAL,
            ])
            ->select(['state', 'created_at', 'updated_at'])
            ->latest('created_at')
            ->limit(5000)
            ->get();

        if ($states->isEmpty()) {
            return new ProviderRuntimeSignals(successRate: 0.5, p50LatencyMs: 5000, stockStatus: 1.0);
        }

        $issued = $states->where('state', ExecutionRecord::STATE_ISSUED)->count();
        $failed = $states->where('state', ExecutionRecord::STATE_FAILED)->count();
        $manual = $states->where('state', ExecutionRecord::STATE_MANUAL)->count();

        $total = max(1, $issued + $failed + $manual);
        $successRate = $issued / $total;

        $latencies = $states
            ->where('state', ExecutionRecord::STATE_ISSUED)
            ->map(function (ExecutionRecord $record): int {
                $created = $record->created_at?->getTimestampMs() ?? 0;
                $updated = $record->updated_at?->getTimestampMs() ?? 0;
                if ($created <= 0 || $updated <= 0 || $updated < $created) {
                    return 5000;
                }

                return (int) min(600000, max(1, $updated - $created));
            })
            ->sort()
            ->values();

        $p50 = $latencies->isEmpty()
            ? 5000
            : (int) $latencies[(int) floor(($latencies->count() - 1) * 0.5)];

        return new ProviderRuntimeSignals(
            successRate: max(0.0, min(1.0, $successRate)),
            p50LatencyMs: max(1, $p50),
            stockStatus: 1.0,
        );
    }
}

