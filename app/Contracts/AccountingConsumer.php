<?php

namespace App\Contracts;

use App\Models\CreditDecision;
use App\Models\IdentityBinding;
use App\Models\VaultSettlementProof;

interface AccountingConsumer
{
    /**
     * Evaluate whether a verified settlement proof may proceed toward accounting.
     * Decision boundary only — no accounting events, SL1 mutation, or CREDITED transition.
     */
    public function consume(
        VaultSettlementProof $proof,
        IdentityBinding $binding,
    ): CreditDecision;
}
