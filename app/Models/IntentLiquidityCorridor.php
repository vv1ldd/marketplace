<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntentLiquidityCorridor extends Model
{
    protected $fillable = [
        'intent_liquidity_node_id',
        'corridor_type',
        'corridor_key',
        'source',
        'route_type',
        'route_score',
        'capacity',
        'latency_ms',
        'friction_score',
        'failure_modes',
        'diagnostics',
        'execution_ready',
        'observed_at',
    ];

    protected $casts = [
        'route_score' => 'float',
        'capacity' => 'float',
        'latency_ms' => 'integer',
        'friction_score' => 'float',
        'failure_modes' => 'array',
        'diagnostics' => 'array',
        'execution_ready' => 'boolean',
        'observed_at' => 'datetime',
    ];

    public function node(): BelongsTo
    {
        return $this->belongsTo(IntentLiquidityNode::class, 'intent_liquidity_node_id');
    }
}
