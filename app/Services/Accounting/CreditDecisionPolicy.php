<?php

namespace App\Services\Accounting;

use App\Models\CreditDecision;
use App\Models\IdentityBinding;
use App\Models\VaultSettlementProof;

class CreditDecisionPolicy
{
    /**
     * @return array{status: string, reason: string|null, metadata: array<string, mixed>}
     */
    public function evaluate(VaultSettlementProof $proof, IdentityBinding $binding): array
    {
        if ($proof->status === VaultSettlementProof::STATUS_CREDITED) {
            return $this->rejected(CreditDecision::REASON_PROOF_ALREADY_CREDITED);
        }

        if ($proof->status !== VaultSettlementProof::STATUS_VERIFIED) {
            return $this->rejected(CreditDecision::REASON_PROOF_NOT_VERIFIED, [
                'proof_status' => $proof->status,
            ]);
        }

        if (! $binding->isActive()) {
            return $this->rejected(CreditDecision::REASON_BINDING_NOT_ACTIVE, [
                'binding_state' => $binding->verification_state,
            ]);
        }

        if (! $binding->isVerified()) {
            return $this->rejected(CreditDecision::REASON_BINDING_NOT_VERIFIED, [
                'binding_state' => $binding->verification_state,
            ]);
        }

        if ((string) $binding->vault_id !== (string) $proof->vault_id) {
            return $this->rejected(CreditDecision::REASON_BINDING_VAULT_MISMATCH);
        }

        if ($proof->identity_binding_id !== null
            && (int) $proof->identity_binding_id !== (int) $binding->id) {
            return $this->rejected(CreditDecision::REASON_BINDING_PROOF_MISMATCH);
        }

        return [
            'status' => CreditDecision::STATUS_APPROVED,
            'reason' => CreditDecision::REASON_ELIGIBLE,
            'metadata' => [
                'proof_kind' => $proof->proof_kind,
                'asset' => $proof->asset,
                'rail' => $proof->rail,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array{status: string, reason: string|null, metadata: array<string, mixed>}
     */
    private function rejected(string $reason, array $metadata = []): array
    {
        return [
            'status' => CreditDecision::STATUS_REJECTED,
            'reason' => $reason,
            'metadata' => $metadata,
        ];
    }
}
