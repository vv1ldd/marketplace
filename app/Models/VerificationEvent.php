<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerificationEvent extends Model
{
    public const TYPE_PROOF_VERIFIED = 'proof_verified';
    public const TYPE_PROOF_VERIFICATION_FAILED = 'proof_verification_failed';

    protected $fillable = [
        'vault_id',
        'binding_proof_id',
        'proof_type',
        'binding_key',
        'event_type',
        'payload',
        'occurred_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function vault(): BelongsTo
    {
        return $this->belongsTo(VaultIdentity::class, 'vault_id');
    }

    public function bindingProof(): BelongsTo
    {
        return $this->belongsTo(BindingProof::class, 'binding_proof_id');
    }
}
