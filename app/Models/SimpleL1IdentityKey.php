<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SimpleL1IdentityKey extends Model
{
    protected $fillable = [
        'user_id',
        'entity_l1_address',
        'key_l1_address',
        'key_type',
        'public_key',
        'public_key_hash',
        'trust_level',
        'device_name',
        'enrolled_via',
        'first_seen_at',
        'last_used_at',
        'revoked_at',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'first_seen_at' => 'datetime',
        'last_used_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
