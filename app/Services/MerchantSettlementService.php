<?php

namespace App\Services;

use App\Models\LegalEntity;
use App\Models\AuthorityVerdict;
use App\Models\MerchantDepositIntent;
use App\Models\SettlementProof;
use App\Models\SovereignLedger;
use App\Models\User;
use App\Models\ValidatorAttestation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MerchantSettlementService
{
    public function __construct(
        private readonly SettlementNetworkRegistry $settlementNetworks,
    ) {}

    /**
     * @param array<string, mixed> $proofPayload
     * @return array{valid: bool, error?: string, proof?: array<string, mixed>, verification?: string}
     */
    public function verifyCryptoDepositProof(array $proofPayload): array
    {
        return $this->settlementNetworks->merchantDepositAdapter()->verifyDepositProof($proofPayload);
    }

    /**
     * @param array<string, mixed> $proofPayload
     */
    public function recordVerifiedCryptoProof(
        MerchantDepositIntent $intent,
        User $reviewer,
        array $proofPayload,
        string $note = '',
    ): SettlementProof {
        $verification = $this->verifyCryptoDepositProof($proofPayload);
        if ($verification['valid'] !== true) {
            throw ValidationException::withMessages([
                'proof' => (string) ($verification['error'] ?? 'Invalid crypto deposit proof.'),
            ]);
        }

        $proof = (array) ($verification['proof'] ?? []);
        if (isset($verification['verification'])) {
            $proof['verification'] = $verification['verification'];
        }
        $externalReference = (string) ($proof['tx_hash'] ?? '');

        return $this->approveProofAndCredit(
            intent: $intent,
            reviewer: $reviewer,
            externalReference: $externalReference,
            confirmedAmount: null,
            source: 'evm_deposit_proof',
            note: $note,
            rawPayload: $proof,
        );
    }

    private function hydrateCryptoDepositPayload(
        MerchantDepositIntent $intent,
        LegalEntity $legalEntity,
        float $amountRub,
    ): MerchantDepositIntent {
        $providerPayload = array_merge(
            (array) $intent->provider_payload,
            $this->settlementNetworks->merchantDepositAdapter()->merchantDepositPayload($legalEntity, $amountRub, [
                'intent_id' => (int) $intent->id,
            ]),
        );

        $intent->forceFill(['provider_payload' => $providerPayload])->save();

        return $intent->refresh();
    }

    /**
     * @param array<string, mixed> $options
     */
    public function issueIntent(
        LegalEntity $legalEntity,
        User $createdBy,
        string $rail,
        float $amount,
        array $options = []
    ): MerchantDepositIntent {
        $amount = round($amount, 4);
        $rail = $this->normalizeRail($rail);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Amount must be greater than zero.']);
        }

        if ($rail === MerchantDepositIntent::RAIL_MERCHANT_TRANSFER) {
            $target = LegalEntity::query()->find((int) ($options['target_legal_entity_id'] ?? 0));
            if (! $target) {
                throw ValidationException::withMessages(['target_legal_entity_id' => 'Target merchant is required for merchant transfer.']);
            }

            return $this->transferBetweenMerchants(
                source: $legalEntity,
                target: $target,
                createdBy: $createdBy,
                amount: $amount,
                note: (string) ($options['note'] ?? $options['comment'] ?? '')
            );
        }

        return DB::transaction(function () use ($legalEntity, $createdBy, $rail, $amount, $options): MerchantDepositIntent {
            $idempotencyKey = $this->intentIdempotencyKey($legalEntity, $rail, $amount, $options);
            $existing = MerchantDepositIntent::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($existing) {
                return $existing;
            }

            $intent = MerchantDepositIntent::create([
                'legal_entity_id' => $legalEntity->id,
                'created_by' => $createdBy->id,
                'rail' => $rail,
                'status' => MerchantDepositIntent::STATUS_WAITING_PAYMENT,
                'reference' => $this->nextReference('MDI'),
                'amount' => $amount,
                'currency' => 'RUB',
                'idempotency_key' => $idempotencyKey,
                'invoice_payload' => $this->invoicePayload($rail, $legalEntity, $amount),
                'provider_payload' => $this->providerPayload($rail, $legalEntity, $amount),
                'metadata' => [
                    'comment' => (string) ($options['comment'] ?? ''),
                    'source' => 'merchant_center',
                ],
                'issued_at' => now(),
                'expires_at' => now()->addDays(7),
            ]);

            app(LedgerService::class)->record(
                shop: null,
                eventType: 'MERCHANT_DEPOSIT_INTENT_CREATED',
                entity: $intent,
                payload: [
                    'intent_id' => $intent->id,
                    'reference' => $intent->reference,
                    'rail' => $intent->rail,
                    'status' => $intent->status,
                    'amount' => $amount,
                    'currency' => 'RUB',
                    'idempotency_key' => $idempotencyKey,
                ],
                legalEntity: $legalEntity,
                triggerSource: 'MERCHANT:CENTER',
                inputData: [
                    'rail' => $rail,
                    'amount' => $amount,
                ],
            );

            if ($rail === MerchantDepositIntent::RAIL_CRYPTO_USDT_USDC) {
                $intent = $this->hydrateCryptoDepositPayload($intent->refresh(), $legalEntity, $amount);
            }

            return $intent->refresh();
        });
    }

    public function cancelIntent(MerchantDepositIntent $intent, LegalEntity $legalEntity): MerchantDepositIntent
    {
        if ((int) $intent->legal_entity_id !== (int) $legalEntity->id) {
            throw ValidationException::withMessages(['intent' => 'Deposit intent does not belong to this merchant.']);
        }
        if ($intent->status === MerchantDepositIntent::STATUS_CREDITED) {
            throw ValidationException::withMessages(['intent' => 'Credited deposit intent cannot be cancelled.']);
        }

        return DB::transaction(function () use ($intent, $legalEntity): MerchantDepositIntent {
            $intent = MerchantDepositIntent::query()->lockForUpdate()->findOrFail($intent->id);
            $intent->forceFill([
                'status' => MerchantDepositIntent::STATUS_CANCELLED,
                'cancelled_at' => now(),
            ])->save();

            app(LedgerService::class)->record(
                null,
                'MERCHANT_DEPOSIT_INTENT_CANCELLED',
                $intent,
                [
                    'intent_id' => $intent->id,
                    'reference' => $intent->reference,
                    'rail' => $intent->rail,
                    'amount' => (float) $intent->amount,
                    'currency' => $intent->currency,
                ],
                $legalEntity,
                'MERCHANT:CENTER',
            );

            return $intent->refresh();
        });
    }

    /**
     * Backward-compatible wrapper. Callers still pass one action, but credit can only
     * happen after the authority verdict allows it.
     *
     * @param array<string, mixed> $rawPayload
     */
    public function approveProofAndCredit(
        MerchantDepositIntent $intent,
        User $reviewer,
        string $externalReference,
        ?float $confirmedAmount = null,
        string $source = 'ops_manual_review',
        string $note = '',
        array $rawPayload = []
    ): SettlementProof {
        $proof = $this->recordProof(
            intent: $intent,
            externalReference: $externalReference,
            confirmedAmount: $confirmedAmount,
            source: $source,
            note: $note,
            rawPayload: $rawPayload,
        );
        $this->attestProof(
            proof: $proof,
            signer: $reviewer,
            type: ValidatorAttestation::TYPE_PROOF_OBSERVED,
            externalReference: $externalReference,
            note: $note,
        );
        $verdict = app(AuthorityPolicyService::class)->evaluateProof($proof->refresh());
        if ($verdict->decision === AuthorityVerdict::DECISION_ALLOW) {
            $this->creditAfterAuthority($verdict);
        }

        return $proof->refresh();
    }

    /**
     * @param array<string, mixed> $rawPayload
     */
    public function recordProof(
        MerchantDepositIntent $intent,
        string $externalReference,
        ?float $confirmedAmount = null,
        string $source = 'ops_manual_review',
        string $note = '',
        array $rawPayload = []
    ): SettlementProof {
        return DB::transaction(function () use ($intent, $externalReference, $confirmedAmount, $source, $note, $rawPayload): SettlementProof {
            $intent = MerchantDepositIntent::query()->lockForUpdate()->findOrFail($intent->id);
            if (in_array($intent->status, [
                MerchantDepositIntent::STATUS_CANCELLED,
                MerchantDepositIntent::STATUS_EXPIRED,
                MerchantDepositIntent::STATUS_REJECTED,
            ], true)) {
                throw ValidationException::withMessages(['intent' => 'Intent is not creditable.']);
            }
            if ($intent->status === MerchantDepositIntent::STATUS_CREDITED) {
                return $intent->proofs()->where('status', SettlementProof::STATUS_CREDITED)->latest()->firstOrFail();
            }

            $legalEntity = LegalEntity::query()->lockForUpdate()->findOrFail($intent->legal_entity_id);
            $amount = round((float) ($confirmedAmount ?? $intent->amount), 4);
            if ($amount <= 0 || $amount > (float) $intent->amount) {
                throw ValidationException::withMessages(['amount' => 'Confirmed amount must be positive and cannot exceed the intent amount.']);
            }

            $idempotencyKey = hash('sha256', implode('|', [
                'settlement-proof',
                $intent->id,
                $source,
                trim($externalReference),
                number_format($amount, 4, '.', ''),
            ]));

            $proof = SettlementProof::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($proof?->status === SettlementProof::STATUS_CREDITED) {
                return $proof;
            }

            $proof ??= SettlementProof::create([
                'merchant_deposit_intent_id' => $intent->id,
                'legal_entity_id' => $legalEntity->id,
                'source' => $source,
                'status' => SettlementProof::STATUS_PROOF_RECEIVED,
                'external_reference' => trim($externalReference),
                'idempotency_key' => $idempotencyKey,
                'confirmed_amount' => $amount,
                'confirmed_currency' => 'RUB',
                'confirmation_count' => (int) ($rawPayload['confirmation_count'] ?? 1),
                'raw_payload_hash' => $rawPayload === [] ? null : hash('sha256', json_encode($rawPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
                'raw_payload' => $rawPayload,
                'review_note' => $note,
                'received_at' => now(),
            ]);

            $intent->forceFill(['status' => MerchantDepositIntent::STATUS_WAITING_AUTHORITY])->save();
            app(LedgerService::class)->record(null, 'MERCHANT_SETTLEMENT_PROOF_RECEIVED', $proof, [
                'intent_id' => $intent->id,
                'proof_id' => $proof->id,
                'external_reference' => $proof->external_reference,
                'source' => $proof->source,
                'amount' => $amount,
                'currency' => 'RUB',
                'idempotency_key' => $idempotencyKey,
            ], $legalEntity, 'AUTHORITY:PROOF');

            return $proof->refresh();
        });
    }

    /**
     * @param array<string, mixed> $signaturePayload
     */
    public function attestProof(
        SettlementProof $proof,
        User $signer,
        string $type = ValidatorAttestation::TYPE_PROOF_OBSERVED,
        ?string $externalReference = null,
        string $note = '',
        array $signaturePayload = []
    ): ValidatorAttestation {
        return DB::transaction(function () use ($proof, $signer, $type, $externalReference, $note, $signaturePayload): ValidatorAttestation {
            $proof = SettlementProof::query()->lockForUpdate()->with('intent')->findOrFail($proof->id);
            $intent = $proof->intent;
            $idempotencyKey = hash('sha256', implode('|', [
                'validator-attestation',
                $proof->id,
                $type,
                $signer->id,
                $externalReference ?: $proof->external_reference,
            ]));

            $attestation = ValidatorAttestation::query()->updateOrCreate(
                ['idempotency_key' => $idempotencyKey],
                [
                    'merchant_deposit_intent_id' => $intent?->id,
                    'settlement_proof_id' => $proof->id,
                    'legal_entity_id' => $proof->legal_entity_id,
                    'signer_user_id' => $signer->id,
                    'signer_identity' => $signer->sovereignIdentityAddress() ?: 'user:'.$signer->id,
                    'signer_role' => $signer->hasOpsSovereignAccess() ? 'sovereign_validator' : 'merchant_validator',
                    'attestation_type' => $type,
                    'status' => $type === ValidatorAttestation::TYPE_EVIDENCE_REJECTED
                        ? ValidatorAttestation::STATUS_REJECTED
                        : ValidatorAttestation::STATUS_ACCEPTED,
                    'external_reference' => $externalReference ?: $proof->external_reference,
                    'signed_payload_hash' => $signaturePayload === [] ? null : hash('sha256', json_encode($signaturePayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
                    'signature_payload' => $signaturePayload,
                    'note' => $note,
                    'attested_at' => now(),
                ],
            );

            $proof->forceFill([
                'reviewed_by' => $signer->id,
                'status' => $type === ValidatorAttestation::TYPE_EVIDENCE_REJECTED
                    ? SettlementProof::STATUS_REJECTED
                    : SettlementProof::STATUS_CONFIRMED,
                'confirmed_at' => $type === ValidatorAttestation::TYPE_EVIDENCE_REJECTED ? $proof->confirmed_at : now(),
                'review_note' => $note ?: $proof->review_note,
            ])->save();

            if ($intent && $type !== ValidatorAttestation::TYPE_EVIDENCE_REJECTED && $intent->status !== MerchantDepositIntent::STATUS_CREDITED) {
                $intent->forceFill(['status' => MerchantDepositIntent::STATUS_WAITING_AUTHORITY])->save();
            }

            app(LedgerService::class)->record(null, 'VALIDATOR_ATTESTATION_RECORDED', $attestation, [
                'intent_id' => $intent?->id,
                'proof_id' => $proof->id,
                'attestation_id' => $attestation->id,
                'attestation_type' => $type,
                'signer_identity' => $attestation->signer_identity,
                'status' => $attestation->status,
                'external_reference' => $attestation->external_reference,
            ], $proof->legalEntity, 'AUTHORITY:ATTESTATION');

            return $attestation->refresh();
        });
    }

    public function evaluateAndCreditIfAllowed(SettlementProof $proof): AuthorityVerdict
    {
        $verdict = app(AuthorityPolicyService::class)->evaluateProof($proof);
        if ($verdict->decision === AuthorityVerdict::DECISION_ALLOW) {
            return $this->creditAfterAuthority($verdict)->refresh();
        }

        return $verdict;
    }

    public function creditAfterAuthority(AuthorityVerdict $verdict): AuthorityVerdict
    {
        return DB::transaction(function () use ($verdict): AuthorityVerdict {
            $verdict = AuthorityVerdict::query()->lockForUpdate()->with(['proof.intent'])->findOrFail($verdict->id);
            if ($verdict->status === AuthorityVerdict::STATUS_CREDITED) {
                return $verdict;
            }
            if ($verdict->decision !== AuthorityVerdict::DECISION_ALLOW) {
                throw ValidationException::withMessages(['authority' => 'Authority verdict has not allowed credit.']);
            }

            $proof = SettlementProof::query()->lockForUpdate()->findOrFail($verdict->settlement_proof_id);
            $intent = MerchantDepositIntent::query()->lockForUpdate()->findOrFail($proof->merchant_deposit_intent_id);
            if ($intent->status === MerchantDepositIntent::STATUS_CREDITED) {
                $verdict->forceFill([
                    'status' => AuthorityVerdict::STATUS_CREDITED,
                    'credited_at' => $intent->credited_at ?: now(),
                    'credited_ledger_id' => $intent->credited_ledger_id,
                ])->save();

                return $verdict->refresh();
            }

            $legalEntity = LegalEntity::query()->lockForUpdate()->findOrFail($intent->legal_entity_id);
            $amount = round((float) $proof->confirmed_amount, 4);
            $before = (float) $legalEntity->available_balance;

            app(LedgerService::class)->record(null, 'FINANCE_DEPOSIT_CONFIRMED', $proof, [
                'intent_id' => $intent->id,
                'proof_id' => $proof->id,
                'authority_verdict_id' => $verdict->id,
                'amount' => $amount,
                'currency' => 'RUB',
                'decision' => $verdict->decision,
                'required_quorum' => $verdict->required_quorum,
                'accepted_attestations' => $verdict->accepted_attestations,
            ], $legalEntity, 'AUTHORITY:POLICY');

            $legalEntity->increment('available_balance', $amount);
            $legalEntity->increment('balance', $amount);
            $legalEntity->refresh();

            $ledger = app(LedgerService::class)->record(null, 'FINANCE_CREDITED', $proof, [
                'intent_id' => $intent->id,
                'proof_id' => $proof->id,
                'authority_verdict_id' => $verdict->id,
                'asset' => 'RUB',
                'amount' => $amount,
                'amount_rub' => $amount,
                'currency' => 'RUB',
                'external_reference' => $proof->external_reference,
                'available_before' => $before,
                'available_after' => (float) $legalEntity->available_balance,
                'idempotency_key' => $proof->idempotency_key,
            ], $legalEntity, 'AUTHORITY:CREDIT');

            $proof->forceFill([
                'status' => SettlementProof::STATUS_CREDITED,
                'credited_at' => now(),
                'credited_ledger_id' => $ledger->id,
            ])->save();
            $intent->forceFill([
                'status' => MerchantDepositIntent::STATUS_CREDITED,
                'credited_at' => now(),
                'credited_ledger_id' => $ledger->id,
            ])->save();
            $verdict->forceFill([
                'status' => AuthorityVerdict::STATUS_CREDITED,
                'credited_at' => now(),
                'credited_ledger_id' => $ledger->id,
            ])->save();

            ValidatorAttestation::query()
                ->where('settlement_proof_id', $proof->id)
                ->update(['authority_verdict_id' => $verdict->id]);

            return $verdict->refresh();
        });
    }

    public function rejectIntent(MerchantDepositIntent $intent, User $reviewer, string $note = ''): MerchantDepositIntent
    {
        return DB::transaction(function () use ($intent, $reviewer, $note): MerchantDepositIntent {
            $intent = MerchantDepositIntent::query()->lockForUpdate()->findOrFail($intent->id);
            if ($intent->status === MerchantDepositIntent::STATUS_CREDITED) {
                throw ValidationException::withMessages(['intent' => 'Credited deposit intent cannot be rejected.']);
            }

            $intent->forceFill([
                'status' => MerchantDepositIntent::STATUS_REJECTED,
                'metadata' => array_merge($intent->metadata ?? [], [
                    'rejected_by' => $reviewer->id,
                    'rejected_note' => $note,
                    'rejected_at' => now()->toJSON(),
                ]),
            ])->save();

            app(LedgerService::class)->record(null, 'MERCHANT_DEPOSIT_INTENT_REJECTED', $intent, [
                'intent_id' => $intent->id,
                'reference' => $intent->reference,
                'amount' => (float) $intent->amount,
                'currency' => $intent->currency,
                'reviewed_by' => $reviewer->id,
                'note' => $note,
            ], $intent->legalEntity, 'OPS:SETTLEMENT');

            return $intent->refresh();
        });
    }

    public function transferBetweenMerchants(
        LegalEntity $source,
        LegalEntity $target,
        User $createdBy,
        float $amount,
        string $note = ''
    ): MerchantDepositIntent {
        if ((int) $source->id === (int) $target->id) {
            throw ValidationException::withMessages(['target_legal_entity_id' => 'Target merchant must be different.']);
        }

        return DB::transaction(function () use ($source, $target, $createdBy, $amount, $note): MerchantDepositIntent {
            $source = LegalEntity::query()->lockForUpdate()->findOrFail($source->id);
            $target = LegalEntity::query()->lockForUpdate()->findOrFail($target->id);
            $amount = round($amount, 4);
            if ((float) $source->available_balance < $amount) {
                throw ValidationException::withMessages(['amount' => 'Недостаточно RUB для перевода другому мерчанту.']);
            }

            $idempotencyKey = $this->intentIdempotencyKey($source, MerchantDepositIntent::RAIL_MERCHANT_TRANSFER, $amount, [
                'target_legal_entity_id' => $target->id,
                'note' => $note,
            ]);
            $existing = MerchantDepositIntent::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }

            $intent = MerchantDepositIntent::create([
                'legal_entity_id' => $source->id,
                'created_by' => $createdBy->id,
                'target_legal_entity_id' => $target->id,
                'rail' => MerchantDepositIntent::RAIL_MERCHANT_TRANSFER,
                'status' => MerchantDepositIntent::STATUS_CONFIRMED,
                'reference' => $this->nextReference('MTX'),
                'amount' => $amount,
                'currency' => 'RUB',
                'idempotency_key' => $idempotencyKey,
                'metadata' => [
                    'note' => $note,
                    'target_legal_entity_name' => $target->short_name ?: $target->name,
                ],
                'issued_at' => now(),
            ]);

            $beforeSource = (float) $source->available_balance;
            $beforeTarget = (float) $target->available_balance;
            $source->decrement('available_balance', $amount);
            $source->decrement('balance', $amount);
            $target->increment('available_balance', $amount);
            $target->increment('balance', $amount);
            $source->refresh();
            $target->refresh();

            $debitLedger = app(LedgerService::class)->record(null, 'FINANCE_TRANSFER_DEBIT', $intent, [
                'intent_id' => $intent->id,
                'reference' => $intent->reference,
                'asset' => 'RUB',
                'amount' => -$amount,
                'amount_rub' => -$amount,
                'currency' => 'RUB',
                'target_legal_entity_id' => $target->id,
                'available_before' => $beforeSource,
                'available_after' => (float) $source->available_balance,
                'idempotency_key' => $idempotencyKey,
            ], $source, 'MERCHANT:CENTER');

            $creditLedger = app(LedgerService::class)->record(null, 'FINANCE_TRANSFER_CREDIT', $intent, [
                'intent_id' => $intent->id,
                'reference' => $intent->reference,
                'asset' => 'RUB',
                'amount' => $amount,
                'amount_rub' => $amount,
                'currency' => 'RUB',
                'source_legal_entity_id' => $source->id,
                'source_ledger_id' => $debitLedger->id,
                'available_before' => $beforeTarget,
                'available_after' => (float) $target->available_balance,
                'idempotency_key' => $idempotencyKey,
            ], $target, 'MERCHANT:CENTER');

            $proof = SettlementProof::create([
                'merchant_deposit_intent_id' => $intent->id,
                'legal_entity_id' => $target->id,
                'reviewed_by' => $createdBy->id,
                'credited_ledger_id' => $creditLedger->id,
                'source' => MerchantDepositIntent::RAIL_MERCHANT_TRANSFER,
                'status' => SettlementProof::STATUS_CREDITED,
                'external_reference' => $intent->reference,
                'idempotency_key' => hash('sha256', 'proof|'.$idempotencyKey),
                'confirmed_amount' => $amount,
                'confirmed_currency' => 'RUB',
                'confirmation_count' => 1,
                'raw_payload' => [
                    'source_legal_entity_id' => $source->id,
                    'target_legal_entity_id' => $target->id,
                    'debit_ledger_id' => $debitLedger->id,
                    'credit_ledger_id' => $creditLedger->id,
                ],
                'received_at' => now(),
                'confirmed_at' => now(),
                'credited_at' => now(),
            ]);

            $verdict = AuthorityVerdict::create([
                'merchant_deposit_intent_id' => $intent->id,
                'settlement_proof_id' => $proof->id,
                'legal_entity_id' => $target->id,
                'credited_ledger_id' => $creditLedger->id,
                'policy_key' => 'merchant_settlement.'.MerchantDepositIntent::RAIL_MERCHANT_TRANSFER,
                'status' => AuthorityVerdict::STATUS_CREDITED,
                'decision' => AuthorityVerdict::DECISION_ALLOW,
                'reason_code' => 'self_executing_transfer',
                'required_quorum' => 0,
                'accepted_attestations' => 1,
                'idempotency_key' => hash('sha256', 'authority-verdict|'.$idempotencyKey),
                'metadata' => [
                    'source_legal_entity_id' => $source->id,
                    'target_legal_entity_id' => $target->id,
                    'debit_ledger_id' => $debitLedger->id,
                    'credit_ledger_id' => $creditLedger->id,
                ],
                'decided_at' => now(),
                'credited_at' => now(),
            ]);

            ValidatorAttestation::create([
                'authority_verdict_id' => $verdict->id,
                'merchant_deposit_intent_id' => $intent->id,
                'settlement_proof_id' => $proof->id,
                'legal_entity_id' => $target->id,
                'signer_user_id' => $createdBy->id,
                'signer_identity' => $createdBy->sovereignIdentityAddress() ?: 'user:'.$createdBy->id,
                'signer_role' => 'merchant_validator',
                'attestation_type' => ValidatorAttestation::TYPE_SELF_EXECUTED,
                'status' => ValidatorAttestation::STATUS_ACCEPTED,
                'external_reference' => $intent->reference,
                'idempotency_key' => hash('sha256', 'attestation|'.$idempotencyKey),
                'note' => 'Self-executing merchant transfer policy.',
                'attested_at' => now(),
            ]);

            $intent->forceFill([
                'status' => MerchantDepositIntent::STATUS_CREDITED,
                'credited_at' => now(),
                'credited_ledger_id' => $creditLedger->id,
                'metadata' => array_merge($intent->metadata ?? [], [
                    'debit_ledger_id' => $debitLedger->id,
                    'credit_ledger_id' => $creditLedger->id,
                    'settlement_proof_id' => $proof->id,
                    'authority_verdict_id' => $verdict->id,
                ]),
            ])->save();

            return $intent->refresh();
        });
    }

    /**
     * @param array<string, mixed> $options
     */
    private function intentIdempotencyKey(LegalEntity $legalEntity, string $rail, float $amount, array $options): string
    {
        if (isset($options['idempotency_key']) && trim((string) $options['idempotency_key']) !== '') {
            return (string) $options['idempotency_key'];
        }

        return hash('sha256', json_encode([
            'legal_entity_id' => $legalEntity->id,
            'rail' => $rail,
            'amount' => number_format($amount, 4, '.', ''),
            'target_legal_entity_id' => $options['target_legal_entity_id'] ?? null,
            'comment' => $options['comment'] ?? $options['note'] ?? '',
            'day' => now()->toDateString(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function normalizeRail(string $rail): string
    {
        $rail = trim($rail);
        $allowed = [
            MerchantDepositIntent::RAIL_INVOICE_MANUAL,
            MerchantDepositIntent::RAIL_CRYPTO_USDT_USDC,
            MerchantDepositIntent::RAIL_PAYMENT_PROVIDER,
            MerchantDepositIntent::RAIL_MERCHANT_TRANSFER,
            MerchantDepositIntent::RAIL_OPS_MANUAL_CREDIT,
        ];

        if (! in_array($rail, $allowed, true)) {
            throw ValidationException::withMessages(['rail' => 'Unsupported balance rail.']);
        }

        if ($rail === MerchantDepositIntent::RAIL_CRYPTO_USDT_USDC && ! $this->settlementNetworks->cryptoRailsEnabled()) {
            throw ValidationException::withMessages([
                'rail' => 'Crypto settlement rails are disabled. Simple commerce mode is active.',
            ]);
        }

        return $rail;
    }

    /**
     * @return array<int, string>
     */
    public function allowedDepositRails(): array
    {
        $rails = [
            MerchantDepositIntent::RAIL_INVOICE_MANUAL,
            MerchantDepositIntent::RAIL_CRYPTO_USDT_USDC,
            MerchantDepositIntent::RAIL_PAYMENT_PROVIDER,
            MerchantDepositIntent::RAIL_MERCHANT_TRANSFER,
            MerchantDepositIntent::RAIL_OPS_MANUAL_CREDIT,
        ];

        if (! $this->settlementNetworks->cryptoRailsEnabled()) {
            $rails = array_values(array_filter(
                $rails,
                static fn (string $rail): bool => $rail !== MerchantDepositIntent::RAIL_CRYPTO_USDT_USDC,
            ));
        }

        return $rails;
    }

    private function nextReference(string $prefix): string
    {
        do {
            $reference = $prefix.'-'.Str::upper(Str::random(12));
        } while (MerchantDepositIntent::query()->where('reference', $reference)->exists());

        return $reference;
    }

    /**
     * @return array<string, mixed>
     */
    private function invoicePayload(string $rail, LegalEntity $legalEntity, float $amount): array
    {
        return [
            'title' => match ($rail) {
                MerchantDepositIntent::RAIL_CRYPTO_USDT_USDC => 'Crypto deposit invoice',
                MerchantDepositIntent::RAIL_PAYMENT_PROVIDER => 'Payment provider checkout',
                MerchantDepositIntent::RAIL_OPS_MANUAL_CREDIT => 'Ops reviewed credit',
                default => 'Merchant RUB balance invoice',
            },
            'recipient' => 'Meanly Settlement Authority',
            'payer' => $legalEntity->short_name ?: $legalEntity->name,
            'amount' => $amount,
            'currency' => 'RUB',
            'instructions' => match ($rail) {
                MerchantDepositIntent::RAIL_CRYPTO_USDT_USDC => 'Send stablecoin to the issued deposit address. Credit requires network proof.',
                MerchantDepositIntent::RAIL_PAYMENT_PROVIDER => 'Complete the payment link. Credit requires provider webhook/proof.',
                MerchantDepositIntent::RAIL_OPS_MANUAL_CREDIT => 'Ops will attach verified settlement proof before crediting balance.',
                default => 'Pay this invoice and attach/reference the payment for Ops review.',
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function providerPayload(string $rail, LegalEntity $legalEntity, float $amount): array
    {
        return match ($rail) {
            MerchantDepositIntent::RAIL_CRYPTO_USDT_USDC => [
                'settlement_network' => $this->settlementNetworks->merchantCryptoNetworkKey(),
                'network' => 'evm',
                'assets' => $this->settlementNetworks->network($this->settlementNetworks->merchantCryptoNetworkKey())->assets,
                'deposit_address_status' => 'pending_intent',
                'expected_amount_rub' => $amount,
            ],
            MerchantDepositIntent::RAIL_PAYMENT_PROVIDER => [
                'provider' => 'payment_provider_adapter',
                'checkout_status' => 'pending_adapter',
                'expected_amount_rub' => $amount,
            ],
            default => [
                'provider' => 'manual_settlement',
                'legal_entity_id' => $legalEntity->id,
            ],
        };
    }
}
