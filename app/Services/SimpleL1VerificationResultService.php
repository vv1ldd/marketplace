<?php

namespace App\Services;

use Illuminate\Support\Str;

class SimpleL1VerificationResultService
{
    private const DECISION_CODES = [
        'PROOF_ACCEPTED',
        'SIGNATURE_REJECTED',
        'KEY_BINDING_REJECTED',
        'HOST_REJECTED',
        'CLIENT_REJECTED',
        'NONCE_REPLAY',
        'PROOF_EXPIRED',
        'POLICY_REJECTED',
    ];

    /**
     * @param array<string, mixed> $proofResponse
     * @param array<string, string> $checks
     * @param array<int, string> $diagnosticSignals
     * @return array<string, mixed>
     */
    public function accepted(array $proofResponse, array $checks = [], array $diagnosticSignals = []): array
    {
        return $this->result($proofResponse, true, $checks + [
            'signature' => 'passed',
            'key_binding' => 'passed',
            'host_policy' => 'passed',
            'client_policy' => 'passed',
            'nonce' => 'passed',
            'expiration' => 'passed',
        ], 'PROOF_ACCEPTED', null, $diagnosticSignals);
    }

    /**
     * @param array<string, mixed> $proofResponse
     * @param array<string, string> $checks
     * @param array<int, string> $diagnosticSignals
     * @return array<string, mixed>
     */
    public function rejected(array $proofResponse, string $decision, array $checks = [], array $diagnosticSignals = []): array
    {
        $decision = strtoupper($decision);
        if (! in_array($decision, self::DECISION_CODES, true) || $decision === 'PROOF_ACCEPTED') {
            $decision = 'POLICY_REJECTED';
        }

        return $this->result($proofResponse, false, $checks + [
            'signature' => 'unknown',
            'key_binding' => 'unknown',
            'host_policy' => 'unknown',
            'client_policy' => 'unknown',
            'nonce' => 'unknown',
            'expiration' => 'unknown',
        ], $decision, $decision, $diagnosticSignals);
    }

    /**
     * @param array<string, mixed> $proofResponse
     * @param array<string, string> $checks
     * @param array<int, string> $diagnosticSignals
     * @return array<string, mixed>
     */
    private function result(
        array $proofResponse,
        bool $accepted,
        array $checks,
        string $decision,
        ?string $rejectionReason,
        array $diagnosticSignals,
    ): array {
        $proof = is_array(data_get($proofResponse, 'proof')) ? data_get($proofResponse, 'proof') : [];
        $signals = array_values(array_unique(array_merge(
            $this->routingDecisionDiagnosticSignals($proofResponse),
            $diagnosticSignals,
        )));

        return [
            'vro_version' => 'v0',
            'verification_result_id' => 'vro_'.(string) Str::uuid(),
            'accepted' => $accepted,
            'verification_steps' => $checks,
            'decision' => $decision,
            'rejection_reason' => $rejectionReason,
            'routing_decision_id' => data_get($proof, 'routingDecisionId'),
            'policy_version' => data_get($proof, 'policyVersion'),
            'entity_address' => data_get($proof, 'entityAddress'),
            'key_address' => data_get($proof, 'keyAddress'),
            'request_host' => data_get($proof, 'requestHost'),
            'client_id' => data_get($proof, 'clientId'),
            'diagnostic_signals' => $signals,
            'created_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param array<string, mixed> $proofResponse
     */
    private function routingDecisionStatus(array $proofResponse): string
    {
        $proof = is_array(data_get($proofResponse, 'proof')) ? data_get($proofResponse, 'proof') : [];
        $routingDecision = data_get($proof, 'routingDecision');

        if (! is_array($routingDecision)) {
            return 'not_present';
        }

        $matches = data_get($routingDecision, 'routing_decision_id') === data_get($proof, 'routingDecisionId')
            && data_get($routingDecision, 'policy_version') === data_get($proof, 'policyVersion')
            && data_get($routingDecision, 'selected_key') === data_get($proof, 'keyAddress');

        return $matches ? 'matched' : 'mismatched';
    }

    /**
     * @param array<string, mixed> $proofResponse
     * @return array<int, string>
     */
    private function routingDecisionDiagnosticSignals(array $proofResponse): array
    {
        $proof = is_array(data_get($proofResponse, 'proof')) ? data_get($proofResponse, 'proof') : [];
        $routingDecision = data_get($proof, 'routingDecision');

        if (! is_array($routingDecision)) {
            return [];
        }

        $signals = [$this->routingDecisionStatus($proofResponse) === 'matched'
            ? 'ROUTING_DECISION_MATCH'
            : 'ROUTING_DECISION_MISMATCH'];

        $eligibleKeys = data_get($routingDecision, 'eligible_keys', []);
        if (is_array($eligibleKeys)) {
            if (count($eligibleKeys) === 0) {
                $signals[] = 'NO_ELIGIBLE_KEYS';
            } elseif (count($eligibleKeys) > 1) {
                $signals[] = 'MULTIPLE_ELIGIBLE_KEYS';
            }
        }

        if (data_get($routingDecision, 'policy_version') !== data_get($proof, 'policyVersion')) {
            $signals[] = 'POLICY_VERSION_MISMATCH';
        }

        return $signals;
    }
}
