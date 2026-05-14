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

    const TYPE_GAMES = 'games';

    const TYPE_BOTH = 'both';

    protected $fillable = [
        'name',
        'type',
        'allowed_categories',
        'allowed_regions',
        'domain',
        'redeem_url',
        'use_custom_redeem_url',
        'store_api_token',
        'voucher_prefix',
        'import_status',
        'import_progress',
        'import_hash',
        'import_token',
        'ps_tax',
        'markup_percent',
        'tariff_type',
        'min_price_threshold',
        'discount_buffer_hours',
        'default_stock',
        'market_category_id',
        'ps_tax_for_sites',
        'business_id',
        'campaign_id',
        'api_key',
        'ip_whitelist',
        'notification_token',
        'ym_tax',
        'ym_boost_percent',
        'ym_stock',
        'ym_warehouse_id',
        'ym_min_price',
        'ym_category_id',
        'ym_diff_hours',
        'ym_base_card',
        'ym_logo',
        'ym_chat_greeting',
        'ym_chat_finish',
        'ym_chat_code_footer',
        'ym_min_selling_price',
        'is_active',
        'is_sandbox',
        'web3_wallet',
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
        'shop_region',
        'redeem_requires_extended_profile',
        'is_global_catalog_enabled',
        'is_voucher_generation_enabled',
        'allow_all_brands',
        'use_custom_smtp',
        'support_email',
        'support_telegram',
        'client_id',
        'client_secret',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_sandbox' => 'boolean',
        'auto_purchase_enabled' => 'boolean',
        'redeem_requires_extended_profile' => 'boolean',
        'is_global_catalog_enabled' => 'boolean',
        'allow_all_brands' => 'boolean',
        'is_voucher_generation_enabled' => 'boolean',
        'use_custom_smtp' => 'boolean',
        'use_custom_redeem_url' => 'boolean',
        'markup_percent' => 'integer',
        'min_price_threshold' => 'integer',
        'ym_tax' => 'decimal:2',
        'ym_boost_percent' => 'decimal:2',
        'allowed_categories' => 'array',
        'allowed_regions' => 'array',
        'api_key' => \App\Casts\VaultEncrypted::class,
        'client_secret' => \App\Casts\VaultEncrypted::class,
        'smtp_password' => \App\Casts\VaultEncrypted::class,
        'telegram_bot_token' => \App\Casts\VaultEncrypted::class,
        'notification_token' => \App\Casts\VaultEncrypted::class,
        'import_token' => \App\Casts\VaultEncrypted::class,
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Customer::class, 'shop_id');
    }

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class);
    }

    public function syncLegalEntityManager(): void
    {
        if ($this->legal_entity_id && $this->legalEntity) {
            $this->legalEntity->save(); // This will trigger the booted saved() listener to sync owners
        }
    }

    public function sellers(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Seller::class, 'shop_user', 'shop_id', 'seller_id')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function managers(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->sellers();
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function apiApplications(): HasMany
    {
        return $this->hasMany(ApiApplication::class);
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }

    /**
     * Локаль текстов на сгенерированных карточках товаров (CardImageService).
     * Берётся из shop_region магазина; по умолчанию RU как в миграции БД.
     */
    /**
     * Полная ссылка на страницу активации для покупателя.
     *
     * @param  bool  $appendShopQueryForPlatform  Добавить ?shop= префикс к системному redeem (общий хост).
     */
    public function getEffectiveRedeemUrl(bool $appendShopQueryForPlatform = true): string
    {
        if ($this->use_custom_redeem_url && filled($this->redeem_url)) {
            return $this->normalizeRedeemUrl((string) $this->redeem_url);
        }

        $base = rtrim((string) SystemSetting::get('default_redeem_url', 'https://wildcloud.ru/redeem'), '/');

        if ($appendShopQueryForPlatform && filled($this->voucher_prefix)) {
            $prefix = preg_replace('/[^A-Z0-9-]/i', '', (string) $this->voucher_prefix);
            if ($prefix !== '') {
                $sep = str_contains($base, '?') ? '&' : '?';
                $base .= $sep.'shop='.rawurlencode($prefix);
            }
        }

        return $base;
    }

    private function normalizeRedeemUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return rtrim((string) SystemSetting::get('default_redeem_url', 'https://wildcloud.ru/redeem'), '/');
        }
        $lower = strtolower($url);
        if (! str_starts_with($lower, 'http://') && ! str_starts_with($lower, 'https://')) {
            $url = 'https://'.ltrim($url, '/');
        }

        return rtrim($url, '/');
    }

    public function getCardImageLocale(): string
    {
        $region = strtoupper(trim((string) ($this->shop_region ?? '')));

        if ($region === '') {
            $region = 'RU';
        }

        return match ($region) {
            'RU' => 'RU',
            'GE' => 'GE',
            'ES' => 'ES',
            'TR' => 'TR',
            'TK' => 'TK',
            default => 'EN',
        };
    }

    protected static function booted()
    {
        static::created(function ($shop) {
            app(\App\Services\LedgerService::class)->recordGlobal('SHOP_CREATED', $shop, $shop->toArray());
        });

        static::updated(function ($shop) {
            $changes = $shop->getChanges();
            unset($changes['updated_at']);
            if (empty($changes)) return;

            app(\App\Services\LedgerService::class)->recordGlobal('SHOP_UPDATED', $shop, [
                'changes' => $changes,
                'original' => array_intersect_key($shop->getOriginal(), $changes)
            ]);
        });

        static::deleted(function ($shop) {
            app(\App\Services\LedgerService::class)->recordGlobal('SHOP_DELETED', $shop, [
                'name' => $shop->name,
                'domain' => $shop->domain
            ]);
        });
    }
}
