<?php

namespace Tests\Unit;

use App\Contracts\AccountingConsumer;
use App\Models\CreditDecision;
use App\Models\IdentityBinding;
use App\Models\SettlementAuditEvent;
use App\Models\User;
use App\Models\VaultIdentity;
use App\Models\VaultSettlementProof;
use App\Services\VaultIdentityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountingConsumerTest extends TestCase
{
    use RefreshDatabase;

    public function test_consume_creates_approved_decision_for_verified_proof_without_downstream_effects(): void
    {
        [$proof, $binding] = $this->verifiedProofFixture();
        $auditEventsBefore = SettlementAuditEvent::query()->count();

        $decision = app(AccountingConsumer::class)->consume($proof, $binding);

        $this->assertTrue($decision->isApproved());
        $this->assertSame(CreditDecision::REASON_ELIGIBLE, $decision->reason);
        $this->assertDatabaseHas('credit_decisions', [
            'id' => $decision->id,
            'vault_settlement_proof_id' => $proof->id,
            'identity_binding_id' => $binding->id,
            'status' => CreditDecision::STATUS_APPROVED,
        ]);
        $this->assertSame(
            VaultSettlementProof::STATUS_VERIFIED,
            $proof->refresh()->status,
        );
        $this->assertSame($auditEventsBefore, SettlementAuditEvent::query()->count());
        $this->assertFalse(Schema::hasTable('accounting_events'));
    }

    public function test_repeat_consume_returns_same_decision_without_duplicates(): void
    {
        [$proof, $binding] = $this->verifiedProofFixture();
        $consumer = app(AccountingConsumer::class);

        $first = $consumer->consume($proof, $binding);
        $second = $consumer->consume($proof->refresh(), $binding->refresh());

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, CreditDecision::query()->count());
        $this->assertSame(
            VaultSettlementProof::STATUS_VERIFIED,
            $proof->refresh()->status,
        );
    }

    public function test_consume_rejects_non_verified_proof_and_persists_rejected_decision(): void
    {
        [$proof, $binding] = $this->verifiedProofFixture(
            proofStatus: VaultSettlementProof::STATUS_FAILED,
        );

        $decision = app(AccountingConsumer::class)->consume($proof, $binding);

        $this->assertTrue($decision->isRejected());
        $this->assertSame(CreditDecision::REASON_PROOF_NOT_VERIFIED, $decision->reason);
        $this->assertSame(
            VaultSettlementProof::STATUS_FAILED,
            $proof->refresh()->status,
        );
    }

    public function test_consume_rejects_binding_vault_mismatch(): void
    {
        [$proof, $binding] = $this->verifiedProofFixture();
        $otherVault = VaultIdentity::query()->create([
            'id' => (string) Str::uuid(),
            'anchor_address' => 'sl1e_'.str_repeat('2', 39),
            'vault_kind' => VaultIdentity::KIND_PERSONAL,
        ]);
        $binding->forceFill(['vault_id' => $otherVault->id])->save();

        $decision = app(AccountingConsumer::class)->consume($proof->refresh(), $binding->refresh());

        $this->assertTrue($decision->isRejected());
        $this->assertSame(CreditDecision::REASON_BINDING_VAULT_MISMATCH, $decision->reason);
    }

    public function test_consume_alone_does_not_create_accounting_event_sl1_mutation_or_credited_transition(): void
    {
        [$proof, $binding] = $this->verifiedProofFixture();
        $consumer = app(AccountingConsumer::class);

        $decision = $consumer->consume($proof, $binding);

        $this->assertTrue($decision->isApproved());
        $this->assertNotSame(
            VaultSettlementProof::STATUS_CREDITED,
            $proof->refresh()->status,
        );
        $this->assertFalse(Schema::hasTable('accounting_events'));
    }

    /**
     * @return array{0: VaultSettlementProof, 1: IdentityBinding}
     */
    private function verifiedProofFixture(
        string $proofStatus = VaultSettlementProof::STATUS_VERIFIED,
    ): array {
        $entityAddress = 'sl1e_'.str_repeat('a', 39);
        $user = User::factory()->create(['entity_l1_address' => $entityAddress]);
        $vault = app(VaultIdentityService::class)->resolveForStorefront([
            'entity_l1_address' => $entityAddress,
        ], $user);

        $recipient = '0xabcdefabcdefabcdefabcdefabcdefabcdefabcd';
        $binding = IdentityBinding::query()->create([
            'vault_id' => $vault->id,
            'binding_type' => IdentityBinding::TYPE_WALLET,
            'binding_key' => 'polygon',
            'binding_value_original' => $recipient,
            'binding_value_normalized' => strtolower($recipient),
            'verification_state' => IdentityBinding::STATE_VERIFIED,
            'verification_method' => IdentityBinding::METHOD_SIGNATURE,
            'bound_at' => now(),
            'verified_at' => now(),
        ]);

        $proof = VaultSettlementProof::query()->create([
            'vault_id' => $vault->id,
            'identity_binding_id' => $binding->id,
            'rail' => 'polygon',
            'external_reference' => VaultSettlementProof::externalReferenceFor(
                VaultSettlementProof::KIND_USDC_TRANSFER,
                '0x'.str_repeat('f', 64),
            ),
            'proof_kind' => VaultSettlementProof::KIND_USDC_TRANSFER,
            'asset' => 'USDC',
            'amount' => '10000000',
            'recipient' => strtolower($recipient),
            'status' => $proofStatus,
            'verified_at' => $proofStatus === VaultSettlementProof::STATUS_VERIFIED ? now() : null,
            'evidence' => [
                'transaction_hash' => '0x'.str_repeat('f', 64),
                'asset' => 'USDC',
            ],
        ]);

        return [$proof->refresh(), $binding->refresh()];
    }
}
