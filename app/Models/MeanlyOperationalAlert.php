<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeanlyOperationalAlert extends Model
{
    protected $fillable = [
        'alert_key',
        'type',
        'severity',
        'surface',
        'status',
        'title',
        'description',
        'occurrence_count',
        'threshold',
        'last_analytics_event_id',
        'last_sovereign_ledger_id',
        'context',
        'first_seen_at',
        'last_seen_at',
        'acknowledged_at',
        'resolved_at',
    ];

    protected $casts = [
        'occurrence_count' => 'integer',
        'threshold' => 'integer',
        'last_analytics_event_id' => 'integer',
        'last_sovereign_ledger_id' => 'integer',
        'context' => 'array',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
