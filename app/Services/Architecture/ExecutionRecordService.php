<?php

namespace App\Services\Architecture;

use App\Domain\Routing\RoutingCircuitBreaker;
use App\Domain\Routing\RoutingPolicy;
use App\Models\Architecture\ExecutionRecord;
use App\Models\Architecture\OfferSnapshot;
use App\Models\Order\Order;
use App\Models\Order\OrderItems;
use Illuminate\Support\Str;

class ExecutionRecordService implements ExecutionRecordServiceInterface
{
    public function __construct(
        private readonly RoutingCircuitBreaker $circuitBreaker,
    ) {}

    public function startExecution(
        string $snapshotId,
        ?int $orderId,
        ?int $orderItemId,
    ): string {
        $snapshot = OfferSnapshot::query()->findOrFail($snapshotId);

        if ($orderItemId !== null) {
            $existing = ExecutionRecord::query()
                ->where('order_item_id', $orderItemId)
                ->where('offer_snapshot_id', $snapshotId)
                ->whereIn('state', [
                    ExecutionRecord::STATE_RESERVED,
                    ExecutionRecord::STATE_FULFILLING,
                ])
                ->first();

            if ($existing) {
                return (string) $existing->id;
            }
        }

        $id = (string) Str::uuid();
        $idempotencyKey = sprintf(
            'exec:%s:%s:%s',
            $snapshotId,
            $orderItemId ?? 'order',
            $orderId ?? $id,
        );

        ExecutionRecord::query()->create([
            'id' => $id,
            'canonical_product_identity_id' => $snapshot->canonical_product_identity_id,
            'offer_snapshot_id' => $snapshot->id,
            'order_id' => $orderId,
            'order_item_id' => $orderItemId,
            'provider_id' => $snapshot->provider_id,
            'idempotency_key' => $idempotencyKey,
            'state' => ExecutionRecord::STATE_RESERVED,
        ]);

        ArchitectureMetrics::logFulfillment('architecture.execution.started', [
            'execution_record_id' => $id,
            'offer_snapshot_id' => $snapshotId,
            'order_id' => $orderId,
            'order_item_id' => $orderItemId,
        ]);

        if ($orderId !== null) {
            $order = Order::query()->find($orderId);
            if ($order) {
                $this->detectStatusMismatch($order);
            }
        }

        return $id;
    }

    public function markAsFulfilling(
        string $executionId,
        string $idempotencyKey,
        ?string $providerOrderId = null,
    ): void {
        $execution = ExecutionRecord::query()->findOrFail($executionId);

        $execution->forceFill([
            'state' => ExecutionRecord::STATE_FULFILLING,
            'idempotency_key' => $idempotencyKey,
            'provider_order_id' => $providerOrderId ?? $execution->provider_order_id,
        ])->save();

        ArchitectureMetrics::logFulfillment('architecture.execution.fulfilling', [
            'execution_record_id' => $executionId,
            'offer_snapshot_id' => $execution->offer_snapshot_id,
            'provider_order_id' => $providerOrderId,
        ]);
    }

    public function recordSuccess(
        string $executionId,
        string $vaultRefId,
        array $auditMeta = [],
    ): void {
        $execution = ExecutionRecord::query()->findOrFail($executionId);

        $execution->forceFill([
            'state' => ExecutionRecord::STATE_ISSUED,
            'vault_reference_id' => $vaultRefId,
            'error_class' => null,
            'audit_payload' => array_merge((array) $execution->audit_payload, $auditMeta, [
                'issued_at' => now()->toJSON(),
            ]),
        ])->save();

        ArchitectureMetrics::logFulfillment('architecture.execution.issued', [
            'execution_record_id' => $executionId,
            'offer_snapshot_id' => $execution->offer_snapshot_id,
            'vault_reference_id' => $vaultRefId,
        ]);
    }

    public function recordFailure(
        string $executionId,
        string $errorClass,
        array $errorDetails = [],
    ): void {
        $execution = ExecutionRecord::query()->findOrFail($executionId);

        $execution->forceFill([
            'state' => ExecutionRecord::STATE_FAILED,
            'error_class' => $errorClass,
            'audit_payload' => array_merge((array) $execution->audit_payload, $errorDetails, [
                'failed_at' => now()->toJSON(),
            ]),
        ])->save();

        ArchitectureMetrics::logFulfillment('architecture.execution.failed', [
            'execution_record_id' => $executionId,
            'offer_snapshot_id' => $execution->offer_snapshot_id,
            'error_class' => $errorClass,
        ]);

        if (config('routing.enabled', false)) {
            $policy = RoutingPolicy::fromConfig();
            $this->circuitBreaker->recordFailure((int) $execution->provider_id, $policy);
        }
    }

    public function findOpenForOrderItem(OrderItems $item): ?ExecutionRecord
    {
        return ExecutionRecord::query()
            ->where('order_item_id', $item->id)
            ->whereIn('state', [
                ExecutionRecord::STATE_RESERVED,
                ExecutionRecord::STATE_FULFILLING,
            ])
            ->latest('created_at')
            ->first();
    }

    public function startRetryForOrderItem(OrderItems $item, string $snapshotId): string
    {
        $retrySuffix = (string) ((int) ($item->purchase_retry_count ?? 0) + 1);

        $id = (string) Str::uuid();
        $snapshot = OfferSnapshot::query()->findOrFail($snapshotId);
        $idempotencyKey = sprintf('exec-retry:%s:%s:%s', $snapshotId, $item->id, $retrySuffix);

        ExecutionRecord::query()->create([
            'id' => $id,
            'canonical_product_identity_id' => $snapshot->canonical_product_identity_id,
            'offer_snapshot_id' => $snapshot->id,
            'order_id' => $item->order_id,
            'order_item_id' => $item->id,
            'provider_id' => $snapshot->provider_id,
            'idempotency_key' => $idempotencyKey,
            'state' => ExecutionRecord::STATE_RESERVED,
            'audit_payload' => ['retry' => true, 'retry_count' => $retrySuffix],
        ]);

        return $id;
    }

    private function detectStatusMismatch(Order $order): void
    {
        $executionId = data_get($order->info, 'order_safe.execution_record_id');
        if (! $executionId) {
            if (in_array($order->status, ['COMPLETED', 'PROCESSING'], true)) {
                ArchitectureMetrics::recordSettlementWithoutExecution($order);
            }

            return;
        }

        $execution = ExecutionRecord::query()->find($executionId);
        if (! $execution) {
            return;
        }

        $orderLooksIssued = in_array($order->status, ['COMPLETED', 'DELIVERED'], true) || (int) $order->progress_id === 4;
        $executionIssued = $execution->state === ExecutionRecord::STATE_ISSUED;

        if ($orderLooksIssued xor $executionIssued) {
            ArchitectureMetrics::recordExecutionStatusMismatch($order, $execution);
        }
    }
}
