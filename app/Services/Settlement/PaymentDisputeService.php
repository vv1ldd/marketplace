<?php

namespace App\Services\Settlement;

use App\Models\IdentityPaymentDispute;
use App\Models\IdentityPaymentIntent;
use App\Models\User;
use App\Models\VaultIdentity;
use App\Services\VaultIdentityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentDisputeService
{
    public const CONTRACT_NAME = 'payment-dispute';

    public const CONTRACT_VERSION = 'v1';

    public function __construct(
        private readonly PaymentDisputeEvidenceSnapshotService $evidenceSnapshots,
        private readonly PaymentDisputeRegistry $registry,
        private readonly PaymentDisputeResolutionService $resolution,
        private readonly PaymentDisputeEvidenceViewerService $evidenceViewer,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function open(
        VaultIdentity $vault,
        User $user,
        string $intentUuid,
        string $reason,
        bool $evidenceRequired = true,
    ): array {
        $this->assertEnabled();

        $intent = $this->loadDisputableIntent($intentUuid);
        $identityId = strtolower(trim((string) $vault->anchor_address));
        $this->assertParticipant($intent, $identityId);

        if (IdentityPaymentDispute::query()->where('identity_payment_intent_id', $intent->id)->exists()) {
            throw ValidationException::withMessages([
                'dispute' => 'A dispute already exists for this payment intent.',
            ]);
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'Dispute reason is required.',
            ]);
        }

        $evidenceSnapshot = $this->evidenceSnapshots->build($intent);
        $openedAt = now();

        $dispute = IdentityPaymentDispute::query()->create([
            'dispute_uuid' => (string) Str::uuid(),
            'identity_payment_intent_id' => $intent->id,
            'opened_by_identity_id' => $identityId,
            'opened_by_alias' => $this->displayAlias($user),
            'reason' => $reason,
            'status' => IdentityPaymentDispute::STATUS_OPENED,
            'evidence_required' => $evidenceRequired,
            'evidence_snapshot' => $evidenceSnapshot,
            'lifecycle_log' => [
                $this->lifecycleEvent(IdentityPaymentDispute::EVENT_OPENED, $user, [
                    'reason' => $reason,
                    'evidence_required' => $evidenceRequired,
                ]),
            ],
            'opened_at' => $openedAt,
        ]);

        return $this->formatResponse($dispute->refresh()->load(['paymentIntent', 'compensationIntent']));
    }

    /**
     * @return array<string, mixed>
     */
    public function requestEvidence(VaultIdentity $vault, User $user, string $disputeUuid): array
    {
        return $this->transition($vault, $user, $disputeUuid, IdentityPaymentDispute::STATUS_EVIDENCE_REQUESTED);
    }

    /**
     * @return array<string, mixed>
     */
    public function collectEvidence(VaultIdentity $vault, User $user, string $disputeUuid): array
    {
        return $this->transition($vault, $user, $disputeUuid, IdentityPaymentDispute::STATUS_EVIDENCE_COLLECTED);
    }

    /**
     * @return array<string, mixed>
     */
    public function review(VaultIdentity $vault, User $user, string $disputeUuid): array
    {
        return $this->transition($vault, $user, $disputeUuid, IdentityPaymentDispute::STATUS_REVIEWED);
    }

    /**
     * @return array<string, mixed>
     */
    private function transitionDispute(
        IdentityPaymentDispute $dispute,
        User $user,
        string $toStatus,
    ): array {
        if ($dispute->status === IdentityPaymentDispute::STATUS_RESOLVED) {
            throw ValidationException::withMessages([
                'dispute' => 'Resolved disputes cannot be transitioned.',
            ]);
        }

        try {
            $this->registry->assertTransition($dispute->status, $toStatus);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'dispute' => $exception->getMessage(),
            ]);
        }

        $lifecycleLog = (array) ($dispute->lifecycle_log ?? []);
        $lifecycleLog[] = $this->lifecycleEvent($this->registry->eventForStatus($toStatus), $user);

        $dispute->forceFill([
            'status' => $toStatus,
            'lifecycle_log' => $lifecycleLog,
        ])->save();

        return $this->formatResponse($dispute->refresh()->load(['paymentIntent', 'compensationIntent']));
    }

    private function loadDisputeForOps(string $disputeUuid): IdentityPaymentDispute
    {
        $dispute = IdentityPaymentDispute::query()
            ->where('dispute_uuid', $disputeUuid)
            ->with(['paymentIntent', 'compensationIntent'])
            ->first();

        if (! $dispute instanceof IdentityPaymentDispute) {
            throw ValidationException::withMessages([
                'dispute' => 'Dispute was not found.',
            ]);
        }

        return $dispute;
    }

    private function transition(
        VaultIdentity $vault,
        User $user,
        string $disputeUuid,
        string $toStatus,
    ): array {
        $this->assertEnabled();

        $dispute = $this->loadDisputeForVault($vault, $disputeUuid);

        return $this->transitionDispute($dispute, $user, $toStatus);
    }

    /**
     * @param  array{
     *     outcome?: string,
     *     decision?: string,
     *     creates_compensation_intent?: bool,
     *     reason: string,
     *     resolved_by?: string,
     *     execute_compensation?: bool
     * }  $input
     * @return array<string, mixed>
     */
    public function resolve(
        VaultIdentity $vault,
        User $user,
        string $disputeUuid,
        array $input,
    ): array {
        $this->assertEnabled();

        $dispute = $this->loadDisputeForVault($vault, $disputeUuid);
        $intent = $dispute->paymentIntent;

        if (! $intent instanceof IdentityPaymentIntent) {
            throw ValidationException::withMessages([
                'dispute' => 'Dispute payment intent is unavailable.',
            ]);
        }

        if ($dispute->status !== IdentityPaymentDispute::STATUS_REVIEWED) {
            throw ValidationException::withMessages([
                'dispute' => 'Only reviewed disputes can be resolved.',
            ]);
        }

        $result = DB::transaction(function () use ($dispute, $intent, $input, $user): array {
            $resolved = $this->resolution->resolve($dispute, $intent, $input, $user);
            $resolution = $resolved['resolution'];
            $compensation = $resolved['compensation'];

            $compensationIntentId = null;
            if (is_array($compensation)) {
                $compensationIntent = IdentityPaymentIntent::query()
                    ->where('intent_uuid', data_get($compensation, 'payment_intent.id'))
                    ->first();
                $compensationIntentId = $compensationIntent?->id;
            }

            $lifecycleLog = (array) ($dispute->lifecycle_log ?? []);
            $lifecycleLog[] = $this->lifecycleEvent(IdentityPaymentDispute::EVENT_RESOLVED, $user, [
                'decision' => $resolution['decision'] ?? null,
                'outcome' => $resolution['outcome'],
                'reason' => $resolution['reason'],
            ]);

            $dispute->forceFill([
                'status' => IdentityPaymentDispute::STATUS_RESOLVED,
                'resolution' => $resolution,
                'compensation_intent_id' => $compensationIntentId,
                'lifecycle_log' => $lifecycleLog,
                'resolved_at' => now(),
            ])->save();

            return [
                'dispute' => $dispute->refresh()->load(['paymentIntent', 'compensationIntent']),
                'compensation' => $compensation,
            ];
        });

        $response = $this->formatResponse($result['dispute']);
        if (is_array($result['compensation'])) {
            $response['compensation_intent'] = $result['compensation'];
        }

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    public function show(VaultIdentity $vault, string $disputeUuid): array
    {
        $this->assertEnabled();

        return $this->formatResponse(
            $this->loadDisputeForVault($vault, $disputeUuid)
                ->load(['paymentIntent', 'compensationIntent']),
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function disputeForPaymentIntent(VaultIdentity $vault, string $intentUuid): ?array
    {
        $this->assertEnabled();

        $intent = IdentityPaymentIntent::query()
            ->where('intent_uuid', $intentUuid)
            ->first();

        if (! $intent instanceof IdentityPaymentIntent) {
            throw ValidationException::withMessages([
                'payment_intent_id' => 'Payment intent was not found.',
            ]);
        }

        $this->assertParticipant($intent, strtolower(trim((string) $vault->anchor_address)));

        $dispute = IdentityPaymentDispute::query()
            ->where('identity_payment_intent_id', $intent->id)
            ->with(['paymentIntent', 'compensationIntent'])
            ->first();

        return $dispute instanceof IdentityPaymentDispute
            ? $this->formatResponse($dispute)
            : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function listForOps(int $limit = 25, ?string $status = null): array
    {
        $this->assertEnabled();

        $limit = max(1, min($limit, 50));

        $disputes = IdentityPaymentDispute::query()
            ->with(['paymentIntent', 'compensationIntent'])
            ->when($status !== null && $status !== '', fn ($query) => $query->where('status', $status))
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return [
            'contract' => [
                'name' => self::CONTRACT_NAME.'-list',
                'version' => self::CONTRACT_VERSION,
            ],
            'items' => $disputes
                ->map(fn (IdentityPaymentDispute $dispute) => $this->formatOpsResponse($dispute))
                ->values()
                ->all(),
        ];
    }

    public function showForOps(string $disputeUuid): array
    {
        $this->assertEnabled();

        $dispute = IdentityPaymentDispute::query()
            ->where('dispute_uuid', $disputeUuid)
            ->with(['paymentIntent', 'compensationIntent'])
            ->first();

        if (! $dispute instanceof IdentityPaymentDispute) {
            throw ValidationException::withMessages([
                'dispute' => 'Dispute was not found.',
            ]);
        }

        return $this->formatOpsResponse($dispute);
    }

    /**
     * @return array<string, mixed>
     */
    public function transitionForOps(User $actor, string $disputeUuid, string $toStatus): array
    {
        $this->assertEnabled();

        $dispute = $this->loadDisputeForOps($disputeUuid);
        $this->transitionDispute($dispute, $actor, $toStatus);

        return $this->formatOpsResponse(
            $dispute->refresh()->load(['paymentIntent', 'compensationIntent']),
        );
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function resolveForOps(User $actor, string $disputeUuid, array $input): array
    {
        $this->assertEnabled();

        $dispute = $this->loadDisputeForOps($disputeUuid);
        $intent = $dispute->paymentIntent;

        if (! $intent instanceof IdentityPaymentIntent) {
            throw ValidationException::withMessages([
                'dispute' => 'Dispute payment intent is unavailable.',
            ]);
        }

        if ($dispute->status !== IdentityPaymentDispute::STATUS_REVIEWED) {
            throw ValidationException::withMessages([
                'dispute' => 'Only reviewed disputes can be resolved.',
            ]);
        }

        $result = DB::transaction(function () use ($dispute, $intent, $input, $actor): array {
            $resolved = $this->resolution->resolve($dispute, $intent, $input, $actor);
            $resolution = $resolved['resolution'];
            $compensation = $resolved['compensation'];

            $compensationIntentId = null;
            if (is_array($compensation)) {
                $compensationIntent = IdentityPaymentIntent::query()
                    ->where('intent_uuid', data_get($compensation, 'payment_intent.id'))
                    ->first();
                $compensationIntentId = $compensationIntent?->id;
            }

            $lifecycleLog = (array) ($dispute->lifecycle_log ?? []);
            $lifecycleLog[] = $this->lifecycleEvent(IdentityPaymentDispute::EVENT_RESOLVED, $actor, [
                'decision' => $resolution['decision'] ?? null,
                'outcome' => $resolution['outcome'],
                'reason' => $resolution['reason'],
            ]);

            $dispute->forceFill([
                'status' => IdentityPaymentDispute::STATUS_RESOLVED,
                'resolution' => $resolution,
                'compensation_intent_id' => $compensationIntentId,
                'lifecycle_log' => $lifecycleLog,
                'resolved_at' => now(),
            ])->save();

            return [
                'dispute' => $dispute->refresh()->load(['paymentIntent', 'compensationIntent']),
                'compensation' => $compensation,
            ];
        });

        $response = $this->formatOpsResponse($result['dispute']);
        if (is_array($result['compensation'])) {
            $response['compensation_intent'] = $result['compensation'];
        }

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    public function formatOpsResponse(IdentityPaymentDispute $dispute): array
    {
        $response = $this->formatResponse($dispute);
        $evidence = (array) ($dispute->evidence_snapshot ?? []);
        $intent = $dispute->paymentIntent;

        $response['evidence_viewer'] = $this->evidenceViewer->build($evidence);
        $response['payment'] = $intent instanceof IdentityPaymentIntent
            ? [
                'from_alias' => $intent->sender_alias,
                'to_alias' => $intent->receiver_alias,
                'amount' => $intent->amount,
                'asset' => $intent->asset,
                'status' => $intent->status,
            ]
            : null;

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    public function formatResponse(IdentityPaymentDispute $dispute): array
    {
        $dispute->loadMissing(['paymentIntent', 'compensationIntent']);
        $intent = $dispute->paymentIntent;
        $compensation = $dispute->compensationIntent;

        return [
            'contract' => [
                'name' => self::CONTRACT_NAME,
                'version' => self::CONTRACT_VERSION,
            ],
            'dispute' => [
                'id' => $dispute->dispute_uuid,
                'payment_intent_id' => $intent?->intent_uuid,
                'opened_by' => ltrim((string) ($dispute->opened_by_alias ?? ''), '@')
                    ?: $dispute->opened_by_identity_id,
                'opened_by_identity' => $dispute->opened_by_identity_id,
                'reason' => $dispute->reason,
                'status' => $dispute->status,
                'evidence_required' => $dispute->evidence_required,
                'opened_at' => $dispute->opened_at?->toJSON(),
                'resolved_at' => $dispute->resolved_at?->toJSON(),
            ],
            'evidence' => $dispute->evidence_snapshot,
            'lifecycle' => $dispute->lifecycle_log ?? [],
            'resolution' => $dispute->resolution,
            'compensation_intent_id' => $compensation?->intent_uuid,
        ];
    }

    private function loadDisputableIntent(string $intentUuid): IdentityPaymentIntent
    {
        $intent = IdentityPaymentIntent::query()
            ->where('intent_uuid', $intentUuid)
            ->with(['accountingEvent.reconciliationRecord', 'settlementAttempts', 'reversalIntent'])
            ->first();

        if (! $intent instanceof IdentityPaymentIntent) {
            throw ValidationException::withMessages([
                'payment_intent_id' => 'Payment intent was not found.',
            ]);
        }

        if ($intent->status !== IdentityPaymentIntent::STATUS_EXECUTED) {
            throw ValidationException::withMessages([
                'payment_intent_id' => 'Only executed payment intents can be disputed.',
            ]);
        }

        if (! $intent->accountingEvent) {
            throw ValidationException::withMessages([
                'payment_intent_id' => 'Accounting evidence is required before opening a dispute.',
            ]);
        }

        return $intent;
    }

    private function loadDisputeForVault(VaultIdentity $vault, string $disputeUuid): IdentityPaymentDispute
    {
        $identityId = strtolower(trim((string) $vault->anchor_address));

        $dispute = IdentityPaymentDispute::query()
            ->where('dispute_uuid', $disputeUuid)
            ->with(['paymentIntent', 'compensationIntent'])
            ->first();

        if (! $dispute instanceof IdentityPaymentDispute) {
            throw ValidationException::withMessages([
                'dispute' => 'Dispute was not found.',
            ]);
        }

        $intent = $dispute->paymentIntent;
        if (! $intent instanceof IdentityPaymentIntent) {
            abort(404);
        }

        $this->assertParticipant($intent, $identityId);

        return $dispute;
    }

    private function assertParticipant(IdentityPaymentIntent $intent, string $identityId): void
    {
        $participants = [
            strtolower((string) $intent->sender_identity_id),
            strtolower((string) $intent->receiver_identity_id),
        ];

        if (! in_array($identityId, $participants, true)) {
            abort(403);
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function lifecycleEvent(string $event, ?User $actor = null, array $metadata = []): array
    {
        return [
            'event' => $event,
            'at' => now()->toJSON(),
            'actor_identity_id' => $actor instanceof User
                ? strtolower(trim((string) $actor->entity_l1_address))
                : null,
            'actor_alias' => $this->displayAlias($actor),
            'metadata' => $metadata,
        ];
    }

    private function displayAlias(?User $user): ?string
    {
        if (! $user instanceof User) {
            return null;
        }

        $username = trim((string) $user->username);

        return $username !== '' ? '@'.$username : null;
    }

    private function assertEnabled(): void
    {
        if (! (bool) config('identity_payments.enabled', false)) {
            throw ValidationException::withMessages([
                'payment' => 'Identity payments are disabled.',
            ]);
        }

        if (! (bool) config('identity_payments.disputes_enabled', false)) {
            throw ValidationException::withMessages([
                'dispute' => 'Identity payment disputes are disabled.',
            ]);
        }
    }
}
