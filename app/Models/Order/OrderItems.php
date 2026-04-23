<?php

namespace App\Models\Order;

use App\Models\PlayStation\PlayStationAlt;
use App\Models\PlayStation\PlayStationTypeForm;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class OrderItems extends Model
{
    protected $guarded = [];

    protected $casts = [
        'activate_till' => 'datetime',
        'client_info' => 'array',
        'is_activated' => 'boolean',
        'is_redeemed' => 'boolean',
        'activated_at' => 'datetime',
        'purchase_status' => 'string',
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


    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')
            ->whereColumn('orders.user_id', 'users.id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->select('users.*');
    }


}
