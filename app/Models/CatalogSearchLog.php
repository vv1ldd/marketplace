<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatalogSearchLog extends Model
{
    protected $fillable = [
        'query',
        'normalized_query',
        'source',
        'intent',
        'filters',
        'confidence',
        'results_count',
        'views_count',
        'carts_count',
    ];

    protected $casts = [
        'filters' => 'array',
        'confidence' => 'float',
        'results_count' => 'integer',
        'views_count' => 'integer',
        'carts_count' => 'integer',
    ];


    public function orders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\Order\Order::class, 'search_log_id');
    }
}
