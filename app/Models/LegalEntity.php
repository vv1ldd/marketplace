<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LegalEntity extends Model
{
    protected $fillable = [
        'brand_id',
        'seller_id',
        'user_id',
        'name',
        'short_name',
        'inn',
        'kpp',
        'ogrn',
        'legal_address',
        'postal_address',
        'bank_name',
        'bank_bic',
        'bank_account',
        'bank_correspondent_account',
        'director_name',
        'phone',
        'email',
        'balance',
        'available_balance',
        'reserved_balance',
        'currency',
        'country_id', // 🏛️ Jurisdiction
        'tariff_type',
        'markup_percent',
        'allowed_categories',
        'allowed_brands',
        'allow_all_brands',
        'is_active',
        'wildflow_api_token',
        'country_code',
        'tax_system',
        'tax_rate',
        'is_vat_payer',
        'vat_rate',
        'agreement_signed_at',
        'agreement_signature',
        'status',
        'agreement_metadata',
        'vendor_credentials',
        'native_token_balance',
        'native_token_reserved',
        'native_token_currency',
    ];

    protected $casts = [
        'available_balance' => 'decimal:2',
        'reserved_balance' => 'decimal:2',
        'balance' => 'decimal:2',
        'native_token_balance' => 'decimal:4',
        'native_token_reserved' => 'decimal:4',
        'allowed_categories' => 'array',
        'allowed_brands' => 'array',
        'allow_all_brands' => 'boolean',
        'is_active' => 'boolean',
        'agreement_metadata' => 'array',
        'vendor_credentials' => 'array',
        
        // 💰 Finance & Tax Casting
        'tax_system' => \App\Enums\TaxSystemEnum::class,
        'tax_rate' => 'decimal:2',
        'is_vat_payer' => 'boolean',
        'vat_rate' => 'decimal:2',
        'agreement_signed_at' => 'datetime',

        'name' => \App\Casts\VaultEncrypted::class . ':name_bidx',
        'short_name' => \App\Casts\VaultEncrypted::class . ':short_name_bidx',
        'inn' => \App\Casts\VaultEncrypted::class . ':inn_bidx',
        'kpp' => \App\Casts\VaultEncrypted::class . ':kpp_bidx',
        'ogrn' => \App\Casts\VaultEncrypted::class . ':ogrn_bidx',
        'director_name' => \App\Casts\VaultEncrypted::class . ':director_name_bidx',
        'phone' => \App\Casts\VaultEncrypted::class . ':phone_bidx',
        'email' => \App\Casts\VaultEncrypted::class . ':email_bidx',
        'bank_name' => \App\Casts\VaultEncrypted::class . ':bank_name_bidx',
        'bank_bic' => \App\Casts\VaultEncrypted::class . ':bank_bic_bidx',
        'bank_account' => \App\Casts\VaultEncrypted::class . ':bank_account_bidx',
        'bank_correspondent_account' => \App\Casts\VaultEncrypted::class . ':bank_correspondent_account_bidx',
        'legal_address' => \App\Casts\VaultEncrypted::class . ':legal_address_bidx',
        'postal_address' => \App\Casts\VaultEncrypted::class . ':postal_address_bidx',
        'agreement_signature' => \App\Casts\VaultEncrypted::class . ':agreement_signature_bidx',
    ];

    protected static function booted(): void
    {
        static::created(function ($entity) {
            app(\App\Services\LedgerService::class)->recordGlobal('LEGAL_ENTITY_CREATED', $entity, $entity->toArray());
            
            // 📡 AUTO-SYNC to Wildflow Kernel
            try {
                (new \App\Services\WildflowService())->syncPartner(
                    $entity, 
                    $entity->vendor_credentials ?? []
                );
            } catch (\Exception $e) {
                \Log::warning("Wildflow Partner Sync failed during registration: " . $e->getMessage());
            }
        });

        static::updated(function ($entity) {
            $changes = $entity->getChanges();
            unset($changes['updated_at']);
            if (empty($changes)) return;

            app(\App\Services\LedgerService::class)->recordGlobal('LEGAL_ENTITY_UPDATED', $entity, [
                'changes' => $changes,
                'original' => array_intersect_key($entity->getOriginal(), $changes)
            ]);

            // 📡 BALANCE & STATUS SYNC: If critical state changed, push to Kernel
            if (isset($changes['balance']) || isset($changes['available_balance']) || isset($changes['status']) || isset($changes['vendor_credentials']) || isset($changes['agreement_metadata'])) {
                try {
                    (new \App\Services\WildflowService())->syncPartner(
                        $entity, 
                        $entity->vendor_credentials ?? []
                    );
                } catch (\Exception $e) {
                    \Log::warning("Wildflow State Sync failed: " . $e->getMessage());
                }
            }
        });

        static::saved(function ($entity) {
            if ($entity->seller_id) {
                $entity->sellers()->syncWithoutDetaching([
                    $entity->seller_id => ['role' => 'owner'],
                ]);
            }
        });

        static::deleted(function ($entity) {
            app(\App\Services\LedgerService::class)->recordGlobal('LEGAL_ENTITY_DELETED', $entity, [
                'name' => $entity->name,
                'inn' => $entity->inn
            ]);
        });
    }

    public static function findByInn(string $inn): ?self
    {
        $salt = config('vault.blind_index.salt', 'default-salt');
        $bidx = hash_hmac('sha256', strtolower(trim($inn)), $salt);
 
        return static::where('inn_bidx', $bidx)->first();
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shops(): HasMany
    {
        return $this->hasMany(Shop::class);
    }

    public function sovereignRequests(): HasMany
    {
        return $this->hasMany(SovereignBalanceRequest::class);
    }

    public function sellers(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Seller::class, 'legal_entity_managers', 'legal_entity_id', 'seller_id')
            ->withPivot('role', 'user_id')
            ->withTimestamps();
    }

    public function managers(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(User::class, 'legal_entity_managers', 'legal_entity_id', 'user_id')
            ->withPivot('role', 'seller_id')
            ->withTimestamps();
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(MappingCountry::class, 'country_id');
    }

    /**
     * Все API-терминалы продавца на платформе marketplace.
     * Каждый терминал имеет terminal_id + PIN для аутентификации.
     */
    public function terminals(): HasMany
    {
        return $this->hasMany(SellerTerminal::class);
    }

    /**
     * Первичный активный терминал (самый новый действующий).
     */
    public function activeTerminal(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(SellerTerminal::class)
            ->where('is_active', true)
            ->latest();
    }

    /**
     * 🛡️ Главный метод фильтрации каталога
     */
    public function canSellProduct(ProviderProduct $product): bool
    {
        if (!$this->is_active) return false;

        // 1. Проверка по юрисдикции (Общие правила)
        // TODO: Добавить таблицу jurisdiction_restrictions для глобальных блокировок
        
        // 2. Проверка персональных фильтров
        if (!$this->allow_all_brands) {
            if (!empty($this->allowed_brands) && !in_array($product->brand_id, $this->allowed_brands)) {
                return false;
            }
            if (!empty($this->allowed_categories) && !in_array($product->category_id, $this->allowed_categories)) {
                return false;
            }
        }

        // 3. Проверка по региональным ограничениям товара
        // Если юрлицо в Узбекистане (UZ), а товар только для USA - блокируем
        if ($this->country_id && !empty($product->mapping_country_id)) {
             // Здесь можно добавить сложную логику совместимости регионов
        }

        return true;
    }

    /**
     * Calculate how much should be deducted from the balance for an order.
     * 
     * @param float $productCost Native cost from provider (e.g., 10.00)
     * @param float $productMSRP Suggested retail price from provider
     * @param string $productCurrency Currency of the cost (e.g., 'USD')
     * @return float Amount to deduct in this LegalEntity's currency
     */
    public function calculateOrderCost(float $productCost, float $productMSRP, string $productCurrency): float
    {
        $standardization = app(\App\Services\StandardizationService::class);
        $finance = app(\App\Services\FinanceService::class);

        // 1. Get the cost for THIS seller in the product's native currency
        // We use dummy Shop for price calculation if not provided, or fallback to LE settings
        $sellerPriceInProductCurrency = $standardization->getPurchasePriceForShop(
            $productCost, 
            $productMSRP,
            $this->shops()->first()
        );

        // 2. Convert from Product Currency to LegalEntity Wallet Currency
        $walletCurrency = $this->currency ?? 'RUB';
        
        return $finance->convert($sellerPriceInProductCurrency, $productCurrency, $walletCurrency);
    }

    /**
     * Deduct Native Network Token balance.
     */
    public function deductNativeBalance(float $amount): void
    {
        if ($this->native_token_balance < $amount) {
            throw new \Exception("Недостаточно средств в нативных токенах. Требуется " . number_format($amount, 4) . " SL1, доступно " . number_format($this->native_token_balance, 4) . " SL1.");
        }
        $this->decrement('native_token_balance', $amount);
    }

    /**
     * Deduct standard fiat RUB balance.
     */
    public function deductRubBalance(float $amount): void
    {
        if ($this->available_balance < $amount) {
            throw new \Exception("Недостаточно средств. Требуется " . number_format($amount, 2) . " RUB, доступно " . number_format($this->available_balance, 2) . " RUB.");
        }
        $this->decrement('available_balance', $amount);
    }
}
