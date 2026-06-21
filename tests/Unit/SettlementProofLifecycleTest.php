<?php

namespace Tests\Unit;

use App\Models\VaultSettlementProof;
use App\Support\SettlementProofLifecycle;
use Tests\TestCase;

class SettlementProofLifecycleTest extends TestCase
{
    public function test_pr1_lifecycle_allows_pending_to_observed_to_verified(): void
    {
        $this->assertTrue(SettlementProofLifecycle::canTransition(
            VaultSettlementProof::STATUS_PENDING,
            VaultSettlementProof::STATUS_OBSERVED,
        ));
        $this->assertTrue(SettlementProofLifecycle::canTransition(
            VaultSettlementProof::STATUS_OBSERVED,
            VaultSettlementProof::STATUS_VERIFIED,
        ));
    }

    public function test_pr1_lifecycle_rejects_verified_to_credited_transition(): void
    {
        $this->assertFalse(SettlementProofLifecycle::canTransition(
            VaultSettlementProof::STATUS_VERIFIED,
            VaultSettlementProof::STATUS_CREDITED,
        ));
    }

    public function test_pr1_lifecycle_allows_failure_from_pending_or_observed(): void
    {
        $this->assertTrue(SettlementProofLifecycle::canTransition(
            VaultSettlementProof::STATUS_PENDING,
            VaultSettlementProof::STATUS_FAILED,
        ));
        $this->assertTrue(SettlementProofLifecycle::canTransition(
            VaultSettlementProof::STATUS_OBSERVED,
            VaultSettlementProof::STATUS_FAILED,
        ));
    }
}
