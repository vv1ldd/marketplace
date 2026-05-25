<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletAccount extends Model
{
    protected $fillable = [
        'user_id',
        'l1_address',
        'asset',
        'available_minor',
        'reserved_minor',
    ];

    protected $casts = [
        'available_minor' => 'integer',
        'reserved_minor' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
