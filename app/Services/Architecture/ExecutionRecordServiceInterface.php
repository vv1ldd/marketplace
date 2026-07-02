<?php

namespace App\Services\Architecture;

use App\Models\Architecture\ExecutionRecord;
use App\Models\Order\OrderItems;

interface ExecutionRecordServiceInterface
{
    /** @return string execution_record UUID */
    public function startExecution(
        string $snapshotId,
        ?int $orderId,
        ?int $orderItemId,
    ): string;

    public function markAsFulfilling(
        string $executionId,
        string $idempotencyKey,
        ?string $providerOrderId = null,
    ): void;

    public function recordSuccess(
        string $executionId,
        string $vaultRefId,
        array $auditMeta = [],
    ): void;

    public function recordFailure(
        string $executionId,
        string $errorClass,
        array $errorDetails = [],
    ): void;

    public function findOpenForOrderItem(OrderItems $item): ?ExecutionRecord;

    public function startRetryForOrderItem(OrderItems $item, string $snapshotId): string;
}
