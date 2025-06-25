<?php

namespace App\Models\PlayStation;

use Illuminate\Database\Eloquent\Model;

class PlayStationAlt extends Model
{
    protected $fillable = [
        'sku',
        'data',
        'base_price',
        'price_with_discount',
        'name',
    ];
}
