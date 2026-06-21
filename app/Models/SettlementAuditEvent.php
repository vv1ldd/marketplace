<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SettlementAuditEvent extends Model
{
    protected $fillable = [
        'vault_id',
        'identity_id',
        'adapter_key',
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
}
