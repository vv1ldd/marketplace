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
        'user_id',
        'comment',
        'is_problem',
        'assigned_user_id',
        'code_activated',
    ];

    protected $casts = [
        'info' => 'array',
        'client_info' => 'array',
        'code_activated' => 'boolean',
        'is_problem' => 'boolean',
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

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    // Scope — доступные для взятия исполнителем (старые)
    public function scopeAvailableForExecutor($query)
    {
        return $query->where('code_activated', true)
            ->whereNull('assigned_user_id')
            ->where('is_problem', false)
            ->orderBy('created_at', 'asc');
    }

    public function comments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OrderComment::class);
    }
}
