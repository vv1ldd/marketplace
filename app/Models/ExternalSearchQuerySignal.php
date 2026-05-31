<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExternalSearchQuerySignal extends Model
{
    protected $fillable = [
        'signal_hash',
        'query',
        'normalized_query',
        'source',
        'country',
        'locale',
        'impressions',
        'clicks',
        'ctr',
        'volume',
        'landing_url',
        'observed_at',
        'metadata',
    ];

    protected $casts = [
        'impressions' => 'integer',
        'clicks' => 'integer',
        'ctr' => 'float',
        'volume' => 'integer',
        'observed_at' => 'datetime',
        'metadata' => 'array',
    ];
}
