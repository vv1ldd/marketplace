<?php

namespace App\Services\Accounting;

use App\Models\CreditDecision;
use App\Models\VaultIdentity;
use App\Models\VaultSettlementProof;

class ValueEntryService
{
    /**
     * @return array<string, mixed>
     */
    public function listForVault(VaultIdentity $vault, int $limit = 25): array
    {
        $limit = max(1, min($limit, 50));

        $proofs = VaultSettlementProof::query()
            ->where('vault_id', $vault->id)
            ->where('proof_kind', VaultSettlementProof::KIND_USDC_TRANSFER)
            ->whereIn('status', [
                VaultSettlementProof::STATUS_VERIFIED,
                VaultSettlementProof::STATUS_CREDITED,
            ])
            ->with('creditDecision')
            ->orderByDesc('verified_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return [
            'contract' => [
                'name' => 'value-entry-list',
                'version' => '1',
            ],
            'items' => $proofs
                ->map(fn (VaultSettlementProof $proof): array => $this->activityItem($proof, $proof->creditDecision))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function activityItem(VaultSettlementProof $proof, ?CreditDecision $decision = null): array
    {
        $entry = $this->formatEntry($proof, $decision);

        return [
            'activity_kind' => 'value_entry',
            'activity_direction' => 'incoming',
            'activity_at' => $proof->verified_at?->toJSON() ?? $proof->created_at?->toJSON(),
            'value_entry' => $entry,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formatEntry(VaultSettlementProof $proof, ?CreditDecision $decision = null): array
    {
        $rail = (string) $proof->rail;
        $decimals = (int) config('verification_proofs.usdc_transfer.'.$rail.'.decimals', 6);

        return [
            'proof_id' => $proof->id,
            'credit_decision_id' => $decision?->id,
            'asset' => (string) $proof->asset,
            'amount' => $this->formatAmount((string) $proof->amount, $decimals),
            'amount_raw' => (string) $proof->amount,
            'network' => $rail,
            'network_label' => (string) config('blockchain_networks.networks.'.$rail.'.label', ucfirst($rail)),
            'transaction_hash' => (string) $proof->evidenceValue('transaction_hash', ''),
            'sender' => (string) $proof->evidenceValue('sender', ''),
            'recipient' => (string) $proof->recipient,
            'proof_status' => (string) $proof->status,
            'credit_status' => $decision?->status,
            'credit_approved' => $decision?->isApproved() ?? false,
            'credit_reason' => $decision?->reason,
            'verified_at' => $proof->verified_at?->toJSON(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formatCreditDecision(CreditDecision $decision): array
    {
        return [
            'id' => $decision->id,
            'status' => $decision->status,
            'reason' => $decision->reason,
            'approved' => $decision->isApproved(),
            'metadata' => $decision->metadata ?? [],
        ];
    }

    private function formatAmount(string $raw, int $decimals): string
    {
        $normalized = ltrim(trim($raw), '0');
        if ($normalized === '') {
            return '0';
        }

        if ($decimals <= 0) {
            return $normalized;
        }

        $padded = str_pad($normalized, $decimals + 1, '0', STR_PAD_LEFT);
        $whole = substr($padded, 0, -$decimals) ?: '0';
        $fraction = rtrim(substr($padded, -$decimals), '0');

        return $fraction === '' ? $whole : $whole.'.'.$fraction;
    }
}
