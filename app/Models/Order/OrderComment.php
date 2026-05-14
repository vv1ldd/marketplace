<?php

namespace App\Models\Order;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class OrderComment extends Model
{
    protected $fillable = [
        'order_id',
        'user_id',
        'user_type',
        'comment',
    ];

    protected $casts = [
        'comment' => \App\Casts\VaultEncrypted::class,
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    /**
     * Get the author of the comment (User or Seller).
     */
    public function user(): MorphTo
    {
        return $this->morphTo('user', 'user_type', 'user_id');
    }
}
