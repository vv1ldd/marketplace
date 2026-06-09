<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AuthorityVerdict extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ALLOWED = 'allowed';
    public const STATUS_DENIED = 'denied';
    public const STATUS_CREDITED = 'credited';

    public const DECISION_WAIT = 'wait';
    public const DECISION_ALLOW = 'allow';
    public const DECISION_DENY = 'deny';

    protected $fillable = [
        'merchant_deposit_intent_id',
        'settlement_proof_id',
        'legal_entity_id',
        'credited_ledger_id',
        'policy_key',
        'status',
        'decision',
        'reason_code',
        'required_quorum',
        'accepted_attestations',
        'idempotency_key',
        'metadata',
        'decided_at',
        'credited_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'required_quorum' => 'integer',
        'accepted_attestations' => 'integer',
        'decided_at' => 'datetime',
        'credited_at' => 'datetime',
    ];

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

    public function creditedLedger(): BelongsTo
    {
        return $this->belongsTo(SovereignLedger::class, 'credited_ledger_id');
    }

    public function attestations(): HasMany
    {
        return $this->hasMany(ValidatorAttestation::class);
    }
}
