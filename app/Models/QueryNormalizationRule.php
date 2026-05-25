<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class QueryNormalizationRule extends Model
{
    protected $fillable = [
        'match_type',
        'source',
        'target',
        'priority',
        'is_active',
    ];

    protected $casts = [
        'priority' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('query_normalization_rules_active'));
        static::deleted(fn () => Cache::forget('query_normalization_rules_active'));
    }
}
