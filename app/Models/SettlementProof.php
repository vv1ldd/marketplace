<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SettlementProof extends Model
{
    public const STATUS_PROOF_RECEIVED = 'proof_received';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_CREDITED = 'credited';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'merchant_deposit_intent_id',
        'legal_entity_id',
        'reviewed_by',
        'credited_ledger_id',
        'source',
        'status',
        'external_reference',
        'idempotency_key',
        'confirmed_amount',
        'confirmed_currency',
        'confirmation_count',
        'raw_payload_hash',
        'raw_payload',
        'review_note',
        'received_at',
        'confirmed_at',
        'credited_at',
    ];

    protected $casts = [
        'confirmed_amount' => 'decimal:4',
        'confirmation_count' => 'integer',
        'raw_payload' => 'array',
        'received_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'credited_at' => 'datetime',
    ];

    public function intent(): BelongsTo
    {
        return $this->belongsTo(MerchantDepositIntent::class, 'merchant_deposit_intent_id');
    }

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function creditedLedger(): BelongsTo
    {
        return $this->belongsTo(SovereignLedger::class, 'credited_ledger_id');
    }

    public function authorityVerdicts(): HasMany
    {
        return $this->hasMany(AuthorityVerdict::class);
    }

    public function validatorAttestations(): HasMany
    {
        return $this->hasMany(ValidatorAttestation::class);
    }
}
