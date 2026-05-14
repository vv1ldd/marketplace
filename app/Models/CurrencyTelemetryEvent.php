<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CurrencyTelemetryEvent extends Model
{
    protected $guarded = [];

    protected $casts = [
        'evidence_graph' => 'array',
        'execution_reality' => 'array',
    ];
}
