<?php

namespace App\Services;

use App\Models\SettlementAuditEvent;
use Illuminate\Database\Eloquent\Collection;

class SettlementAuditEventRecorder
{
    public function recordAttachmentCreated(
        string $identityId,
        string $vaultId,
        string $adapterKey,
        string $chain,
        string $address,
    ): SettlementAuditEvent {
        return $this->record(
            vaultId: $vaultId,
            identityId: $identityId,
            adapterKey: $adapterKey,
            eventType: SettlementAuditEventTypes::ATTACHMENT_CREATED,
            payload: [
                'identity_id' => $identityId,
                'chain' => $chain,
                'address' => $address,
            ],
        );
    }

    public function recordSettlementObserved(
        string $identityId,
        string $vaultId,
        string $adapterKey,
        string $asset,
        string $amount,
        int $blockNumber,
    ): SettlementAuditEvent {
        return $this->record(
            vaultId: $vaultId,
            identityId: $identityId,
            adapterKey: $adapterKey,
            eventType: SettlementAuditEventTypes::SETTLEMENT_OBSERVED,
            payload: [
                'identity_id' => $identityId,
                'asset' => $asset,
                'amount' => $amount,
                'block_number' => $blockNumber,
                'observed_at' => now()->toJSON(),
            ],
        );
    }

    public function recordBalanceRead(
        string $identityId,
        string $vaultId,
        string $source,
    ): SettlementAuditEvent {
        return $this->record(
            vaultId: $vaultId,
            identityId: $identityId,
            adapterKey: $source,
            eventType: SettlementAuditEventTypes::BALANCE_READ,
            payload: [
                'identity_id' => $identityId,
                'source' => $source,
                'timestamp' => now()->toJSON(),
            ],
        );
    }

    /**
     * @return Collection<int, SettlementAuditEvent>
     */
    public function listForVault(string $vaultId, int $limit = 50): Collection
    {
        return SettlementAuditEvent::query()
            ->where('vault_id', $vaultId)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function lastBalanceReadForAdapter(string $adapterKey): ?SettlementAuditEvent
    {
        return SettlementAuditEvent::query()
            ->where('adapter_key', $adapterKey)
            ->where('event_type', SettlementAuditEventTypes::BALANCE_READ)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function formatEvent(SettlementAuditEvent $event): array
    {
        return [
            'id' => $event->id,
            'vault_id' => $event->vault_id,
            'identity_id' => $event->identity_id,
            'adapter_key' => $event->adapter_key,
            'event_type' => $event->event_type,
            'payload' => $event->payload ?? [],
            'occurred_at' => $event->occurred_at?->toJSON(),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function record(
        string $vaultId,
        string $identityId,
        string $adapterKey,
        string $eventType,
        array $payload,
    ): SettlementAuditEvent {
        return SettlementAuditEvent::query()->create([
            'vault_id' => $vaultId,
            'identity_id' => $identityId,
            'adapter_key' => $adapterKey,
            'event_type' => $eventType,
            'payload' => $payload,
            'occurred_at' => now(),
        ]);
    }
}
