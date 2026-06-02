<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WriterAuthorityReadiness extends Model
{
    public const FENCING_UNKNOWN = 'unknown';
    public const FENCING_ACTIVE = 'active';
    public const FENCING_FENCED_PREVIOUS = 'fenced_previous_holder';
    public const FENCING_PENDING = 'fencing_pending';
    public const FENCING_EMERGENCY_OVERRIDE = 'emergency_override';

    public const CONFLICT_NONE = 'none';
    public const CONFLICT_NO_HOLDER = 'no_holder';
    public const CONFLICT_CONFLICT = 'conflict';
    public const CONFLICT_STALE_HEARTBEAT = 'stale_heartbeat';
    public const CONFLICT_FENCING_PENDING = 'fencing_pending';
    public const CONFLICT_EMERGENCY_OVERRIDE = 'emergency_override';

    protected $table = 'writer_authority_readiness';

    protected $fillable = [
        'scope',
        'authority_holder',
        'authority_epoch',
        'fencing_status',
        'conflict_status',
        'last_heartbeat_at',
        'last_transition_id',
        'last_transition_hash',
        'last_anchor_hash',
        'metadata',
    ];

    protected $casts = [
        'last_heartbeat_at' => 'datetime',
        'metadata' => \App\Casts\VaultEncryptedJson::class,
    ];

    public function isHealthy(): bool
    {
        return $this->conflict_status === self::CONFLICT_NONE
            && filled($this->authority_holder)
            && filled($this->authority_epoch);
    }
}
