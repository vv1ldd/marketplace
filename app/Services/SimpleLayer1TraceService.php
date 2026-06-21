<?php

namespace App\Services;

use App\Models\Order\Order;
use App\Models\Order\OrderItems;
use App\Models\ProductInventory;
use App\Models\SovereignLedger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class SimpleLayer1TraceService
{
    public function __construct(
        private readonly SimpleLayer1TransactionReferenceService $references,
        private readonly SettlementNetworkRegistry $settlementNetworks,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function trace(string $reference, ?int $legalEntityId = null): ?array
    {
        $target = $this->targetEntry($reference, $legalEntityId);

        if (! $target) {
            return null;
        }

        $relatedEntities = $this->relatedEntitiesFor($target);
        $entityTimeline = $this->ledgerForEntities($relatedEntities, $target, $legalEntityId);
        $proofWindow = $this->proofWindow($target, $legalEntityId);

        return [
            'network' => $this->settlementNetworks->traceLabel('simple-layer-1'),
            'query_ref' => $reference,
            'canonical_ref' => $target->transactionReference(),
            'target' => $this->formatEntry($target),
            'entity' => $this->formatEntity($target->entity),
            'support_summary' => $this->supportSummary($target, $relatedEntities, $entityTimeline),
            'proof_window' => $proofWindow->map(fn (SovereignLedger $entry) => $this->formatEntry($entry))->values(),
            'entity_timeline' => $entityTimeline->map(fn (SovereignLedger $entry) => $this->formatEntry($entry))->values(),
        ];
    }

    private function targetEntry(string $reference, ?int $legalEntityId): ?SovereignLedger
    {
        $normalized = strtolower(trim($reference));

        if (preg_match('/^[a-f0-9]{64}$/', $normalized)) {
            if (\Illuminate\Support\Facades\DB::connection()->getDriverName() === 'sqlite') {
                return SovereignLedger::query()
                    ->when($legalEntityId, fn ($query) => $query->where('legal_entity_id', $legalEntityId))
                    ->oldest('id')
                    ->get()
                    ->first(function (SovereignLedger $entry) use ($normalized): bool {
                        return strtolower((string) data_get($entry->payload, 'tx_hash')) === $normalized
                            || strtolower((string) data_get($entry->payload, 'simple_layer_one.tx_hash')) === $normalized;
                    });
            }

            return SovereignLedger::query()
                ->when($legalEntityId, fn ($query) => $query->where('legal_entity_id', $legalEntityId))
                ->where(function ($query) use ($normalized) {
                    $query
                        ->where('payload->tx_hash', $normalized)
                        ->orWhere('payload->simple_layer_one->tx_hash', $normalized);
                })
                ->oldest('id')
                ->first();
        }

        $prefix = $this->references->fingerprintPrefixFromReference($reference);
        if ($prefix === null) {
            return null;
        }

        return SovereignLedger::query()
            ->whereRaw('LOWER(fingerprint) LIKE ?', [$prefix.'%'])
            ->when($legalEntityId, fn ($query) => $query->where('legal_entity_id', $legalEntityId))
            ->latest('id')
            ->first();
    }

    /**
     * @return Collection<int, Model>
     */
    private function relatedEntitiesFor(SovereignLedger $entry): Collection
    {
        $entities = collect();
        $entity = $entry->entity;

        if ($entity instanceof Model) {
            $entities->push($entity);
        }

        if ($entity instanceof Order) {
            $items = $entity->items()->get();
            $entities = $entities->merge($items);
            $entities = $entities->merge(ProductInventory::query()->whereIn('order_item_id', $items->pluck('id'))->get());
        }

        if ($entity instanceof OrderItems) {
            $entities->push($entity->order);
            $entities = $entities->merge(ProductInventory::query()->where('order_item_id', $entity->id)->get());
        }

        if ($entity instanceof ProductInventory) {
            $item = $entity->orderItem;
            $entities->push($item);
            $entities->push($item?->order);
        }

        $payloadOrderId = data_get($entry->payload, 'order_id');
        if ($payloadOrderId) {
            $entities->push(Order::query()->find($payloadOrderId));
        }

        $payloadOrderItemId = data_get($entry->payload, 'order_item_id');
        if ($payloadOrderItemId) {
            $item = OrderItems::query()->find($payloadOrderItemId);
            $entities->push($item);
            $entities->push($item?->order);
            $entities = $entities->merge(ProductInventory::query()->where('order_item_id', $payloadOrderItemId)->get());
        }

        return $entities
            ->filter(fn ($model) => $model instanceof Model && $model->exists)
            ->unique(fn (Model $model) => $model::class.':'.$model->getKey())
            ->values();
    }

    /**
     * @param  Collection<int, Model>  $entities
     * @return Collection<int, SovereignLedger>
     */
    private function ledgerForEntities(Collection $entities, SovereignLedger $target, ?int $legalEntityId): Collection
    {
        if ($entities->isEmpty()) {
            return collect([$target]);
        }

        return SovereignLedger::query()
            ->when($legalEntityId, fn ($query) => $query->where('legal_entity_id', $legalEntityId))
            ->where(function ($query) use ($entities, $target) {
                $query->whereKey($target->id);

                foreach ($entities as $entity) {
                    $query->orWhere(function ($entityQuery) use ($entity) {
                        $entityQuery
                            ->where('entity_type', $entity::class)
                            ->where('entity_id', $entity->getKey());
                    });
                }
            })
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, SovereignLedger>
     */
    private function proofWindow(SovereignLedger $target, ?int $legalEntityId): Collection
    {
        $previous = $target->previous_fingerprint
            ? SovereignLedger::query()
                ->when($legalEntityId, fn ($query) => $query->where('legal_entity_id', $legalEntityId))
                ->where('fingerprint', $target->previous_fingerprint)
                ->first()
            : null;

        $next = SovereignLedger::query()
            ->when($legalEntityId, fn ($query) => $query->where('legal_entity_id', $legalEntityId))
            ->where('previous_fingerprint', $target->fingerprint)
            ->oldest('id')
            ->first();

        return collect([$previous, $target, $next])->filter()->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function formatEntry(SovereignLedger $entry): array
    {
        return [
            'transaction_ref' => $entry->transactionReference(),
            'event_type' => $entry->event_type,
            'created_at' => $entry->created_at?->toIso8601String(),
            'entity_type' => class_basename((string) $entry->entity_type),
            'entity_ref' => $this->entityReference($entry->entity),
            'fingerprint' => $entry->fingerprint,
            'previous_ref' => $entry->previous_fingerprint
                ? $this->references->fromFingerprint($entry->previous_fingerprint)
                : null,
            'previous_fingerprint' => $entry->previous_fingerprint,
            'trigger_source' => $entry->trigger_source,
            'payload' => $entry->payload,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function formatEntity(?Model $entity): ?array
    {
        if (! $entity) {
            return null;
        }

        return [
            'type' => class_basename($entity::class),
            'transaction_ref' => $this->entityReference($entity),
        ];
    }

    private function entityReference(?Model $entity): ?string
    {
        if (! $entity || ! method_exists($entity, 'transactionReference')) {
            return null;
        }

        return $entity->transactionReference();
    }

    /**
     * @param  Collection<int, Model>  $relatedEntities
     * @param  Collection<int, SovereignLedger>  $timeline
     * @return array<string, mixed>
     */
    private function supportSummary(SovereignLedger $target, Collection $relatedEntities, Collection $timeline): array
    {
        $types = $relatedEntities
            ->map(fn (Model $model) => class_basename($model::class))
            ->countBy()
            ->all();

        return [
            'meaning' => 'This is a Simple Layer One transaction reference derived from a ledger fingerprint prefix or a signed tx_hash.',
            'target_event' => $target->event_type,
            'related_entity_count' => $relatedEntities->count(),
            'related_entity_types' => $types,
            'timeline_event_count' => $timeline->count(),
            'how_to_read' => 'Use proof_window for hash continuity and entity_timeline for the business story around the same order, item, or voucher.',
        ];
    }
}
