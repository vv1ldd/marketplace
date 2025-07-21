<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItemTypes extends Model
{
    protected $table = 'order_item_types';

    public $timestamps = false;

    protected $fillable = [
        'name',
    ];

    public function orderItems()
    {
        return $this->hasMany(OrderItems::class);
    }
}
