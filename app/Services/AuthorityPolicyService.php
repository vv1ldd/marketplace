<?php

namespace App\Services;

use App\Models\AuthorityVerdict;
use App\Models\MerchantDepositIntent;
use App\Models\SettlementProof;
use App\Models\ValidatorAttestation;

class AuthorityPolicyService
{
    public function evaluateProof(SettlementProof $proof): AuthorityVerdict
    {
        $proof->loadMissing(['intent', 'validatorAttestations']);
        $intent = $proof->intent;
        $legalEntity = $proof->legalEntity ?: $intent?->legalEntity;
        $policyKey = $this->policyKey($intent, $proof);
        $requiredQuorum = $this->requiredQuorum($intent, $proof);
        $acceptedCount = ValidatorAttestation::query()
            ->where('settlement_proof_id', $proof->id)
            ->where('status', ValidatorAttestation::STATUS_ACCEPTED)
            ->distinct('signer_identity')
            ->count('signer_identity');
        $hasRejectedEvidence = ValidatorAttestation::query()
            ->where('settlement_proof_id', $proof->id)
            ->where('attestation_type', ValidatorAttestation::TYPE_EVIDENCE_REJECTED)
            ->exists();

        [$decision, $status, $reason] = $this->decision(
            intent: $intent,
            proof: $proof,
            requiredQuorum: $requiredQuorum,
            acceptedCount: $acceptedCount,
            hasRejectedEvidence: $hasRejectedEvidence,
        );

        return AuthorityVerdict::query()->updateOrCreate(
            ['idempotency_key' => $this->verdictIdempotencyKey($proof)],
            [
                'merchant_deposit_intent_id' => $intent?->id,
                'settlement_proof_id' => $proof->id,
                'legal_entity_id' => $legalEntity?->id ?? $proof->legal_entity_id,
                'policy_key' => $policyKey,
                'status' => $status,
                'decision' => $decision,
                'reason_code' => $reason,
                'required_quorum' => $requiredQuorum,
                'accepted_attestations' => $acceptedCount,
                'metadata' => [
                    'rail' => $intent?->rail,
                    'proof_status' => $proof->status,
                    'intent_status' => $intent?->status,
                    'has_rejected_evidence' => $hasRejectedEvidence,
                ],
                'decided_at' => $decision !== AuthorityVerdict::DECISION_WAIT ? now() : null,
            ],
        );
    }

    private function decision(
        ?MerchantDepositIntent $intent,
        SettlementProof $proof,
        int $requiredQuorum,
        int $acceptedCount,
        bool $hasRejectedEvidence,
    ): array {
        if (! $intent) {
            return [AuthorityVerdict::DECISION_DENY, AuthorityVerdict::STATUS_DENIED, 'intent_missing'];
        }

        if (in_array($intent->status, [
            MerchantDepositIntent::STATUS_CANCELLED,
            MerchantDepositIntent::STATUS_EXPIRED,
            MerchantDepositIntent::STATUS_REJECTED,
        ], true)) {
            return [AuthorityVerdict::DECISION_DENY, AuthorityVerdict::STATUS_DENIED, 'intent_not_creditable'];
        }

        if ($proof->status === SettlementProof::STATUS_REJECTED || $hasRejectedEvidence) {
            return [AuthorityVerdict::DECISION_DENY, AuthorityVerdict::STATUS_DENIED, 'proof_rejected'];
        }

        if ((float) $proof->confirmed_amount <= 0 || (float) $proof->confirmed_amount > (float) $intent->amount) {
            return [AuthorityVerdict::DECISION_DENY, AuthorityVerdict::STATUS_DENIED, 'amount_out_of_bounds'];
        }

        if ($acceptedCount < $requiredQuorum) {
            return [AuthorityVerdict::DECISION_WAIT, AuthorityVerdict::STATUS_PENDING, 'quorum_pending'];
        }

        return [AuthorityVerdict::DECISION_ALLOW, AuthorityVerdict::STATUS_ALLOWED, 'quorum_satisfied'];
    }

    private function policyKey(?MerchantDepositIntent $intent, SettlementProof $proof): string
    {
        return 'merchant_settlement.'.($intent?->rail ?: $proof->source);
    }

    private function requiredQuorum(?MerchantDepositIntent $intent, SettlementProof $proof): int
    {
        if ($intent?->rail === MerchantDepositIntent::RAIL_MERCHANT_TRANSFER) {
            return 0;
        }

        // Bootstrap: authority is already modeled as quorum data, even while it is 1-of-N.
        return 1;
    }

    private function verdictIdempotencyKey(SettlementProof $proof): string
    {
        return hash('sha256', implode('|', [
            'authority-verdict',
            $proof->id,
            $proof->idempotency_key,
        ]));
    }
}
