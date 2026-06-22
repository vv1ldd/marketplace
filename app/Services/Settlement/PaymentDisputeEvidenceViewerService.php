<?php

namespace App\Services\Settlement;

use App\Models\IdentityPaymentDispute;
use App\Models\IdentityPaymentIntent;

class PaymentDisputeEvidenceViewerService
{
    /**
     * @param  array<string, mixed>  $evidenceSnapshot
     * @return array<string, mixed>
     */
    public function build(array $evidenceSnapshot): array
    {
        $limitDecision = (array) ($evidenceSnapshot['limit_decision'] ?? []);
        $feeDecision = (array) ($evidenceSnapshot['fee_decision'] ?? []);
        $routingDecision = (array) ($evidenceSnapshot['routing_decision'] ?? []);
        $paymentIntent = (array) ($evidenceSnapshot['payment_intent'] ?? []);
        $settlementExecution = (array) ($evidenceSnapshot['settlement_execution'] ?? []);

        return [
            'identity' => [
                'sender' => [
                    'alias' => $paymentIntent['from_alias'] ?? null,
                    'identity_id' => $paymentIntent['from_identity'] ?? null,
                    'resolved' => ($paymentIntent['from_alias'] ?? '') !== '',
                ],
                'receiver' => [
                    'alias' => $paymentIntent['to_alias'] ?? null,
                    'identity_id' => $paymentIntent['to_identity'] ?? null,
                    'resolved' => ($paymentIntent['to_alias'] ?? '') !== '',
                ],
            ],
            'routing' => [
                'network' => data_get($routingDecision, 'selected.network'),
                'policy' => (string) ($evidenceSnapshot['routing_policy'] ?? ''),
                'capability_policy' => (string) ($evidenceSnapshot['capability_policy'] ?? ''),
            ],
            'limits' => [
                'policy' => (string) ($evidenceSnapshot['limit_policy'] ?? ''),
                'approved' => $limitDecision['approved'] ?? null,
            ],
            'fees' => [
                'policy' => (string) ($evidenceSnapshot['fee_policy'] ?? ''),
                'amount' => $feeDecision['fee_amount'] ?? null,
                'asset' => $feeDecision['asset'] ?? ($paymentIntent['asset'] ?? null),
            ],
            'settlement' => [
                'tx_reference' => $settlementExecution['tx_reference'] ?? null,
                'network' => $settlementExecution['network'] ?? data_get($routingDecision, 'selected.network'),
                'attempt_id' => $evidenceSnapshot['settlement_attempt_id'] ?? null,
            ],
            'reconciliation' => [
                'status' => $evidenceSnapshot['reconciliation_status'] ?? null,
                'record_id' => $evidenceSnapshot['reconciliation_record_id'] ?? null,
            ],
            'accounting' => [
                'event_id' => $evidenceSnapshot['accounting_event_id'] ?? null,
                'entries' => $evidenceSnapshot['accounting_entries'] ?? [],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $paymentContext
     * @param  array<string, mixed>  $evidenceSnapshot
     */
    public function matchesPaymentContext(array $paymentContext, array $evidenceSnapshot): bool
    {
        $paymentIntent = (array) ($evidenceSnapshot['payment_intent'] ?? []);

        return strtolower((string) ($paymentContext['intent_id'] ?? '')) === strtolower((string) ($evidenceSnapshot['intent_id'] ?? ''))
            && (string) ($paymentContext['amount'] ?? '') === (string) ($paymentIntent['amount'] ?? '')
            && strtoupper((string) ($paymentContext['asset'] ?? '')) === strtoupper((string) ($paymentIntent['asset'] ?? ''))
            && (string) ($paymentContext['fee_policy'] ?? '') === (string) ($evidenceSnapshot['fee_policy'] ?? '')
            && (string) ($paymentContext['limit_policy'] ?? '') === (string) ($evidenceSnapshot['limit_policy'] ?? '')
            && (string) ($paymentContext['routing_policy'] ?? '') === (string) ($evidenceSnapshot['routing_policy'] ?? '');
    }

    /**
     * @return array<string, mixed>
     */
    public function paymentContextFromIntentResponse(array $paymentPayload): array
    {
        $intent = (array) ($paymentPayload['payment_intent'] ?? $paymentPayload['intent'] ?? []);

        return [
            'intent_id' => $intent['id'] ?? null,
            'amount' => $intent['amount'] ?? null,
            'asset' => $intent['asset'] ?? null,
            'fee_policy' => data_get($paymentPayload, 'fee_decision.policy_key'),
            'limit_policy' => data_get($paymentPayload, 'limit_decision.policy_key'),
            'routing_policy' => data_get($paymentPayload, 'routing_decision.policy_version'),
        ];
    }
}
