<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CurrencyHistory extends Model
{
    protected $fillable = [
        'currency_id',
        'official_rate',
        'p2p_bybit',
        'spread_percent',
        'record_date',
    ];

    protected $casts = [
        'record_date' => 'date',
    ];

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }
}
