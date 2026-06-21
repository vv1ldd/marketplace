<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdentityGovernanceProjectionCache extends Model
{
    public $timestamps = false;

    protected $table = 'identity_governance_projection_cache';

    protected $primaryKey = 'stream_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'stream_id',
        'through_version',
        'registry_projection',
        'governance_projection',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'through_version' => 'integer',
            'registry_projection' => 'array',
            'governance_projection' => 'array',
            'updated_at' => 'datetime',
        ];
    }
}
