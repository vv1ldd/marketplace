<?php

namespace App\Services\Continuity;

use App\Models\MarketplaceTransitionOutbox;
use App\Models\SovereignLedger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Meanly\Mdk\Kernel\Identity\CanonicalJsonEncoder;

class TransitionOutboxService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function recordAcceptedTransition(
        string $scope,
        string $transitionType,
        ?string $transitionId,
        string $transitionHash,
        ?Model $aggregate = null,
        array $payload = [],
        ?string $idempotencyKey = null,
        ?int $authorityDecisionId = null,
        ?string $authorityDecisionHash = null,
    ): ?MarketplaceTransitionOutbox {
        if (! Schema::hasTable('marketplace_transition_outbox')) {
            return null;
        }

        $idempotencyKey ??= $transitionType.':'.($transitionId ?: $transitionHash);

        $existing = MarketplaceTransitionOutbox::query()
            ->where('scope', $scope)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            return $existing;
        }

        return MarketplaceTransitionOutbox::create([
            'event_uuid' => (string) Str::uuid(),
            'scope' => $scope,
            'aggregate_type' => $aggregate ? $aggregate::class : null,
            'aggregate_id' => $aggregate?->getKey(),
            'transition_type' => $transitionType,
            'transition_id' => $transitionId,
            'transition_hash' => $transitionHash,
            'authority_decision_id' => $authorityDecisionId,
            'authority_decision_hash' => $authorityDecisionHash,
            'idempotency_key' => $idempotencyKey,
            'payload' => $payload,
            'payload_hash' => $this->hashPayload($payload),
            'anchor_status' => MarketplaceTransitionOutbox::ANCHOR_PENDING,
            'status' => MarketplaceTransitionOutbox::STATUS_PENDING,
            'available_at' => now(),
        ]);
    }

    public function recordFromLedger(SovereignLedger $ledger): ?MarketplaceTransitionOutbox
    {
        return $this->recordAcceptedTransition(
            scope: $this->scopeForLedger($ledger),
            transitionType: $ledger->event_type,
            transitionId: 'sovereign_ledger:'.$ledger->id,
            transitionHash: $ledger->fingerprint,
            aggregate: $ledger->entity,
            payload: [
                'sovereign_ledger_id' => $ledger->id,
                'event_type' => $ledger->event_type,
                'entity_type' => $ledger->entity_type,
                'entity_id' => $ledger->entity_id,
                'legal_entity_id' => $ledger->legal_entity_id,
                'shop_id' => $ledger->shop_id,
                'fingerprint' => $ledger->fingerprint,
                'previous_fingerprint' => $ledger->previous_fingerprint,
            ],
            idempotencyKey: 'sovereign_ledger:'.$ledger->id,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function hashPayload(array $payload): string
    {
        return hash('sha256', (new CanonicalJsonEncoder())->encode($payload));
    }

    private function scopeForLedger(SovereignLedger $ledger): string
    {
        if ($ledger->legal_entity_id) {
            return 'legal_entity:'.$ledger->legal_entity_id;
        }

        if ($ledger->shop_id) {
            return 'shop:'.$ledger->shop_id;
        }

        return 'marketplace:global';
    }
}
