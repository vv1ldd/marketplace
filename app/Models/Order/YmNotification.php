<?php

namespace App\Models\Order;

use Illuminate\Database\Eloquent\Model;

class YmNotification extends Model
{
    protected $table = 'ym_notifications';

    protected $fillable = [
        'campaign_id',
        'order_id',
        'type',
        'payload',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'order_id');
    }
}
