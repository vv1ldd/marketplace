<?php

namespace App\Support;

use App\Models\VaultSettlementProof;

class SettlementProofLifecycle
{
    /**
     * PR 1 owns transitions through VERIFIED and FAILED only.
     * VERIFIED → CREDITED belongs to the accounting context.
     *
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        VaultSettlementProof::STATUS_PENDING => [
            VaultSettlementProof::STATUS_OBSERVED,
            VaultSettlementProof::STATUS_FAILED,
        ],
        VaultSettlementProof::STATUS_OBSERVED => [
            VaultSettlementProof::STATUS_VERIFIED,
            VaultSettlementProof::STATUS_FAILED,
        ],
    ];

    public static function canTransition(?string $from, string $to): bool
    {
        $from = strtolower(trim((string) $from));
        $to = strtolower(trim($to));

        if ($to === VaultSettlementProof::STATUS_CREDITED) {
            return false;
        }

        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /**
     * @return list<string>
     */
    public static function pr1Statuses(): array
    {
        return [
            VaultSettlementProof::STATUS_PENDING,
            VaultSettlementProof::STATUS_OBSERVED,
            VaultSettlementProof::STATUS_VERIFIED,
            VaultSettlementProof::STATUS_FAILED,
        ];
    }
}
