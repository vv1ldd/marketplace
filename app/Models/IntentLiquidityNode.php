<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IntentLiquidityNode extends Model
{
    public const INTENT_BUY = 'buy';
    public const INTENT_SELL = 'sell';
    public const INTENT_EXCHANGE = 'exchange';
    public const INTENT_INDEX = 'index';
    public const INTENT_FULFILL = 'fulfill';

    protected $fillable = [
        'intent_key',
        'intent_type',
        'actor_role',
        'entity_type',
        'entity_id',
        'entity_slug',
        'entity_label',
        'attributes',
        'demand_score',
        'readiness_score',
        'confidence_score',
        'status',
        'calculated_at',
    ];

    protected $casts = [
        'attributes' => 'array',
        'demand_score' => 'float',
        'readiness_score' => 'float',
        'confidence_score' => 'float',
        'calculated_at' => 'datetime',
    ];

    public function corridors(): HasMany
    {
        return $this->hasMany(IntentLiquidityCorridor::class);
    }
}
