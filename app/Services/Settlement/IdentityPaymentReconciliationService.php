<?php

namespace App\Services\Settlement;

use App\Models\IdentityPaymentAccountingEvent;
use App\Models\IdentityPaymentIntent;
use App\Models\ReconciliationRecord;
use App\Models\SettlementAttempt;

class IdentityPaymentReconciliationService
{
    public const CHECKER_VERSION = 'reconciliation:v1';

    public function reconcile(
        IdentityPaymentAccountingEvent $accounting,
        SettlementAttempt $attempt,
        IdentityPaymentIntent $intent,
    ): ReconciliationRecord {
        $existing = ReconciliationRecord::query()
            ->where('identity_payment_accounting_event_id', $accounting->id)
            ->first();

        if ($existing instanceof ReconciliationRecord) {
            return $existing;
        }

        $frozenRoute = (array) data_get($intent->routing_metadata, 'selected', []);
        $settlementReference = strtolower(trim((string) ($accounting->settlement_reference ?? '')));
        $attemptReference = strtolower(trim((string) ($attempt->tx_reference ?? '')));

        $identityFromMatch = strtolower((string) $accounting->sender_identity_id)
            === strtolower((string) $intent->sender_identity_id);
        $identityToMatch = strtolower((string) $accounting->receiver_identity_id)
            === strtolower((string) $intent->receiver_identity_id);
        $assetMatch = strtoupper((string) $accounting->asset) === strtoupper((string) $intent->asset);
        $amountMatch = (string) $accounting->amount === (string) $intent->amount
            && (int) $attempt->binding_from === (int) ($frozenRoute['sender_binding_id'] ?? $intent->sender_binding_id)
            && (int) $attempt->binding_to === (int) ($frozenRoute['receiver_binding_id'] ?? $intent->receiver_binding_id)
            && $attempt->status === SettlementAttempt::STATUS_CONFIRMED
            && $settlementReference !== ''
            && $settlementReference === $attemptReference;

        $allMatched = $identityFromMatch
            && $identityToMatch
            && $assetMatch
            && $amountMatch;

        return ReconciliationRecord::query()->create([
            'identity_payment_accounting_event_id' => $accounting->id,
            'settlement_attempt_id' => $attempt->id,
            'identity_from_match' => $identityFromMatch,
            'identity_to_match' => $identityToMatch,
            'asset_match' => $assetMatch,
            'amount_match' => $amountMatch,
            'status' => $allMatched ? ReconciliationRecord::STATUS_MATCHED : ReconciliationRecord::STATUS_MISMATCH,
            'evidence' => [
                'settlement_reference' => $accounting->settlement_reference,
                'checked_at' => now()->toJSON(),
                'checker_version' => self::CHECKER_VERSION,
                'intent_id' => $intent->intent_uuid,
                'attempt_no' => $attempt->attempt_no,
                'network' => $attempt->network,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function formatRecord(ReconciliationRecord $record): array
    {
        return [
            'id' => $record->id,
            'accounting_event_id' => $record->identity_payment_accounting_event_id,
            'settlement_execution_id' => $record->settlement_attempt_id,
            'identity_from_match' => $record->identity_from_match,
            'identity_to_match' => $record->identity_to_match,
            'asset_match' => $record->asset_match,
            'amount_match' => $record->amount_match,
            'status' => $record->status,
            'evidence' => $record->evidence ?? [],
        ];
    }
}
