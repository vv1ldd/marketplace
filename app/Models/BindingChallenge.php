<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BindingChallenge extends Model
{
    public const METHOD_SIGNATURE = 'signature';

    protected $fillable = [
        'vault_id',
        'binding_type',
        'binding_key',
        'binding_value_original',
        'binding_value_normalized',
        'nonce',
        'message',
        'verification_method',
        'expires_at',
        'consumed_at',
        'verification_attempt_count',
        'last_verification_error',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'last_verification_error' => 'array',
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];

    public function vault(): BelongsTo
    {
        return $this->belongsTo(VaultIdentity::class, 'vault_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }
}
