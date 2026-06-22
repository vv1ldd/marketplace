<?php

namespace App\Services\Accounting;

use App\Contracts\AccountingConsumer as AccountingConsumerContract;
use App\Models\CreditDecision;
use App\Models\IdentityBinding;
use App\Models\VaultSettlementProof;
use Illuminate\Support\Facades\DB;

class AccountingConsumer implements AccountingConsumerContract
{
    public function __construct(
        private readonly CreditDecisionPolicy $policy,
    ) {}

    public function consume(
        VaultSettlementProof $proof,
        IdentityBinding $binding,
    ): CreditDecision {
        return DB::transaction(function () use ($proof, $binding): CreditDecision {
            $existing = CreditDecision::query()
                ->where('vault_settlement_proof_id', $proof->id)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof CreditDecision) {
                return $existing;
            }

            $evaluation = $this->policy->evaluate($proof->refresh(), $binding);

            return CreditDecision::query()->create([
                'vault_settlement_proof_id' => $proof->id,
                'identity_binding_id' => $binding->id,
                'status' => $evaluation['status'],
                'reason' => $evaluation['reason'],
                'metadata' => $evaluation['metadata'] ?? [],
            ]);
        });
    }
}
