<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItems extends Model
{
    protected $fillable = [
        'order_id',
        'sku',
        'count',
        'is_activated',
        'is_redeemed',
        'activate_till',
        'client_info',
    ];

    protected $casts = [
        'activate_till' => 'datetime',
        'client_info' => 'array',
    ];

    public function order(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }
}
