<?php

namespace App\Models\Order;

use App\Models\User;
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
        'chat_id',
        'user_id'
    ];

    protected $casts = [
        'info' => 'array',
        'client_info' => 'array',
    ];

    public function items(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OrderItems::class, 'order_id', 'id');
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function progress(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(OrderProgress::class, 'progress_id', 'id');
    }
}
