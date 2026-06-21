<?php

namespace App\Services;

use App\Models\BindingProof;
use App\Models\VerificationEvent;
use Illuminate\Database\Eloquent\Collection;

class VerificationEventRecorder
{
    public function recordProofVerified(BindingProof $proof, array $context = []): VerificationEvent
    {
        return $this->record(
            vaultId: (string) $proof->vault_id,
            bindingProofId: (int) $proof->id,
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
        string $proofType,
        string $bindingKey,
        string $eventType,
        array $payload = [],
    ): VerificationEvent {
        return VerificationEvent::query()->create([
            'vault_id' => $vaultId,
            'binding_proof_id' => $bindingProofId,
            'proof_type' => $proofType,
            'binding_key' => $bindingKey,
            'event_type' => $eventType,
            'payload' => $payload === [] ? null : $payload,
            'occurred_at' => now(),
        ]);
    }
}
