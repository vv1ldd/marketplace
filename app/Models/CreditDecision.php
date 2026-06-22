<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditDecision extends Model
{
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const REASON_ELIGIBLE = 'eligible';
    public const REASON_PROOF_NOT_VERIFIED = 'proof_not_verified';
    public const REASON_PROOF_ALREADY_CREDITED = 'proof_already_credited';
    public const REASON_BINDING_NOT_ACTIVE = 'binding_not_active';
    public const REASON_BINDING_NOT_VERIFIED = 'binding_not_verified';
    public const REASON_BINDING_VAULT_MISMATCH = 'binding_vault_mismatch';
    public const REASON_BINDING_PROOF_MISMATCH = 'binding_proof_mismatch';

    protected $fillable = [
        'vault_settlement_proof_id',
        'identity_binding_id',
        'status',
        'reason',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function vaultSettlementProof(): BelongsTo
    {
        return $this->belongsTo(VaultSettlementProof::class, 'vault_settlement_proof_id');
    }

    public function identityBinding(): BelongsTo
    {
        return $this->belongsTo(IdentityBinding::class, 'identity_binding_id');
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }
}
