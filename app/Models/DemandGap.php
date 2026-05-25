<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class DemandGap extends Model
{
    protected $fillable = [
        'canonical_query',
        'brand_entity_key',
        'region_entity_key',
        'category_entity_key',
        'search_volume',
        'views_count',
        'carts_count',
        'zero_results_count',
        'average_results_count',
        'attributed_orders_count',
        'attributed_gmv',
        'estimated_lost_gmv',
        'opportunity_score',
        'opportunity_diagnosis',
        'diagnosis_confidence',
        'opportunity_diagnosis_graph',
        'demand_gap_score',
        'priority_label',
        'last_searched_at',
    ];

    protected $casts = [
        'search_volume' => 'integer',
        'brand_entity_key' => 'string',
        'region_entity_key' => 'string',
        'category_entity_key' => 'string',
        'views_count' => 'integer',
        'carts_count' => 'integer',
        'zero_results_count' => 'integer',
        'average_results_count' => 'float',
        'attributed_orders_count' => 'float',
        'attributed_gmv' => 'float',
        'estimated_lost_gmv' => 'float',
        'opportunity_score' => 'float',
        'opportunity_diagnosis' => 'string',
        'diagnosis_confidence' => 'float',
        'opportunity_diagnosis_graph' => 'array',
        'demand_gap_score' => 'float',
        'last_searched_at' => 'datetime',
    ];


    public function opportunityCases(): HasMany
    {
        return $this->hasMany(OpportunityCase::class, 'canonical_query', 'canonical_query');
    }

}
