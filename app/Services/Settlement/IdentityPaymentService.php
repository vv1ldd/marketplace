<?php

namespace App\Services\Settlement;

use App\Contracts\IdentityPaymentExecutor;
use App\Models\IdentityBinding;
use App\Models\IdentityPaymentAccountingEvent;
use App\Models\IdentityPaymentIntent;
use App\Models\ReconciliationRecord;
use App\Models\SettlementAttempt;
use App\Models\User;
use App\Models\VaultIdentity;
use App\Services\WalletBindingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class IdentityPaymentService
{
    public const CONTRACT_NAME = 'payment-intent';

    public const CONTRACT_VERSION = 'v3b';

    /**
     * Frozen intent snapshots must not be reinterpreted against the current graph.
     * T0 captures resolution + routing; T1 executes that snapshot; T2 records accounting.
     */
    public const INVARIANT_INTENT_SNAPSHOT_ISOLATION = 'intent_snapshot_is_not_current_graph_state';

    public function __construct(
        private readonly RecipientResolverService $recipientResolver,
        private readonly IdentityPaymentRoutingService $routing,
        private readonly WalletBindingService $bindings,
        private readonly IdentityPaymentExecutor $executor,
        private readonly IdentityPaymentReconciliationService $reconciliation,
        private readonly PaymentLimitPolicyEvaluator $limitEvaluator,
        private readonly PaymentFeeQuoteService $feeQuotes,
    ) {}

    /**
     * @param  array<string, mixed>  $senderIdentity
     * @param  array{
     *     to_alias: string,
     *     asset: string,
     *     amount: string,
     *     execute?: bool,
     *     idempotency_key?: string|null,
     *     reversal_of_intent_id?: string|null,
     *     reversal_reason?: string|null
     * }  $input
     * @return array<string, mixed>
     */
    public function create(
        array $senderIdentity,
        VaultIdentity $senderVault,
        ?User $senderUser,
        array $input,
    ): array {
        if (! (bool) config('identity_payments.enabled', false)) {
            throw ValidationException::withMessages([
                'payment' => 'Identity payments are disabled.',
            ]);
        }

        $senderIdentityId = strtolower(trim((string) ($senderIdentity['entity_l1_address'] ?? '')));
        abort_if($senderIdentityId === '', 403);

        $reversalOfIntentUuid = trim((string) ($input['reversal_of_intent_id'] ?? ''));
        if ($reversalOfIntentUuid !== '') {
            return $this->createReversalIntent(
                $senderIdentity,
                $senderVault,
                $senderUser,
                $reversalOfIntentUuid,
                $input,
            );
        }

        if (! isset($input['to_alias']) || trim((string) $input['to_alias']) === '') {
            throw ValidationException::withMessages([
                'to_alias' => 'Recipient alias is required.',
            ]);
        }

        return $this->createPaymentIntentRecord($senderIdentity, $senderVault, $senderUser, $input);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function createReversalIntent(
        array $senderIdentity,
        VaultIdentity $senderVault,
        ?User $senderUser,
        string $originalIntentUuid,
        array $input,
    ): array {
        $senderIdentityId = strtolower(trim((string) ($senderIdentity['entity_l1_address'] ?? '')));

        $original = IdentityPaymentIntent::query()
            ->where('intent_uuid', $originalIntentUuid)
            ->with(['accountingEvent.reconciliationRecord', 'reversalIntent'])
            ->first();

        if (! $original instanceof IdentityPaymentIntent) {
            throw ValidationException::withMessages([
                'reversal_of_intent_id' => 'Original payment intent was not found.',
            ]);
        }

        if ($original->status !== IdentityPaymentIntent::STATUS_EXECUTED) {
            throw ValidationException::withMessages([
                'reversal_of_intent_id' => 'Only executed payment intents can be reversed.',
            ]);
        }

        if ($original->reversalIntent instanceof IdentityPaymentIntent) {
            throw ValidationException::withMessages([
                'reversal_of_intent_id' => 'A reversal already exists for this payment intent.',
            ]);
        }

        if ($senderIdentityId !== strtolower((string) $original->receiver_identity_id)) {
            throw ValidationException::withMessages([
                'reversal_of_intent_id' => 'Reversal must be initiated by the original payment recipient.',
            ]);
        }

        $toAlias = (string) ($original->sender_alias ?? '');
        if ($toAlias === '') {
            throw ValidationException::withMessages([
                'reversal_of_intent_id' => 'Original payment sender alias is unavailable for reversal routing.',
            ]);
        }

        $reversalInput = [
            'to_alias' => $toAlias,
            'asset' => (string) $original->asset,
            'amount' => (string) $original->amount,
            'execute' => (bool) ($input['execute'] ?? false),
            'idempotency_key' => trim((string) ($input['idempotency_key'] ?? '')),
            '_reversal_of_intent_id' => $original->id,
            '_reversal_reason' => trim((string) ($input['reversal_reason'] ?? 'refund')),
        ];

        return $this->createPaymentIntentRecord(
            $senderIdentity,
            $senderVault,
            $senderUser,
            $reversalInput,
        );
    }

    /**
     * @param  array<string, mixed>  $senderIdentity
     * @param  array{
     *     to_alias: string,
     *     asset: string,
     *     amount: string,
     *     execute?: bool,
     *     idempotency_key?: string,
     *     _reversal_of_intent_id?: int,
     *     _reversal_reason?: string
     * }  $input
     * @return array<string, mixed>
     */
    private function createPaymentIntentRecord(
        array $senderIdentity,
        VaultIdentity $senderVault,
        ?User $senderUser,
        array $input,
    ): array {
        $senderIdentityId = strtolower(trim((string) ($senderIdentity['entity_l1_address'] ?? '')));
        $toAlias = trim((string) $input['to_alias']);
        $asset = strtoupper(trim((string) $input['asset']));
        $amount = trim((string) $input['amount']);
        $execute = (bool) ($input['execute'] ?? false);
        $idempotencyKey = trim((string) ($input['idempotency_key'] ?? ''));
        $reversalOfIntentId = isset($input['_reversal_of_intent_id'])
            ? (int) $input['_reversal_of_intent_id']
            : null;
        $reversalReason = trim((string) ($input['_reversal_reason'] ?? ''));

        if ($idempotencyKey !== '') {
            $existing = IdentityPaymentIntent::query()
                ->where('sender_vault_id', $senderVault->id)
                ->where('idempotency_key', $idempotencyKey)
                ->with(['accountingEvent.reconciliationRecord', 'settlementAttempts'])
                ->first();

            if ($existing instanceof IdentityPaymentIntent) {
                return $this->formatResponse($existing);
            }
        }

        $recipientResolution = $this->recipientResolver->resolve($toAlias);
        $receiverIdentityId = strtolower((string) $recipientResolution['identity_id']);

        if ($receiverIdentityId === $senderIdentityId) {
            throw ValidationException::withMessages([
                'to_alias' => 'Sender and recipient must be different identities.',
            ]);
        }

        if ($reversalOfIntentId !== null) {
            $original = IdentityPaymentIntent::query()->findOrFail($reversalOfIntentId);
            if ($receiverIdentityId !== strtolower((string) $original->sender_identity_id)) {
                throw ValidationException::withMessages([
                    'reversal_of_intent_id' => 'Reversal recipient must be the original payment sender.',
                ]);
            }
        }

        $senderBindings = $this->bindings
            ->listForVault($senderVault, IdentityBinding::TYPE_WALLET)
            ->filter(fn (IdentityBinding $binding) => $binding->isVerified());

        $routingDecision = $this->routing->decide(
            $senderBindings,
            $recipientResolution,
            $asset,
            $amount,
        );

        $senderBinding = $senderBindings->firstWhere('id', (int) $routingDecision['sender_binding_id']);
        if (! $senderBinding instanceof IdentityBinding) {
            throw ValidationException::withMessages([
                'payment' => 'Sender payment routing binding is unavailable.',
            ]);
        }

        $limitDecision = $this->limitEvaluator->evaluate(
            $senderIdentityId,
            $asset,
            $amount,
            (string) $routingDecision['network'],
            $senderBinding,
        );

        if (($limitDecision['approved'] ?? false) !== true) {
            throw ValidationException::withMessages([
                'amount' => $this->limitRejectionMessage($limitDecision),
            ]);
        }

        $feeDecision = $this->feeQuotes->quote(
            $senderIdentityId,
            $this->displayAlias($senderUser),
            $asset,
            $amount,
            (string) $routingDecision['network'],
            $senderBinding,
        );

        if ($execute && ! (bool) config('identity_payments.execute_enabled', false)) {
            throw ValidationException::withMessages([
                'execute' => 'On-chain identity payment execution is disabled.',
            ]);
        }

        $intent = DB::transaction(function () use (
            $senderVault,
            $senderIdentityId,
            $senderUser,
            $recipientResolution,
            $toAlias,
            $asset,
            $amount,
            $routingDecision,
            $limitDecision,
            $feeDecision,
            $idempotencyKey,
            $reversalOfIntentId,
            $reversalReason,
        ): IdentityPaymentIntent {
            $intent = IdentityPaymentIntent::query()->create([
                'intent_uuid' => (string) Str::uuid(),
                'status' => IdentityPaymentIntent::STATUS_ROUTED,
                'sender_vault_id' => $senderVault->id,
                'sender_identity_id' => $senderIdentityId,
                'sender_alias' => $this->displayAlias($senderUser),
                'receiver_identity_id' => strtolower((string) $recipientResolution['identity_id']),
                'receiver_alias' => (string) ($recipientResolution['alias'] ?? $this->aliasFromInput($toAlias)),
                'asset' => $asset,
                'amount' => $amount,
                'amount_wei' => (string) $routingDecision['amount_wei'],
                'sender_binding_id' => (int) $routingDecision['sender_binding_id'],
                'receiver_binding_id' => (int) $routingDecision['receiver_binding_id'],
                'network' => (string) $routingDecision['network'],
                'routing_policy' => (string) $routingDecision['policy'],
                'routing_metadata' => $this->routingDecisionSnapshot($routingDecision),
                'recipient_resolution_snapshot' => $this->recipientResolutionSnapshot($recipientResolution),
                'metadata' => [
                    'limit_decision' => $this->limitDecisionSnapshot($limitDecision),
                    'fee_decision' => $this->feeDecisionSnapshot($feeDecision),
                ],
                'idempotency_key' => $idempotencyKey !== '' ? $idempotencyKey : null,
                'reversal_of_intent_id' => $reversalOfIntentId,
                'reversal_reason' => $reversalOfIntentId !== null && $reversalReason !== '' ? $reversalReason : null,
                'routed_at' => now(),
            ]);

            return $intent->refresh()->load(['accountingEvent.reconciliationRecord', 'settlementAttempts']);
        });

        if ($execute) {
            $this->executeIntent($intent->refresh());
            $intent = $intent->refresh()->load(['accountingEvent.reconciliationRecord', 'settlementAttempts']);
        }

        return $this->formatResponse($intent);
    }

    /**
     * @return array<string, mixed>
     */
    public function listForVault(VaultIdentity $vault, int $limit = 25): array
    {
        if (! (bool) config('identity_payments.enabled', false)) {
            throw ValidationException::withMessages([
                'payment' => 'Identity payments are disabled.',
            ]);
        }

        $identityId = strtolower(trim((string) $vault->anchor_address));
        $limit = max(1, min($limit, 50));

        $intents = IdentityPaymentIntent::query()
            ->where(function ($query) use ($vault, $identityId): void {
                $query->where('sender_vault_id', $vault->id);
                if ($identityId !== '') {
                    $query->orWhere('receiver_identity_id', $identityId);
                }
            })
            ->with(['accountingEvent.reconciliationRecord', 'settlementAttempts', 'reversalOf'])
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return [
            'contract' => [
                'name' => 'payment-intent-list',
                'version' => self::CONTRACT_VERSION,
            ],
            'items' => $intents
                ->map(function (IdentityPaymentIntent $intent) use ($vault): array {
                    $response = $this->formatResponse($intent);
                    $response['activity_direction'] = (string) $intent->sender_vault_id === (string) $vault->id
                        ? 'outgoing'
                        : 'incoming';

                    return $response;
                })
                ->values()
                ->all(),
        ];
    }

    public function execute(VaultIdentity $senderVault, IdentityPaymentIntent $intent): array
    {
        if (! (bool) config('identity_payments.enabled', false)) {
            throw ValidationException::withMessages([
                'payment' => 'Identity payments are disabled.',
            ]);
        }

        if ((string) $intent->sender_vault_id !== (string) $senderVault->id) {
            abort(403);
        }

        if ($intent->status === IdentityPaymentIntent::STATUS_EXECUTED) {
            return $this->formatResponse($intent->load(['accountingEvent.reconciliationRecord', 'settlementAttempts']));
        }

        if ($intent->status !== IdentityPaymentIntent::STATUS_ROUTED) {
            throw ValidationException::withMessages([
                'intent' => 'Only routed payment intents can be executed.',
            ]);
        }

        if (! (bool) config('identity_payments.execute_enabled', false)) {
            throw ValidationException::withMessages([
                'execute' => 'On-chain identity payment execution is disabled.',
            ]);
        }

        $this->executeIntent($intent->refresh());

        return $this->formatResponse($intent->refresh()->load(['accountingEvent.reconciliationRecord', 'settlementAttempts']));
    }

    private function executeIntent(IdentityPaymentIntent $intent): void
    {
        $frozenLimitDecision = (array) data_get($intent->metadata, 'limit_decision', []);
        if (($frozenLimitDecision['approved'] ?? null) !== true) {
            throw ValidationException::withMessages([
                'execute' => 'Frozen limit decision does not permit execution.',
            ]);
        }

        $frozenFeeDecision = (array) data_get($intent->metadata, 'fee_decision', []);
        if ($frozenFeeDecision === [] || ! isset($frozenFeeDecision['fee_amount'])) {
            throw ValidationException::withMessages([
                'execute' => 'Frozen fee decision is unavailable for execution.',
            ]);
        }

        $intent->forceFill(['status' => IdentityPaymentIntent::STATUS_EXECUTING])->save();

        $frozenRoute = $this->frozenRoutingSelection($intent);
        $attempt = $this->createSettlementAttempt($intent, $frozenRoute);

        try {
            $receiverBinding = IdentityBinding::query()->findOrFail((int) $frozenRoute['receiver_binding_id']);
            $recipientAddress = strtolower(trim((string) $receiverBinding->binding_value_normalized));

            if (! preg_match('/^0x[a-f0-9]{40}$/', $recipientAddress)) {
                throw ValidationException::withMessages([
                    'execute' => 'Recipient settlement address is unavailable for the frozen routing snapshot.',
                ]);
            }

            $attempt->forceFill([
                'status' => SettlementAttempt::STATUS_SUBMITTED,
                'submitted_at' => now(),
            ])->save();

            $execution = $this->executor->executeUsdcTransfer(
                (int) $frozenRoute['sender_binding_id'],
                $recipientAddress,
                (string) $intent->amount_wei,
                (string) $frozenRoute['network'],
            );

            $executionSnapshot = $this->settlementExecutionSnapshot($intent, $execution, $attempt);

            $attempt->forceFill([
                'status' => SettlementAttempt::STATUS_CONFIRMED,
                'tx_reference' => (string) $execution['transaction_hash'],
                'confirmed_at' => now(),
            ])->save();

            $intent->forceFill([
                'status' => IdentityPaymentIntent::STATUS_EXECUTED,
                'settlement_reference' => (string) $execution['transaction_hash'],
                'executed_at' => now(),
                'metadata' => array_merge((array) ($intent->metadata ?? []), [
                    'settlement_execution' => $executionSnapshot,
                ]),
            ])->save();

            $this->recordAccountingEvent($intent, $attempt, $executionSnapshot);
        } catch (\Throwable $exception) {
            $failureReason = $exception instanceof ValidationException
                ? collect($exception->errors())->flatten()->first()
                : $exception->getMessage();

            $attempt->forceFill([
                'status' => SettlementAttempt::STATUS_FAILED,
                'failure_reason' => is_string($failureReason) ? substr($failureReason, 0, 255) : 'Settlement attempt failed.',
                'failed_at' => now(),
            ])->save();

            $intent->forceFill([
                'status' => IdentityPaymentIntent::STATUS_ROUTED,
                'metadata' => array_merge((array) ($intent->metadata ?? []), [
                    'last_failed_attempt_id' => $attempt->id,
                ]),
            ])->save();

            throw $exception;
        }
    }

    /**
     * @param  array{network: string, sender_binding_id: int, receiver_binding_id: int}  $frozenRoute
     */
    private function createSettlementAttempt(IdentityPaymentIntent $intent, array $frozenRoute): SettlementAttempt
    {
        $nextAttemptNo = (int) SettlementAttempt::query()
            ->where('identity_payment_intent_id', $intent->id)
            ->max('attempt_no') + 1;

        return SettlementAttempt::query()->create([
            'identity_payment_intent_id' => $intent->id,
            'attempt_no' => $nextAttemptNo,
            'routing_snapshot_ref' => (string) data_get($intent->routing_metadata, 'snapshot_at', ''),
            'network' => (string) $frozenRoute['network'],
            'binding_from' => (int) $frozenRoute['sender_binding_id'],
            'binding_to' => (int) $frozenRoute['receiver_binding_id'],
            'status' => SettlementAttempt::STATUS_CREATED,
        ]);
    }

    /**
     * @param  array<string, mixed>  $executionSnapshot
     */
    private function recordAccountingEvent(
        IdentityPaymentIntent $intent,
        SettlementAttempt $attempt,
        array $executionSnapshot,
    ): IdentityPaymentAccountingEvent {
        if ($intent->accountingEvent instanceof IdentityPaymentAccountingEvent) {
            return $intent->accountingEvent;
        }

        $narrative = $this->buildNarrative($intent);
        $feeDecision = (array) data_get($intent->metadata, 'fee_decision', []);
        $feeAmount = (string) ($feeDecision['fee_amount'] ?? '0');
        $paymentAmount = (string) $intent->amount;
        $debitTotal = $this->addAmounts($paymentAmount, $feeAmount);
        $entries = $this->accountingEntries($intent, $paymentAmount, $feeAmount, $debitTotal);
        $intent->loadMissing('reversalOf');

        $accounting = IdentityPaymentAccountingEvent::query()->create([
            'identity_payment_intent_id' => $intent->id,
            'sender_identity_id' => $intent->sender_identity_id,
            'receiver_identity_id' => $intent->receiver_identity_id,
            'sender_binding_id' => $intent->sender_binding_id,
            'receiver_binding_id' => $intent->receiver_binding_id,
            'asset' => $intent->asset,
            'amount' => $intent->amount,
            'network' => $intent->network,
            'narrative' => $narrative,
            'settlement_reference' => (string) ($executionSnapshot['tx_reference'] ?? $intent->settlement_reference),
            'metadata' => [
                'entries' => $entries,
                'origin' => [
                    'kind' => $intent->reversal_of_intent_id !== null ? 'compensation' : 'payment',
                    'payment_intent_uuid' => $intent->intent_uuid,
                    'reversal_of_payment_intent_uuid' => $intent->reversalOf?->intent_uuid,
                ],
                'fee_decision' => $feeDecision,
                'debit' => [
                    'identity_id' => $intent->sender_identity_id,
                    'alias' => $intent->sender_alias,
                    'amount' => $paymentAmount,
                    'fee_amount' => $feeAmount,
                    'total_amount' => $debitTotal,
                    'asset' => $intent->asset,
                    'binding_id' => $intent->sender_binding_id,
                ],
                'credit' => [
                    'identity_id' => $intent->receiver_identity_id,
                    'alias' => $intent->receiver_alias,
                    'amount' => $paymentAmount,
                    'asset' => $intent->asset,
                    'binding_id' => $intent->receiver_binding_id,
                ],
                'settlement_attempt_id' => $attempt->id,
                'settlement_execution' => $executionSnapshot,
            ],
            'recorded_at' => now(),
        ]);

        $this->reconciliation->reconcile($accounting, $attempt, $intent);

        return $accounting;
    }

    private function buildNarrative(IdentityPaymentIntent $intent): string
    {
        $sender = ltrim((string) ($intent->sender_alias ?? ''), '@');
        $receiver = ltrim((string) ($intent->receiver_alias ?? ''), '@');

        if ($sender === '') {
            $sender = $intent->sender_identity_id;
        }

        if ($receiver === '') {
            $receiver = $intent->receiver_identity_id;
        }

        return sprintf('%s → %s : %s %s', $sender, $receiver, $intent->amount, $intent->asset);
    }

    /**
     * @return array{network: string, sender_binding_id: int, receiver_binding_id: int}
     */
    private function frozenRoutingSelection(IdentityPaymentIntent $intent): array
    {
        $selected = (array) data_get($intent->routing_metadata, 'selected', []);

        return [
            'network' => (string) ($selected['network'] ?? $intent->network),
            'sender_binding_id' => (int) ($selected['sender_binding_id'] ?? $intent->sender_binding_id),
            'receiver_binding_id' => (int) ($selected['receiver_binding_id'] ?? $intent->receiver_binding_id),
        ];
    }

    /**
     * @param  array<string, mixed>  $recipientResolution
     * @return array<string, mixed>
     */
    private function recipientResolutionSnapshot(array $recipientResolution): array
    {
        return [
            'snapshot_at' => now()->toJSON(),
            'contract' => $recipientResolution['contract'] ?? null,
            'alias' => $recipientResolution['alias'] ?? null,
            'identity_id' => $recipientResolution['identity_id'] ?? null,
            'receiving_capabilities' => $recipientResolution['receiving_capabilities'] ?? [],
            'payment_routing_capabilities' => $recipientResolution['payment_routing_capabilities'] ?? [],
            'routing_candidates' => $recipientResolution['routing_candidates'] ?? [],
        ];
    }

    /**
     * @param  array<string, mixed>  $routingDecision
     * @return array<string, mixed>
     */
    private function routingDecisionSnapshot(array $routingDecision): array
    {
        $metadata = (array) ($routingDecision['metadata'] ?? []);

        return [
            'snapshot_at' => now()->toJSON(),
            'policy' => (string) ($routingDecision['policy'] ?? ''),
            'policy_version' => (string) ($metadata['policy_version'] ?? ''),
            'capability_policy_version' => (string) ($metadata['capability_policy_version'] ?? ''),
            'capability_policy_key' => (string) ($metadata['capability_policy_key'] ?? ''),
            'sender_payment_routing_capabilities' => (array) ($metadata['sender_payment_routing_capabilities'] ?? []),
            'recipient_payment_routing_capabilities' => (array) ($metadata['recipient_payment_routing_capabilities'] ?? []),
            'candidates' => (array) ($metadata['candidates'] ?? []),
            'selected' => (array) ($metadata['selected'] ?? [
                'network' => $routingDecision['network'] ?? null,
                'sender_binding_id' => $routingDecision['sender_binding_id'] ?? null,
                'receiver_binding_id' => $routingDecision['receiver_binding_id'] ?? null,
            ]),
            'reason' => (string) ($metadata['reason'] ?? ''),
            'amount_wei' => (string) ($routingDecision['amount_wei'] ?? ''),
            'asset' => (string) ($metadata['asset'] ?? ''),
            'decision_context' => (array) ($metadata['decision_context'] ?? []),
        ];
    }

    /**
     * @param  array<string, mixed>  $limitDecision
     * @return array<string, mixed>
     */
    private function limitDecisionSnapshot(array $limitDecision): array
    {
        return $limitDecision;
    }

    /**
     * @param  array<string, mixed>  $feeDecision
     * @return array<string, mixed>
     */
    private function feeDecisionSnapshot(array $feeDecision): array
    {
        return $feeDecision;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function accountingEntries(
        IdentityPaymentIntent $intent,
        string $paymentAmount,
        string $feeAmount,
        string $debitTotal,
    ): array {
        $asset = (string) $intent->asset;
        $senderLabel = ltrim((string) ($intent->sender_alias ?? ''), '@') ?: $intent->sender_identity_id;
        $receiverLabel = ltrim((string) ($intent->receiver_alias ?? ''), '@') ?: $intent->receiver_identity_id;

        $entries = [
            [
                'identity' => $senderLabel,
                'identity_id' => $intent->sender_identity_id,
                'delta' => '-'.$debitTotal,
                'asset' => $asset,
            ],
            [
                'identity' => $receiverLabel,
                'identity_id' => $intent->receiver_identity_id,
                'delta' => '+'.$paymentAmount,
                'asset' => $asset,
            ],
        ];

        if (bccomp($this->normalizeDecimal($feeAmount), '0', 8) > 0) {
            $entries[] = [
                'account' => 'platform_fee',
                'delta' => '+'.$feeAmount,
                'asset' => $asset,
            ];
        }

        return $entries;
    }

    private function addAmounts(string $left, string $right): string
    {
        return $this->trimTrailingZeros(bcadd($this->normalizeDecimal($left), $this->normalizeDecimal($right), 8));
    }

    private function normalizeDecimal(string $amount): string
    {
        $normalized = trim($amount);

        return $normalized === '' ? '0' : $normalized;
    }

    private function trimTrailingZeros(string $amount): string
    {
        if (! str_contains($amount, '.')) {
            return $amount;
        }

        $trimmed = rtrim(rtrim($amount, '0'), '.');

        return $trimmed === '' ? '0' : $trimmed;
    }

    /**
     * @param  array<string, mixed>  $limitDecision
     */
    private function limitRejectionMessage(array $limitDecision): string
    {
        $reason = (string) ($limitDecision['reason'] ?? 'limit_exceeded');

        return match ($reason) {
            'per_transaction_limit_exceeded' => 'Payment amount exceeds the per-transaction limit for this identity.',
            'daily_limit_exceeded' => 'Payment amount exceeds the remaining daily limit for this identity.',
            default => 'Payment is not permitted by the active limit policy.',
        };
    }

    /**
     * @param  array<string, mixed>  $execution
     * @return array<string, mixed>
     */
    private function settlementExecutionSnapshot(
        IdentityPaymentIntent $intent,
        array $execution,
        SettlementAttempt $attempt,
    ): array {
        $frozenRoute = $this->frozenRoutingSelection($intent);

        return [
            'snapshot_at' => now()->toJSON(),
            'settlement_attempt_id' => $attempt->id,
            'attempt_no' => $attempt->attempt_no,
            'adapter' => (string) ($execution['network'] ?? $frozenRoute['network']),
            'sender_binding_id' => (int) $frozenRoute['sender_binding_id'],
            'receiver_binding_id' => (int) $frozenRoute['receiver_binding_id'],
            'tx_reference' => (string) ($execution['transaction_hash'] ?? ''),
            'network' => (string) ($execution['network'] ?? $frozenRoute['network']),
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

    private function aliasFromInput(string $alias): string
    {
        $normalized = User::normalizeUsername($alias);

        return $normalized !== null ? '@'.$normalized : $alias;
    }

    /**
     * @return array<string, mixed>
     */
    public function formatResponse(IdentityPaymentIntent $intent): array
    {
        $intent->loadMissing(['accountingEvent.reconciliationRecord', 'settlementAttempts', 'reversalOf']);

        $accounting = $intent->accountingEvent;
        $reconciliation = $accounting?->reconciliationRecord;
        $routingSnapshot = (array) ($intent->routing_metadata ?? []);
        $settlementSnapshot = data_get($intent->metadata, 'settlement_execution');

        return [
            'contract' => [
                'name' => self::CONTRACT_NAME,
                'version' => self::CONTRACT_VERSION,
            ],
            'payment_intent' => [
                'id' => $intent->intent_uuid,
                'status' => $intent->status,
                'from_identity' => $intent->sender_identity_id,
                'to_identity' => $intent->receiver_identity_id,
                'from_alias' => $intent->sender_alias,
                'to_alias' => $intent->receiver_alias,
                'asset' => $intent->asset,
                'amount' => $intent->amount,
                'routed_at' => $intent->routed_at?->toJSON(),
                'reversal_of_intent_id' => $intent->reversalOf?->intent_uuid,
                'reversal_reason' => $intent->reversal_reason,
            ],
            'recipient_resolution' => $intent->recipient_resolution_snapshot,
            'routing_decision' => [
                'snapshot_at' => data_get($routingSnapshot, 'snapshot_at'),
                'policy' => data_get($routingSnapshot, 'policy', $intent->routing_policy),
                'policy_version' => data_get($routingSnapshot, 'policy_version'),
                'capability_policy_version' => data_get($routingSnapshot, 'capability_policy_version'),
                'capability_policy_key' => data_get($routingSnapshot, 'capability_policy_key'),
                'sender_payment_routing_capabilities' => data_get($routingSnapshot, 'sender_payment_routing_capabilities', []),
                'recipient_payment_routing_capabilities' => data_get($routingSnapshot, 'recipient_payment_routing_capabilities', []),
                'candidates' => data_get($routingSnapshot, 'candidates', []),
                'selected' => data_get($routingSnapshot, 'selected', [
                    'network' => $intent->network,
                    'sender_binding_id' => $intent->sender_binding_id,
                    'receiver_binding_id' => $intent->receiver_binding_id,
                ]),
                'reason' => data_get($routingSnapshot, 'reason'),
                'amount_wei' => data_get($routingSnapshot, 'amount_wei', $intent->amount_wei),
                'decision_context' => data_get($routingSnapshot, 'decision_context', []),
            ],
            'limit_decision' => data_get($intent->metadata, 'limit_decision', []),
            'fee_decision' => data_get($intent->metadata, 'fee_decision', []),
            'settlement_attempts' => $intent->settlementAttempts
                ->map(fn (SettlementAttempt $attempt) => $this->formatSettlementAttempt($attempt))
                ->values()
                ->all(),
            'accounting_event' => $accounting instanceof IdentityPaymentAccountingEvent
                ? [
                    'id' => $accounting->id,
                    'narrative' => $accounting->narrative,
                    'debit' => data_get($accounting->metadata, 'debit'),
                    'credit' => data_get($accounting->metadata, 'credit'),
                    'entries' => data_get($accounting->metadata, 'entries', []),
                    'fee_decision' => data_get($accounting->metadata, 'fee_decision', []),
                    'asset' => $accounting->asset,
                    'amount' => $accounting->amount,
                    'network' => $accounting->network,
                    'settlement_reference' => $accounting->settlement_reference,
                    'recorded_at' => $accounting->recorded_at?->toJSON(),
                ]
                : null,
            'reconciliation_record' => $reconciliation instanceof ReconciliationRecord
                ? $this->reconciliation->formatRecord($reconciliation)
                : null,
            'settlement_execution' => is_array($settlementSnapshot)
                ? $settlementSnapshot
                : ($intent->settlement_reference !== null
                    ? [
                        'adapter' => $intent->network,
                        'sender_binding_id' => $intent->sender_binding_id,
                        'receiver_binding_id' => $intent->receiver_binding_id,
                        'tx_reference' => $intent->settlement_reference,
                        'network' => $intent->network,
                        'executed_at' => $intent->executed_at?->toJSON(),
                    ]
                    : null),
            // Backward-compatible aliases for existing clients/tests.
            'intent' => [
                'id' => $intent->intent_uuid,
                'status' => $intent->status,
                'from_identity' => $intent->sender_identity_id,
                'to_identity' => $intent->receiver_identity_id,
                'from_alias' => $intent->sender_alias,
                'to_alias' => $intent->receiver_alias,
                'asset' => $intent->asset,
                'amount' => $intent->amount,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatSettlementAttempt(SettlementAttempt $attempt): array
    {
        return [
            'id' => $attempt->id,
            'attempt_no' => $attempt->attempt_no,
            'routing_snapshot_ref' => $attempt->routing_snapshot_ref,
            'network' => $attempt->network,
            'binding_from' => $attempt->binding_from,
            'binding_to' => $attempt->binding_to,
            'status' => $attempt->status,
            'failure_reason' => $attempt->failure_reason,
            'tx_reference' => $attempt->tx_reference,
            'submitted_at' => $attempt->submitted_at?->toJSON(),
            'confirmed_at' => $attempt->confirmed_at?->toJSON(),
            'failed_at' => $attempt->failed_at?->toJSON(),
        ];
    }
}
