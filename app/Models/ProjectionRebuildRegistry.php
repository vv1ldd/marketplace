<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectionRebuildRegistry extends Model
{
    public const RESULT_UNKNOWN = 'unknown';
    public const RESULT_HEALTHY = 'healthy';
    public const RESULT_STALE = 'stale';
    public const RESULT_FAILED = 'failed';
    public const RESULT_SOURCE_GAP = 'source_gap';
    public const RESULT_AUTHORITY_GAP = 'authority_gap';
    public const RESULT_ANCHOR_GAP = 'anchor_gap';

    protected $table = 'projection_rebuild_registry';

    protected $fillable = [
        'projection_name',
        'classification',
        'source_transitions',
        'source_authority_decisions',
        'required_anchor_range',
        'rebuild_command',
        'verify_command',
        'last_rebuilt_at',
        'last_verified_at',
        'verification_result',
        'source_revision',
        'anchor_range',
        'metadata',
    ];

    protected $casts = [
        'source_transitions' => \App\Casts\VaultEncryptedJson::class,
        'source_authority_decisions' => \App\Casts\VaultEncryptedJson::class,
        'metadata' => \App\Casts\VaultEncryptedJson::class,
        'last_rebuilt_at' => 'datetime',
        'last_verified_at' => 'datetime',
    ];

    public function isHealthy(): bool
    {
        return $this->verification_result === self::RESULT_HEALTHY;
    }
}
