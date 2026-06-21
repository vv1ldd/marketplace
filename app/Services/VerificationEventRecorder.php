<?php

namespace App\Services;

use App\Models\BindingProof;
use App\Models\VaultSettlementProof;
use App\Models\VerificationEvent;
use Illuminate\Database\Eloquent\Collection;

class VerificationEventRecorder
{
    public function recordProofVerified(BindingProof $proof, array $context = []): VerificationEvent
    {
        return $this->record(
            vaultId: (string) $proof->vault_id,
            bindingProofId: (int) $proof->id,
            vaultSettlementProofId: null,
            proofType: (string) $proof->proof_type,
            bindingKey: (string) $proof->binding_key,
            eventType: VerificationEvent::TYPE_PROOF_VERIFIED,
            payload: array_merge($context, [
                'proof_reference' => $proof->proof_reference,
                'verification_state' => $proof->verification_state,
                'proof_payload' => $proof->proof_payload ?? [],
                'verified_at' => $proof->verified_at?->toJSON(),
            ]),
        );
    }

    public function recordSettlementProofVerified(VaultSettlementProof $proof, array $context = []): VerificationEvent
    {
        return $this->record(
            vaultId: (string) $proof->vault_id,
            bindingProofId: null,
            vaultSettlementProofId: (int) $proof->id,
            proofType: (string) $proof->proof_kind,
            bindingKey: (string) $proof->rail,
            eventType: VerificationEvent::TYPE_PROOF_VERIFIED,
            payload: array_merge($context, [
                'external_reference' => $proof->external_reference,
                'status' => $proof->status,
                'evidence' => $proof->evidence ?? [],
                'verified_at' => $proof->verified_at?->toJSON(),
                'observed_at' => $proof->observed_at?->toJSON(),
            ]),
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    public function recordProofVerificationFailed(
        string $vaultId,
        string $proofType,
        string $bindingKey,
        string $code,
        string $message,
        array $context = [],
    ): VerificationEvent {
        return $this->record(
            vaultId: $vaultId,
            bindingProofId: null,
            vaultSettlementProofId: isset($context['vault_settlement_proof_id'])
                ? (int) $context['vault_settlement_proof_id']
                : null,
            proofType: $proofType,
            bindingKey: $bindingKey,
            eventType: VerificationEvent::TYPE_PROOF_VERIFICATION_FAILED,
            payload: array_merge($context, [
                'error' => [
                    'code' => $code,
                    'message' => $message,
                ],
            ]),
        );
    }

    public function recordSettlementProofVerificationFailed(
        string $vaultId,
        string $proofKind,
        string $bindingKey,
        string $code,
        string $message,
        array $context = [],
    ): VerificationEvent {
        return $this->recordProofVerificationFailed(
            vaultId: $vaultId,
            proofType: $proofKind,
            bindingKey: $bindingKey,
            code: $code,
            message: $message,
            context: $context,
        );
    }

    /**
     * @return Collection<int, VerificationEvent>
     */
    public function listForVault(string $vaultId, ?string $eventType = null, int $limit = 50): Collection
    {
        $query = VerificationEvent::query()
            ->where('vault_id', $vaultId)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit($limit);

        if ($eventType !== null) {
            $query->where('event_type', $eventType);
        }

        return $query->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function formatEvent(VerificationEvent $event): array
    {
        return [
            'id' => $event->id,
            'vault_id' => $event->vault_id,
            'binding_proof_id' => $event->binding_proof_id,
            'vault_settlement_proof_id' => $event->vault_settlement_proof_id,
            'proof_type' => $event->proof_type,
            'binding_key' => $event->binding_key,
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
        ?int $bindingProofId,
        ?int $vaultSettlementProofId,
        string $proofType,
        string $bindingKey,
        string $eventType,
        array $payload = [],
    ): VerificationEvent {
        return VerificationEvent::query()->create([
            'vault_id' => $vaultId,
            'binding_proof_id' => $bindingProofId,
            'vault_settlement_proof_id' => $vaultSettlementProofId,
            'proof_type' => $proofType,
            'binding_key' => $bindingKey,
            'event_type' => $eventType,
            'payload' => $payload === [] ? null : $payload,
            'occurred_at' => now(),
        ]);
    }
}
