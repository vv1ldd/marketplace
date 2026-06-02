<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MutationGuardEntry extends Model
{
    protected $fillable = [
        'guard_key',
        'mutation_id',
        'mutation_path',
        'actor',
        'action',
        'entity_type',
        'entity_id',
        'idempotency_key',
        'context_fingerprint',
        'mode',
        'decision',
        'status',
        'metadata',
        'first_seen_at',
        'last_seen_at',
        'completed_at',
    ];

    protected $casts = [
        'metadata' => \App\Casts\VaultEncryptedJson::class,
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'completed_at' => 'datetime',
    ];
}
