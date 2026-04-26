<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $fillable = [
        'code',
        'name',
        'symbol',
        'rate_to_rub',
        'manual_rate',
        'is_auto_update',
    ];

    public function getEffectiveRateAttribute(): float
    {
        return (float)($this->manual_rate ?? $this->rate_to_rub ?? 1.0);
    }
}
