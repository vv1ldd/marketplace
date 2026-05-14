<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductSalesChannel extends Model
{
    protected $fillable = [
        'product_id',
        'shop_id',
        'channel',
        'is_enabled',
        'last_synced_at',
        'last_error',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'last_synced_at' => 'datetime',
    ];
}
