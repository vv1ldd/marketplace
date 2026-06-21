<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VaultIdentity extends Model
{
    public const KIND_PERSONAL = 'personal';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'owner_user_id',
        'anchor_address',
        'vault_kind',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function bindings(): HasMany
    {
        return $this->hasMany(IdentityBinding::class, 'vault_id');
    }

    public function bindingChallenges(): HasMany
    {
        return $this->hasMany(BindingChallenge::class, 'vault_id');
    }

    public function bindingEvents(): HasMany
    {
        return $this->hasMany(BindingEvent::class, 'vault_id');
    }

    public function bindingProofs(): HasMany
    {
        return $this->hasMany(BindingProof::class, 'vault_id');
    }

    public function settlementProofs(): HasMany
    {
        return $this->hasMany(VaultSettlementProof::class, 'vault_id');
    }

    public function verificationEvents(): HasMany
    {
        return $this->hasMany(VerificationEvent::class, 'vault_id');
    }

    public function settlementAuditEvents(): HasMany
    {
        return $this->hasMany(SettlementAuditEvent::class, 'vault_id');
    }
}
