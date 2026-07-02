<?php

namespace App\Services\Architecture;

use App\Models\Architecture\ExecutionRecord;
use App\Models\Order\Order;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ArchitectureMetrics
{
    public static function increment(string $metric, int $by = 1): void
    {
        $key = 'architecture.metrics.'.str_replace('.', ':', $metric);

        try {
            Cache::increment($key, $by);
        } catch (\Throwable) {
            Cache::put($key, (int) Cache::get($key, 0) + $by, now()->addDay());
        }

        Log::debug('architecture.metric', ['metric' => $metric, 'increment' => $by]);
    }

    public static function count(string $metric): int
    {
        $key = 'architecture.metrics.'.str_replace('.', ':', $metric);

        return (int) Cache::get($key, 0);
    }

    public static function recordSnapshotCreated(): void
    {
        self::increment('architecture.snapshot.created_total');
    }

    public static function recordFallbackLiveCatalog(): void
    {
        self::increment('architecture.execution.fallback_live_catalog_count');
    }

    public static function recordSettlementWithoutExecution(Order $order): void
    {
        self::increment('architecture.anomaly.settlement_without_execution');
        Log::warning('architecture.anomaly.settlement_without_execution', [
            'order_id' => $order->id,
            'order_reference' => $order->order_id,
        ]);
    }

    public static function recordExecutionStatusMismatch(Order $order, ExecutionRecord $execution): void
    {
        self::increment('architecture.anomaly.execution_status_mismatch');
        Log::warning('architecture.anomaly.execution_status_mismatch', [
            'order_id' => $order->id,
            'order_status' => $order->status,
            'execution_record_id' => $execution->id,
            'execution_state' => $execution->state,
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function logFulfillment(string $message, array $context = []): void
    {
        Log::info($message, $context);
    }
}
