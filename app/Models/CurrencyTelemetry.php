<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CurrencyTelemetry extends Model
{
    protected $fillable = [
        'currency_code',
        'rate',
        'source_type',
        'source_name',
        'reporter_id',
        'city',
        'confidence',
        'reporter_reputation',
        'payload',
        'observed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'observed_at' => 'datetime',
    ];
}
