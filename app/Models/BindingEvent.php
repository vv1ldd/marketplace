<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BindingEvent extends Model
{
    public const TYPE_WALLET_BOUND = 'wallet_bound';
    public const TYPE_WALLET_BINDING_FAILED = 'wallet_binding_failed';
    public const TYPE_WALLET_REVOKED = 'wallet_revoked';

    protected $fillable = [
        'vault_id',
        'identity_binding_id',
        'binding_type',
        'binding_key',
        'binding_value_normalized',
        'event_type',
        'verification_method',
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

    public function identityBinding(): BelongsTo
    {
        return $this->belongsTo(IdentityBinding::class, 'identity_binding_id');
    }
}
