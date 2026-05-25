<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommerceEntityMetric extends Model
{
    protected $fillable = [
        'commerce_entity_id',
        'searches',
        'views',
        'carts',
        'orders',
        'attributed_gmv',
        'estimated_lost_gmv',
        'opportunity_score',
        'active_cases',
        'resolved_cases',
        'calculated_at',
    ];

    protected $casts = [
        'searches' => 'integer',
        'views' => 'integer',
        'carts' => 'integer',
        'orders' => 'float',
        'attributed_gmv' => 'float',
        'estimated_lost_gmv' => 'float',
        'opportunity_score' => 'float',
        'active_cases' => 'integer',
        'resolved_cases' => 'integer',
        'calculated_at' => 'datetime',
    ];

    public function commerceEntity(): BelongsTo
    {
        return $this->belongsTo(CommerceEntity::class);
    }
}
