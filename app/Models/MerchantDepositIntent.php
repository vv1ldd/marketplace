<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MerchantDepositIntent extends Model
{
    public const RAIL_INVOICE_MANUAL = 'invoice_manual';
    public const RAIL_CRYPTO_USDT_USDC = 'crypto_usdt_usdc';
    public const RAIL_PAYMENT_PROVIDER = 'payment_provider';
    public const RAIL_MERCHANT_TRANSFER = 'merchant_transfer';
    public const RAIL_OPS_MANUAL_CREDIT = 'ops_manual_credit';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ISSUED = 'issued';
    public const STATUS_WAITING_PAYMENT = 'waiting_payment';
    public const STATUS_PROOF_RECEIVED = 'proof_received';
    public const STATUS_WAITING_AUTHORITY = 'waiting_authority';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_CREDITED = 'credited';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'legal_entity_id',
        'created_by',
        'target_legal_entity_id',
        'rail',
        'status',
        'reference',
        'amount',
        'currency',
        'idempotency_key',
        'invoice_payload',
        'provider_payload',
        'metadata',
        'issued_at',
        'expires_at',
        'cancelled_at',
        'credited_at',
        'credited_ledger_id',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'invoice_payload' => 'array',
        'provider_payload' => 'array',
        'metadata' => 'array',
        'issued_at' => 'datetime',
        'expires_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'credited_at' => 'datetime',
    ];

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class);
    }

    public function targetLegalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class, 'target_legal_entity_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function creditedLedger(): BelongsTo
    {
        return $this->belongsTo(SovereignLedger::class, 'credited_ledger_id');
    }

    public function proofs(): HasMany
    {
        return $this->hasMany(SettlementProof::class);
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
