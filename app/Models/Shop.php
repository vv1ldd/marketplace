<?php

namespace App\Models;

use App\Models\Order\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shop extends Model
{
    protected $fillable = [
        'name',
        'domain',
        'business_id',
        'campaign_id',
        'api_key',
        'notification_token',
        'is_active',
        'auto_purchase_enabled',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'auto_purchase_enabled' => 'boolean',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function applications()
    {
        return $this->hasMany(ApiApplication::class);
    }
}
