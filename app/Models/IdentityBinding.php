<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdentityBinding extends Model
{
    public const TYPE_WALLET = 'wallet';

    public const STATE_PENDING = 'pending';
    public const STATE_VERIFIED = 'verified';
    public const STATE_REVOKED = 'revoked';

    public const METHOD_SIGNATURE = 'signature';
    public const METHOD_RPC = 'rpc';
    public const METHOD_MANUAL = 'manual';
    public const METHOD_IMPORTED = 'imported';

    /** @var list<string> */
    public const ACTIVE_STATES = [
        self::STATE_PENDING,
        self::STATE_VERIFIED,
    ];

    protected $fillable = [
        'vault_id',
        'binding_type',
        'binding_key',
        'binding_value_original',
        'binding_value_normalized',
        'verification_state',
        'verification_method',
        'metadata',
        'bound_at',
        'verified_at',
        'revoked_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'bound_at' => 'datetime',
        'verified_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function vault(): BelongsTo
    {
        return $this->belongsTo(VaultIdentity::class, 'vault_id');
    }

    public function isActive(): bool
    {
        return in_array($this->verification_state, self::ACTIVE_STATES, true);
    }

    public function isVerified(): bool
    {
        return $this->verification_state === self::STATE_VERIFIED;
    }
}
