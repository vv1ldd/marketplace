<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ValidatorAttestation extends Model
{
    public const TYPE_PROOF_OBSERVED = 'proof_observed';
    public const TYPE_EVIDENCE_REJECTED = 'evidence_rejected';
    public const TYPE_ADAPTER_CONFIRMED = 'adapter_confirmed';
    public const TYPE_SELF_EXECUTED = 'self_executed';

    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'authority_verdict_id',
        'merchant_deposit_intent_id',
        'settlement_proof_id',
        'legal_entity_id',
        'signer_user_id',
        'signer_identity',
        'signer_role',
        'attestation_type',
        'status',
        'external_reference',
        'idempotency_key',
        'signed_payload_hash',
        'signature_payload',
        'note',
        'attested_at',
    ];

    protected $casts = [
        'signature_payload' => 'array',
        'attested_at' => 'datetime',
    ];

    public function verdict(): BelongsTo
    {
        return $this->belongsTo(AuthorityVerdict::class, 'authority_verdict_id');
    }

    public function intent(): BelongsTo
    {
        return $this->belongsTo(MerchantDepositIntent::class, 'merchant_deposit_intent_id');
    }

    public function proof(): BelongsTo
    {
        return $this->belongsTo(SettlementProof::class, 'settlement_proof_id');
    }

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class);
    }

    public function signer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signer_user_id');
    }
}
