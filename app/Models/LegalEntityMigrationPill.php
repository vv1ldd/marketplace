<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegalEntityMigrationPill extends Model
{
    protected $fillable = [
        'legal_entity_id',
        'user_id',
        'issued_by_user_id',
        'token_hash',
        'target_domain',
        'expires_at',
        'used_at',
        'used_by_passkey_id',
        'issued_ip',
        'used_ip',
        'metadata',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_user_id');
    }

    public function isConsumable(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
    }
}
