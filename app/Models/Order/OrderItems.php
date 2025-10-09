<?php

namespace App\Models\Order;

use App\Models\PlayStation\PlayStationAlt;
use App\Models\PlayStation\PlayStationTypeForm;
use Illuminate\Database\Eloquent\Model;

class OrderItems extends Model
{
    protected $fillable = [
        'order_id',
        'key',
        'uuid',
        'sku',
        'count',
        'is_activated',
        'is_redeemed',
        'activate_till',
        'client_info',
        'activated_at',
        'type_id',
        'type_form_id'
    ];

    protected $casts = [
        'activate_till' => 'datetime',
        'client_info' => 'array',
        'is_activated' => 'boolean',
        'is_redeemed' => 'boolean',
        'activated_at' => 'datetime',
    ];

    public function order(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    public function type(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(OrderItemTypes::class, 'type_id', 'id');
    }

    public function typeForm(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PlayStationTypeForm::class, 'type_form_id', 'id');
    }

    // app/Models/OrderItem.php
    public function game()
    {
        return $this->belongsTo(PlayStationAlt::class, 'sku', 'sku');
    }
}
