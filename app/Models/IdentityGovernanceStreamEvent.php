<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdentityGovernanceStreamEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'stream_id',
        'version',
        'event_id',
        'event_type',
        'payload',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
