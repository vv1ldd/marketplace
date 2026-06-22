<?php

namespace Tests\Unit;

use App\Models\IdentityPaymentAccountingEvent;
use App\Models\IdentityPaymentIntent;
use App\Models\ReconciliationRecord;
use App\Models\SettlementAttempt;
use App\Services\Settlement\IdentityPaymentReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class IdentityPaymentReconciliationServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{intent: IdentityPaymentIntent, attempt: SettlementAttempt, accounting: IdentityPaymentAccountingEvent}
     */
    private function seedAlignedPayment(string $amount = '10'): array
    {
        $vaultId = (string) \Illuminate\Support\Str::uuid();
        \App\Models\VaultIdentity::query()->create([
            'id' => $vaultId,
            'anchor_address' => 'sl1e_'.str_repeat('f', 39),
            'vault_kind' => \App\Models\VaultIdentity::KIND_PERSONAL,
        ]);

        $senderBindingId = $this->createBinding($vaultId, 'polygon', '0x'.str_repeat('1', 40));
        $receiverBindingId = $this->createBinding($vaultId, 'polygon', '0x'.str_repeat('2', 40));

        $intent = IdentityPaymentIntent::query()->create([
            'intent_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'status' => IdentityPaymentIntent::STATUS_EXECUTED,
            'sender_vault_id' => $vaultId,
            'sender_identity_id' => 'sl1e_'.str_repeat('a', 39),
            'sender_alias' => '@selim',
            'receiver_identity_id' => 'sl1e_'.str_repeat('b', 39),
            'receiver_alias' => '@alice',
            'asset' => 'USDC',
            'amount' => $amount,
            'amount_wei' => '10000000',
            'sender_binding_id' => $senderBindingId,
            'receiver_binding_id' => $receiverBindingId,
            'network' => 'polygon',
            'routing_policy' => 'shared_managed_network',
            'routing_metadata' => [
                'snapshot_at' => '2026-06-22T00:00:00.000000Z',
                'selected' => [
                    'network' => 'polygon',
                    'sender_binding_id' => $senderBindingId,
                    'receiver_binding_id' => $receiverBindingId,
                ],
            ],
            'routed_at' => now(),
            'executed_at' => now(),
            'settlement_reference' => '0xabc123',
        ]);

        $attempt = SettlementAttempt::query()->create([
            'identity_payment_intent_id' => $intent->id,
            'attempt_no' => 1,
            'routing_snapshot_ref' => '2026-06-22T00:00:00.000000Z',
            'network' => 'polygon',
            'binding_from' => $senderBindingId,
            'binding_to' => $receiverBindingId,
            'status' => SettlementAttempt::STATUS_CONFIRMED,
            'tx_reference' => '0xabc123',
            'confirmed_at' => now(),
        ]);

        $accounting = IdentityPaymentAccountingEvent::query()->create([
            'identity_payment_intent_id' => $intent->id,
            'sender_identity_id' => $intent->sender_identity_id,
            'receiver_identity_id' => $intent->receiver_identity_id,
            'sender_binding_id' => $senderBindingId,
            'receiver_binding_id' => $receiverBindingId,
            'asset' => 'USDC',
            'amount' => $amount,
            'network' => 'polygon',
            'narrative' => 'selim → alice : '.$amount.' USDC',
            'settlement_reference' => '0xabc123',
            'recorded_at' => now(),
        ]);

        return compact('intent', 'attempt', 'accounting');
    }

    private function createBinding(string $vaultId, string $network, string $address): int
    {
        return (int) \App\Models\IdentityBinding::query()->create([
            'vault_id' => $vaultId,
            'binding_type' => \App\Models\IdentityBinding::TYPE_WALLET,
            'binding_key' => $network,
            'binding_source' => \App\Models\IdentityBinding::SOURCE_MANAGED,
            'binding_value_original' => $address,
            'binding_value_normalized' => strtolower($address),
            'verification_state' => \App\Models\IdentityBinding::STATE_VERIFIED,
            'verification_method' => \App\Models\IdentityBinding::METHOD_VAULT_KEY,
            'metadata' => ['protocol' => 'evm'],
            'bound_at' => now(),
            'verified_at' => now(),
        ])->id;
    }

    #[Test]
    public function reconciliation_marks_aligned_accounting_and_settlement_as_matched(): void
    {
        ['intent' => $intent, 'attempt' => $attempt, 'accounting' => $accounting] = $this->seedAlignedPayment();

        $record = app(IdentityPaymentReconciliationService::class)->reconcile($accounting, $attempt, $intent);

        $this->assertSame(ReconciliationRecord::STATUS_MATCHED, $record->status);
        $this->assertTrue($record->identity_from_match);
        $this->assertTrue($record->identity_to_match);
        $this->assertTrue($record->asset_match);
        $this->assertTrue($record->amount_match);
        $this->assertSame(IdentityPaymentReconciliationService::CHECKER_VERSION, data_get($record->evidence, 'checker_version'));
    }

    #[Test]
    public function reconciliation_marks_misaligned_amount_as_mismatch(): void
    {
        ['intent' => $intent, 'attempt' => $attempt, 'accounting' => $accounting] = $this->seedAlignedPayment('10');
        $accounting->forceFill(['amount' => '20'])->save();

        $record = app(IdentityPaymentReconciliationService::class)->reconcile($accounting, $attempt, $intent->fresh());

        $this->assertSame(ReconciliationRecord::STATUS_MISMATCH, $record->status);
        $this->assertFalse($record->amount_match);
    }
}
