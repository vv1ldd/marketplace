<?php

namespace App\Services;

use App\Models\BindingProof;
use App\Models\VaultIdentity;
use App\Models\VaultSettlementProof;

/**
 * @deprecated Prefer SettlementAdapter::verifyProof(). BindingProof is no longer the canonical execution object.
 */
class BindingProofVerificationService
{
    public function __construct(
        private readonly SettlementAdapterRegistry $settlementAdapters,
        private readonly SettlementProofFormatter $settlementProofFormatter,
    ) {}

    /**
     * @param array<string, mixed> $input
     */
    public function verifyUsdcTransfer(VaultIdentity $vault, array $input): VaultSettlementProof
    {
        $bindingKey = trim((string) ($input['binding_key'] ?? ''));

        return $this->settlementAdapters
            ->adapter($bindingKey)
            ->verifyProof($vault, $input);
    }

    /**
     * @return array<string, mixed>
     */
    public function formatProof(VaultSettlementProof|BindingProof $proof): array
    {
        if ($proof instanceof BindingProof) {
            return $this->formatLegacyBindingProof($proof);
        }

        return $this->settlementProofFormatter->format($proof);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatLegacyBindingProof(BindingProof $proof): array
    {
        $payload = (array) ($proof->proof_payload ?? []);

        return [
            'id' => $proof->id,
            'vault_id' => $proof->vault_id,
            'identity_binding_id' => $proof->identity_binding_id,
            'rail' => $proof->binding_key,
            'external_reference' => $proof->proof_reference,
            'proof_kind' => $proof->proof_type,
            'asset' => (string) ($payload['asset'] ?? 'USDC'),
            'amount' => (string) ($payload['amount'] ?? '0'),
            'recipient' => (string) ($payload['recipient'] ?? ''),
            'status' => VaultSettlementProof::STATUS_VERIFIED,
            'observed_at' => $proof->verified_at?->toJSON(),
            'verified_at' => $proof->verified_at?->toJSON(),
            'failed_at' => null,
            'evidence' => $payload,
            'metadata' => $proof->metadata ?? [],
            'transaction_hash' => $payload['transaction_hash'] ?? null,
            'proof_type' => $proof->proof_type,
            'binding_key' => $proof->binding_key,
            'verification_state' => $proof->verification_state,
            'proof_payload' => $payload,
            'proof_reference' => $proof->proof_reference,
        ];
    }
}
