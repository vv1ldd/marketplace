<?php

namespace App\Services;

use App\Models\VaultSettlementProof;

class SettlementProofFormatter
{
    /**
     * @return array<string, mixed>
     */
    public function format(VaultSettlementProof $proof): array
    {
        $evidence = (array) ($proof->evidence ?? []);
        $transactionHash = (string) ($evidence['transaction_hash'] ?? '');

        return [
            'id' => $proof->id,
            'vault_id' => $proof->vault_id,
            'identity_binding_id' => $proof->identity_binding_id,
            'rail' => $proof->rail,
            'external_reference' => $proof->external_reference,
            'proof_kind' => $proof->proof_kind,
            'asset' => $proof->asset,
            'amount' => $proof->amount,
            'recipient' => $proof->recipient,
            'status' => $proof->status,
            'observed_at' => $proof->observed_at?->toJSON(),
            'verified_at' => $proof->verified_at?->toJSON(),
            'failed_at' => $proof->failed_at?->toJSON(),
            'evidence' => $evidence,
            'metadata' => $proof->metadata ?? [],
            'transaction_hash' => $transactionHash !== '' ? $transactionHash : null,
            'proof_type' => $proof->proof_kind,
            'binding_key' => $proof->rail,
            'verification_state' => $this->legacyVerificationState($proof),
            'proof_payload' => $evidence,
            'proof_reference' => $proof->external_reference,
        ];
    }

    private function legacyVerificationState(VaultSettlementProof $proof): string
    {
        return match ($proof->status) {
            VaultSettlementProof::STATUS_VERIFIED,
            VaultSettlementProof::STATUS_CREDITED => 'verified',
            VaultSettlementProof::STATUS_FAILED => 'failed',
            default => $proof->status,
        };
    }
}
