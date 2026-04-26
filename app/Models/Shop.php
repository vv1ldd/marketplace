<?php

namespace App\Models;

use App\Models\Order\Order;
use Filament\Models\Contracts\HasName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shop extends Model implements HasName
{
    public function getFilamentName(): string
    {
        return $this->name;
    }
    const TYPE_VOUCHERS = 'vouchers';
    const TYPE_GAMES    = 'games';
    const TYPE_BOTH     = 'both';

    protected $fillable = [
        'name',
        'type',
        'domain',
        'redeem_url',
        'store_api_token',
        'voucher_prefix',
        'ps_tax',
        'ps_tax_for_sites',
        'business_id',
        'campaign_id',
        'api_key',
        'ip_whitelist',
        'notification_token',
        'ym_tax',
        'ym_stock',
        'ym_warehouse_id',
        'ym_min_price',
        'ym_category_id',
        'ym_diff_hours',
        'ym_base_card',
        'ym_logo',
        'is_active',
        'auto_purchase_enabled',
        'use_custom_smtp',
        'smtp_host',
        'smtp_port',
        'smtp_user',
        'smtp_password',
        'smtp_encryption',
        'smtp_from_address',
        'smtp_from_name',
        'smtp_subject',
        'telegram_bot_token',
        'telegram_chat_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'auto_purchase_enabled' => 'boolean',
        'use_custom_smtp' => 'boolean',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function clients(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(User::class, 'orders', 'shop_id', 'user_id')->distinct();
    }

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class);
    }

    public function syncLegalEntityManager(): void
    {
        if ($this->legal_entity_id && $this->legalEntity && $this->legalEntity->user_id) {
            $this->managers()->syncWithoutDetaching([
                $this->legalEntity->user_id => ['role' => 'owner']
            ]);
        }
    }

    public function managers(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(User::class, 'shop_user', 'shop_id', 'user_id')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function apiApplications(): HasMany
    {
        return $this->hasMany(ApiApplication::class);
    }
}
