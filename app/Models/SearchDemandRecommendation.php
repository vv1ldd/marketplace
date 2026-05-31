<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SearchDemandRecommendation extends Model
{
    public const STATUS_PROPOSED = 'proposed';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_APPLIED = 'applied';

    protected $fillable = [
        'recommendation_hash',
        'type',
        'query',
        'normalized_query',
        'insight_type',
        'expected_entity',
        'impact_score',
        'confidence',
        'evidence',
        'status',
        'decided_at',
        'applied_at',
    ];

    protected $casts = [
        'expected_entity' => 'array',
        'impact_score' => 'float',
        'confidence' => 'float',
        'evidence' => 'array',
        'decided_at' => 'datetime',
        'applied_at' => 'datetime',
    ];
}
