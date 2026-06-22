<?php

namespace App\Services\Settlement;

use App\Models\IdentityPaymentAccountingEvent;
use App\Models\IdentityPaymentDispute;
use App\Models\IdentityPaymentIntent;
use App\Models\ReconciliationRecord;
use App\Models\SettlementAttempt;
use App\Models\VaultIdentity;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class PaymentEventTimelineService
{
    public const CONTRACT_NAME = 'payment-intent-timeline';

    public const CONTRACT_VERSION = 'v1';

    /**
     * @return array<string, mixed>
     */
    public function build(IdentityPaymentIntent $intent): array
    {
        $intent->loadMissing([
            'settlementAttempts',
            'accountingEvent.reconciliationRecord',
            'dispute.compensationIntent',
            'reversalOf',
        ]);

        $events = array_merge(
            $this->intentEvents($intent),
            $this->policyDecisionEvents($intent),
            $this->settlementAttemptEvents($intent),
            $this->accountingEvents($intent),
            $this->reconciliationEvents($intent),
            $this->disputeEvents($intent),
            $this->compensationEvents($intent),
        );

        usort($events, fn (array $left, array $right) => $this->parseTime((string) $left['occurred_at'])
            <=> $this->parseTime((string) $right['occurred_at']));

        return [
            'contract' => [
                'name' => self::CONTRACT_NAME,
                'version' => self::CONTRACT_VERSION,
            ],
            'payment_intent_id' => $intent->intent_uuid,
            'events' => array_values($events),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forVaultParticipant(VaultIdentity $vault, string $intentUuid): array
    {
        if (! (bool) config('identity_payments.enabled', false)) {
            throw ValidationException::withMessages([
                'payment' => 'Identity payments are disabled.',
            ]);
        }

        $intent = IdentityPaymentIntent::query()
            ->where('intent_uuid', $intentUuid)
            ->first();

        if (! $intent instanceof IdentityPaymentIntent) {
            throw ValidationException::withMessages([
                'payment_intent_id' => 'Payment intent was not found.',
            ]);
        }

        $identityId = strtolower(trim((string) $vault->anchor_address));
        $participants = [
            strtolower((string) $intent->sender_identity_id),
            strtolower((string) $intent->receiver_identity_id),
        ];

        if (! in_array($identityId, $participants, true)) {
            abort(403);
        }

        return $this->build($intent);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function intentEvents(IdentityPaymentIntent $intent): array
    {
        $occurredAt = $intent->routed_at?->toJSON() ?? $intent->created_at?->toJSON();
        if ($occurredAt === null) {
            return [];
        }

        return [[
            'type' => 'intent_created',
            'occurred_at' => $occurredAt,
            'actor' => $this->actorLabel($intent->sender_alias, $intent->sender_identity_id),
            'source' => 'payment_intent',
            'evidence' => [
                'amount' => $intent->amount,
                'asset' => $intent->asset,
                'to_alias' => $intent->receiver_alias,
                'status' => $intent->status,
            ],
        ]];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function policyDecisionEvents(IdentityPaymentIntent $intent): array
    {
        $routingSnapshot = (array) ($intent->routing_metadata ?? []);
        $limitDecision = (array) data_get($intent->metadata, 'limit_decision', []);
        $feeDecision = (array) data_get($intent->metadata, 'fee_decision', []);
        $events = [];

        $routingAt = (string) ($routingSnapshot['snapshot_at'] ?? $intent->routed_at?->toJSON() ?? '');
        if ($routingAt !== '') {
            $events[] = [
                'type' => 'routing_decided',
                'occurred_at' => $routingAt,
                'actor' => 'system',
                'source' => 'routing_decision',
                'evidence' => [
                    'network' => data_get($routingSnapshot, 'selected.network', $intent->network),
                    'policy_version' => data_get($routingSnapshot, 'policy_version'),
                    'capability_policy_version' => data_get($routingSnapshot, 'capability_policy_version'),
                    'reason' => data_get($routingSnapshot, 'reason'),
                    'ruleset_hash' => data_get($routingSnapshot, 'decision_context.ruleset_hash'),
                ],
            ];
        }

        $limitAt = (string) ($limitDecision['evaluated_at'] ?? '');
        if ($limitAt !== '') {
            $events[] = [
                'type' => 'limit_decided',
                'occurred_at' => $limitAt,
                'actor' => 'system',
                'source' => 'limit_decision',
                'evidence' => [
                    'policy_key' => $limitDecision['policy_key'] ?? null,
                    'approved' => $limitDecision['approved'] ?? null,
                    'ruleset_hash' => $limitDecision['ruleset_hash'] ?? null,
                ],
            ];
        }

        $feeAt = (string) ($feeDecision['evaluated_at'] ?? '');
        if ($feeAt !== '') {
            $events[] = [
                'type' => 'fee_quoted',
                'occurred_at' => $feeAt,
                'actor' => (string) ($feeDecision['payer'] ?? 'system'),
                'source' => 'fee_decision',
                'evidence' => [
                    'policy_key' => $feeDecision['policy_key'] ?? null,
                    'fee_amount' => $feeDecision['fee_amount'] ?? null,
                    'asset' => $feeDecision['asset'] ?? $intent->asset,
                    'ruleset_hash' => $feeDecision['ruleset_hash'] ?? null,
                ],
            ];
        }

        return $events;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function settlementAttemptEvents(IdentityPaymentIntent $intent): array
    {
        $events = [];

        foreach ($intent->settlementAttempts as $attempt) {
            $events = array_merge($events, $this->settlementAttemptTimelineEvents($attempt));
        }

        $execution = data_get($intent->metadata, 'settlement_execution');
        if (is_array($execution) && ($execution['tx_reference'] ?? null)) {
            $confirmedAt = (string) ($execution['snapshot_at'] ?? $intent->executed_at?->toJSON() ?? '');
            if ($confirmedAt !== '' && ! $this->hasEventType($events, 'settlement_confirmed')) {
                $events[] = [
                    'type' => 'settlement_confirmed',
                    'occurred_at' => $confirmedAt,
                    'actor' => 'system',
                    'source' => 'settlement_execution',
                    'evidence' => [
                        'tx_reference' => $execution['tx_reference'] ?? null,
                        'network' => $execution['network'] ?? $intent->network,
                        'settlement_attempt_id' => $execution['settlement_attempt_id'] ?? null,
                    ],
                ];
            }
        }

        return $events;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function settlementAttemptTimelineEvents(SettlementAttempt $attempt): array
    {
        $events = [];

        if ($attempt->submitted_at !== null) {
            $events[] = [
                'type' => 'settlement_attempt_submitted',
                'occurred_at' => $attempt->submitted_at->toJSON(),
                'actor' => 'system',
                'source' => 'settlement_attempt',
                'evidence' => [
                    'attempt_no' => $attempt->attempt_no,
                    'network' => $attempt->network,
                    'status' => SettlementAttempt::STATUS_SUBMITTED,
                ],
            ];
        }

        if ($attempt->failed_at !== null) {
            $events[] = [
                'type' => 'settlement_attempt_failed',
                'occurred_at' => $attempt->failed_at->toJSON(),
                'actor' => 'system',
                'source' => 'settlement_attempt',
                'evidence' => [
                    'attempt_no' => $attempt->attempt_no,
                    'status' => SettlementAttempt::STATUS_FAILED,
                    'failure_reason' => $attempt->failure_reason,
                ],
            ];
        }

        if ($attempt->confirmed_at !== null) {
            $events[] = [
                'type' => 'settlement_confirmed',
                'occurred_at' => $attempt->confirmed_at->toJSON(),
                'actor' => 'system',
                'source' => 'settlement_attempt',
                'evidence' => [
                    'attempt_no' => $attempt->attempt_no,
                    'tx_reference' => $attempt->tx_reference,
                    'network' => $attempt->network,
                    'status' => SettlementAttempt::STATUS_CONFIRMED,
                ],
            ];
        }

        return $events;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function accountingEvents(IdentityPaymentIntent $intent): array
    {
        $accounting = $intent->accountingEvent;
        if (! $accounting instanceof IdentityPaymentAccountingEvent) {
            return [];
        }

        return [[
            'type' => 'accounting_recorded',
            'occurred_at' => $accounting->recorded_at?->toJSON() ?? $intent->executed_at?->toJSON() ?? now()->toJSON(),
            'actor' => 'system',
            'source' => 'accounting_event',
            'evidence' => [
                'narrative' => $accounting->narrative,
                'entries' => $this->formatAccountingEntries($accounting),
                'fee_decision' => data_get($accounting->metadata, 'fee_decision.policy_key'),
            ],
        ]];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function reconciliationEvents(IdentityPaymentIntent $intent): array
    {
        $accounting = $intent->accountingEvent;
        $reconciliation = $accounting?->reconciliationRecord;
        if (! $reconciliation instanceof ReconciliationRecord) {
            return [];
        }

        $occurredAt = $accounting->recorded_at?->toJSON()
            ?? $reconciliation->updated_at?->toJSON()
            ?? $intent->executed_at?->toJSON();

        if ($occurredAt === null) {
            return [];
        }

        $type = $reconciliation->status === ReconciliationRecord::STATUS_MATCHED
            ? 'reconciliation_matched'
            : 'reconciliation_recorded';

        return [[
            'type' => $type,
            'occurred_at' => $occurredAt,
            'actor' => 'system',
            'source' => 'reconciliation_record',
            'evidence' => [
                'status' => $reconciliation->status,
                'identity_from_match' => $reconciliation->identity_from_match,
                'identity_to_match' => $reconciliation->identity_to_match,
                'asset_match' => $reconciliation->asset_match,
                'amount_match' => $reconciliation->amount_match,
            ],
        ]];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function disputeEvents(IdentityPaymentIntent $intent): array
    {
        $dispute = $intent->dispute;
        if (! $dispute instanceof IdentityPaymentDispute) {
            return [];
        }

        $events = [];
        foreach ((array) ($dispute->lifecycle_log ?? []) as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $occurredAt = (string) ($entry['at'] ?? '');
            if ($occurredAt === '') {
                continue;
            }

            $eventName = (string) ($entry['event'] ?? '');
            if ($eventName === IdentityPaymentDispute::EVENT_RESOLVED) {
                continue;
            }

            $events[] = [
                'type' => $this->disputeEventType($eventName),
                'occurred_at' => $occurredAt,
                'actor' => ltrim((string) ($entry['actor_alias'] ?? ''), '@')
                    ?: (string) ($entry['actor_identity_id'] ?? 'system'),
                'source' => 'dispute_lifecycle',
                'evidence' => [
                    'dispute_id' => $dispute->dispute_uuid,
                    'reason' => $dispute->reason,
                    'metadata' => (array) ($entry['metadata'] ?? []),
                ],
            ];
        }

        $resolution = (array) ($dispute->resolution ?? []);
        $resolvedAt = (string) ($resolution['resolved_at'] ?? $dispute->resolved_at?->toJSON() ?? '');
        if ($resolvedAt !== '') {
            $events[] = [
                'type' => 'dispute_resolved',
                'occurred_at' => $resolvedAt,
                'actor' => (string) ($resolution['resolved_by'] ?? 'system'),
                'source' => 'dispute_resolution',
                'evidence' => [
                    'decision' => $resolution['decision'] ?? null,
                    'creates_compensation_intent' => $resolution['creates_compensation_intent'] ?? null,
                    'reason' => $resolution['reason'] ?? null,
                    'compensation_intent_id' => $resolution['compensation_intent_id'] ?? null,
                ],
            ];
        }

        return $events;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function compensationEvents(IdentityPaymentIntent $intent): array
    {
        $dispute = $intent->dispute;
        $compensation = $dispute?->compensationIntent;
        if (! $compensation instanceof IdentityPaymentIntent) {
            return [];
        }

        $occurredAt = $compensation->routed_at?->toJSON() ?? $compensation->created_at?->toJSON();
        if ($occurredAt === null) {
            return [];
        }

        return [[
            'type' => 'compensation_intent_created',
            'occurred_at' => $occurredAt,
            'actor' => $this->actorLabel($compensation->sender_alias, $compensation->sender_identity_id),
            'source' => 'compensation_intent',
            'evidence' => [
                'compensation_intent_id' => $compensation->intent_uuid,
                'reversal_of_intent_id' => $intent->intent_uuid,
                'amount' => $compensation->amount,
                'asset' => $compensation->asset,
                'status' => $compensation->status,
            ],
        ]];
    }

    /**
     * @return list<string>
     */
    private function formatAccountingEntries(IdentityPaymentAccountingEvent $accounting): array
    {
        $entries = (array) data_get($accounting->metadata, 'entries', []);
        $formatted = [];

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            if (isset($entry['account'])) {
                $formatted[] = sprintf('%s %s', $entry['account'], $entry['delta'] ?? '');
            } elseif (isset($entry['identity'])) {
                $formatted[] = sprintf('%s %s', $entry['identity'], $entry['delta'] ?? '');
            }
        }

        return $formatted;
    }

    private function disputeEventType(string $event): string
    {
        return match ($event) {
            IdentityPaymentDispute::EVENT_OPENED => 'dispute_opened',
            IdentityPaymentDispute::EVENT_EVIDENCE_REQUESTED => 'dispute_evidence_requested',
            IdentityPaymentDispute::EVENT_EVIDENCE_COLLECTED => 'dispute_evidence_collected',
            IdentityPaymentDispute::EVENT_REVIEWED => 'dispute_reviewed',
            IdentityPaymentDispute::EVENT_RESOLVED => 'dispute_resolved',
            default => 'dispute_event',
        };
    }

    /**
     * @param  list<array<string, mixed>>  $events
     */
    private function hasEventType(array $events, string $type): bool
    {
        foreach ($events as $event) {
            if (($event['type'] ?? null) === $type) {
                return true;
            }
        }

        return false;
    }

    private function actorLabel(?string $alias, ?string $identityId): string
    {
        $label = ltrim(trim((string) $alias), '@');

        return $label !== '' ? $label : strtolower(trim((string) $identityId));
    }

    private function parseTime(string $value): int
    {
        try {
            return Carbon::parse($value)->getTimestamp();
        } catch (\Throwable) {
            return 0;
        }
    }
}
