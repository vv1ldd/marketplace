<?php

namespace App\Services\SettlementAdapters\Concerns;

use App\Models\VaultIdentity;
use App\Models\VaultSettlementProof;
use Illuminate\Validation\ValidationException;

trait DeclinesSettlementProofVerification
{
    public function verifyProof(VaultIdentity $vault, array $input): VaultSettlementProof
    {
        throw ValidationException::withMessages([
            'proof' => 'Settlement proof verification is not implemented for this rail yet.',
        ]);
    }
}
