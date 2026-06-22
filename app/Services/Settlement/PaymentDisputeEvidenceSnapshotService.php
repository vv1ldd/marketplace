<?php

namespace App\Services\Settlement;

use App\Models\IdentityPaymentIntent;
use App\Models\SettlementAttempt;

class PaymentDisputeEvidenceSnapshotService
{
    /**
     * @return array<string, mixed>
     */
    public function build(IdentityPaymentIntent $intent): array
    {
        $intent->loadMissing([
            'accountingEvent.reconciliationRecord',
            'settlementAttempts',
        ]);

        $accounting = $intent->accountingEvent;
        $reconciliation = $accounting?->reconciliationRecord;
        $routingSnapshot = (array) ($intent->routing_metadata ?? []);
        $limitDecision = (array) data_get($intent->metadata, 'limit_decision', []);
        $feeDecision = (array) data_get($intent->metadata, 'fee_decision', []);
        $settlementExecution = data_get($intent->metadata, 'settlement_execution', []);
        $confirmedAttempt = $intent->settlementAttempts
            ->first(fn (SettlementAttempt $attempt) => $attempt->status === SettlementAttempt::STATUS_CONFIRMED);

        return [
            'snapshot_at' => now()->toJSON(),
            'intent_id' => $intent->intent_uuid,
            'intent_status' => $intent->status,
            'payment_intent' => [
                'from_identity' => $intent->sender_identity_id,
                'to_identity' => $intent->receiver_identity_id,
                'from_alias' => $intent->sender_alias,
                'to_alias' => $intent->receiver_alias,
                'asset' => $intent->asset,
                'amount' => $intent->amount,
                'executed_at' => $intent->executed_at?->toJSON(),
            ],
            'settlement_attempt_id' => $confirmedAttempt?->id,
            'settlement_execution' => $settlementExecution,
            'accounting_event_id' => $accounting?->id,
            'accounting_entries' => data_get($accounting?->metadata, 'entries', []),
            'reconciliation_record_id' => $reconciliation?->id,
            'reconciliation_status' => $reconciliation?->status,
            'routing_policy' => (string) data_get($routingSnapshot, 'policy_version', ''),
            'capability_policy' => (string) data_get($routingSnapshot, 'capability_policy_version', ''),
            'limit_policy' => (string) ($limitDecision['policy_key'] ?? ''),
            'fee_policy' => (string) ($feeDecision['policy_key'] ?? ''),
            'routing_decision' => [
                'policy_version' => data_get($routingSnapshot, 'policy_version'),
                'selected' => data_get($routingSnapshot, 'selected'),
                'decision_context' => data_get($routingSnapshot, 'decision_context'),
            ],
            'limit_decision' => $limitDecision,
            'fee_decision' => $feeDecision,
        ];
    }
}
