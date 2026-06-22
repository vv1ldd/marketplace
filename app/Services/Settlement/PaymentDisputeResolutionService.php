<?php

namespace App\Services\Settlement;

use App\Models\IdentityPaymentDispute;
use App\Models\IdentityPaymentIntent;
use App\Models\User;
use App\Models\VaultIdentity;
use App\Services\VaultIdentityService;
use Illuminate\Validation\ValidationException;

class PaymentDisputeResolutionService
{
    public function __construct(
        private readonly IdentityPaymentService $payments,
        private readonly VaultIdentityService $vaultIdentities,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array{resolution: array<string, mixed>, compensation: array<string, mixed>|null}
     */
    public function resolve(
        IdentityPaymentDispute $dispute,
        IdentityPaymentIntent $intent,
        array $input,
        ?User $actor = null,
    ): array {
        $normalized = $this->normalizeResolutionInput($input, $actor);
        $reason = $normalized['reason'];
        $resolvedBy = $normalized['resolved_by'];
        $outcome = $normalized['outcome'];
        $decision = $normalized['decision'];
        $createsCompensationIntent = $normalized['creates_compensation_intent'];
        $executeCompensation = $normalized['execute_compensation'];

        $resolution = [
            'decision' => $decision,
            'creates_compensation_intent' => $createsCompensationIntent,
            'outcome' => $outcome,
            'resolved_by' => $resolvedBy,
            'reason' => $reason,
            'resolved_at' => now()->toJSON(),
            'compensation_intent_id' => null,
        ];

        $compensationPayload = null;

        if ($createsCompensationIntent) {
            $compensationPayload = $this->createCompensationIntent($intent, $executeCompensation);
            $resolution['compensation_intent_id'] = data_get($compensationPayload, 'payment_intent.id');
        }

        return [
            'resolution' => $resolution,
            'compensation' => $compensationPayload,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *     decision: string,
     *     creates_compensation_intent: bool,
     *     outcome: string,
     *     reason: string,
     *     resolved_by: string,
     *     execute_compensation: bool
     * }
     */
    private function normalizeResolutionInput(array $input, ?User $actor): array
    {
        $reason = trim((string) ($input['reason'] ?? ''));
        $resolvedBy = trim((string) ($input['resolved_by'] ?? ($actor?->username ?? 'system')));
        $executeCompensation = (bool) ($input['execute_compensation'] ?? false);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'Resolution reason is required.',
            ]);
        }

        if (isset($input['decision'])) {
            $decision = (string) $input['decision'];
            $createsCompensationIntent = (bool) ($input['creates_compensation_intent'] ?? false);

            if (! in_array($decision, [
                IdentityPaymentDispute::DECISION_APPROVED,
                IdentityPaymentDispute::DECISION_REJECTED,
                IdentityPaymentDispute::DECISION_NO_ACTION,
            ], true)) {
                throw ValidationException::withMessages([
                    'decision' => 'Unsupported dispute resolution decision.',
                ]);
            }

            $outcome = match ($decision) {
                IdentityPaymentDispute::DECISION_APPROVED => $createsCompensationIntent
                    ? IdentityPaymentDispute::OUTCOME_REFUND_APPROVED
                    : IdentityPaymentDispute::OUTCOME_NO_ACTION,
                IdentityPaymentDispute::DECISION_REJECTED => IdentityPaymentDispute::OUTCOME_REFUND_DENIED,
                default => IdentityPaymentDispute::OUTCOME_NO_ACTION,
            };

            return [
                'decision' => $decision,
                'creates_compensation_intent' => $createsCompensationIntent,
                'outcome' => $outcome,
                'reason' => $reason,
                'resolved_by' => $resolvedBy,
                'execute_compensation' => $executeCompensation,
            ];
        }

        $outcome = (string) ($input['outcome'] ?? '');
        if (! in_array($outcome, [
            IdentityPaymentDispute::OUTCOME_REFUND_APPROVED,
            IdentityPaymentDispute::OUTCOME_REFUND_DENIED,
            IdentityPaymentDispute::OUTCOME_NO_ACTION,
        ], true)) {
            throw ValidationException::withMessages([
                'outcome' => 'Unsupported dispute resolution outcome.',
            ]);
        }

        return [
            'decision' => match ($outcome) {
                IdentityPaymentDispute::OUTCOME_REFUND_APPROVED => IdentityPaymentDispute::DECISION_APPROVED,
                IdentityPaymentDispute::OUTCOME_REFUND_DENIED => IdentityPaymentDispute::DECISION_REJECTED,
                default => IdentityPaymentDispute::DECISION_NO_ACTION,
            },
            'creates_compensation_intent' => $outcome === IdentityPaymentDispute::OUTCOME_REFUND_APPROVED,
            'outcome' => $outcome,
            'reason' => $reason,
            'resolved_by' => $resolvedBy,
            'execute_compensation' => $executeCompensation,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function createCompensationIntent(IdentityPaymentIntent $intent, bool $execute): array
    {
        if ($intent->reversalIntent instanceof IdentityPaymentIntent) {
            throw ValidationException::withMessages([
                'dispute' => 'A compensation intent already exists for this payment.',
            ]);
        }

        $receiverUser = User::findByEntityL1Address($intent->receiver_identity_id);

        if (! $receiverUser instanceof User) {
            throw ValidationException::withMessages([
                'dispute' => 'Original payment recipient is unavailable for compensation routing.',
            ]);
        }

        $receiverVault = $this->vaultIdentities->resolveForStorefront(
            ['entity_l1_address' => $intent->receiver_identity_id],
            $receiverUser,
        );

        return $this->payments->create(
            ['entity_l1_address' => $intent->receiver_identity_id],
            $receiverVault,
            $receiverUser,
            [
                'reversal_of_intent_id' => $intent->intent_uuid,
                'reversal_reason' => 'dispute_compensation',
                'execute' => $execute,
            ],
        );
    }
}
