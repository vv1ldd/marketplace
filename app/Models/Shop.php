<?php

namespace App\Models;

use App\Models\Order\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shop extends Model
{
    const TYPE_VOUCHERS = 'vouchers';
    const TYPE_GAMES    = 'games';
    const TYPE_BOTH     = 'both';

    protected $fillable = [
        'name',
        'type',
        'domain',
        'store_api_token',
        'voucher_prefix',
        'ps_tax',
        'ps_tax_for_sites',
        'business_id',
        'campaign_id',
        'api_key',
        'notification_token',
        'is_active',
        'auto_purchase_enabled',
        'smtp_host',
        'smtp_port',
        'smtp_user',
        'smtp_password',
        'smtp_encryption',
        'smtp_from_address',
        'smtp_from_name',
        'telegram_bot_token',
        'telegram_chat_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'auto_purchase_enabled' => 'boolean',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function apiApplications(): HasMany
    {
        return $this->hasMany(ApiApplication::class);
    }
}
