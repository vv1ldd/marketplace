<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'order_id',
        'uuid',
        'status',
        'sub_status',
        'info',
        'client_info',
    ];

    protected $casts = [
        'items' => 'array',
        'client_info' => 'array',
    ];

    public function items(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OrderItems::class, 'order_id', 'id');
    }
}
