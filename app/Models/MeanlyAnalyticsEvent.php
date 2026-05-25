<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeanlyAnalyticsEvent extends Model
{
    protected $fillable = [
        'sovereign_ledger_id',
        'event_type',
        'event_name',
        'surface',
        'severity',
        'request_id',
        'user_id',
        'session_hash',
        'visitor_hash',
        'ip_hash',
        'user_agent_hash',
        'route_name',
        'route_action',
        'method',
        'path',
        'status_code',
        'duration_ms',
        'is_slow',
        'product_id',
        'order_id',
        'shop_id',
        'legal_entity_id',
        'provider_type',
        'category',
        'currency',
        'error_class',
        'error_message',
        'error_fingerprint',
        'context',
        'occurred_at',
    ];

    protected $casts = [
        'sovereign_ledger_id' => 'integer',
        'user_id' => 'integer',
        'status_code' => 'integer',
        'duration_ms' => 'integer',
        'is_slow' => 'boolean',
        'product_id' => 'integer',
        'order_id' => 'integer',
        'shop_id' => 'integer',
        'legal_entity_id' => 'integer',
        'context' => 'array',
        'occurred_at' => 'datetime',
    ];
}
