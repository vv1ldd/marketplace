<?php

namespace App\Models\Order;

use Illuminate\Database\Eloquent\Model;

class OrderProgress extends Model
{
    protected $table = 'order_progress';
    public $timestamps = false;
    protected $fillable = [
        'name'
    ];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
