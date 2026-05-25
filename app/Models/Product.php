<?php

namespace App\Models;

use App\Services\FinanceService;
use App\Support\PublicAssetUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    /**
     * Поиск товара селлера по SKU оффера (короткий WF-* или старый длинный VOUCHER-*).
     */
    public static function queryByOfferSku(string $sku): \Illuminate\Database\Eloquent\Builder
    {
        $vault = app(\App\Services\VaultTransitService::class);
        $bidx = $vault->computeBlindIndex($sku);

        return static::query()->where(function ($q) use ($sku, $bidx) {
            $q->where('sku', $sku)->orWhere('wildflow_catalog_sku_bidx', $bidx);
        });
    }

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($product) {
            if (empty($product->slug)) {
                $namePart = \Illuminate\Support\Str::limit(\Illuminate\Support\Str::slug(str_replace('.', '-', $product->name)), 150, '');
                $skuPart = \Illuminate\Support\Str::slug(str_replace('.', '-', $product->sku));
                $product->slug = $namePart.'-'.$skuPart;
            }

            if (empty($product->meta_title)) {
                $product->meta_title = $product->name.($product->vendor ? ' | '.$product->vendor : '');
            }

            if (empty($product->meta_description) && ! empty($product->description)) {
                $product->meta_description = \Illuminate\Support\Str::limit(strip_tags($product->description), 160);
            }

            if (empty($product->canonical_category)) {
                $product->canonical_category = app(\App\Services\CanonicalCategoryResolver::class)->fromPayload($product->data ?? [], [
                    $product->name,
                    $product->category,
                    $product->vendor,
                    $product->type,
                ]);
            }
        });
    }

    protected $fillable = [
        'catalog_id',
        'wildflow_catalog_sku',
        'fazer_catalog_sku',
        'provider_id',
        'brand_id',
        'sku',
        'name',
        'slug',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'vendor',
        'vendor_code',
        'barcode',
        'description',
        'type',
        'category',
        'canonical_category',
        'category_id',
        'market_category_name',
        'market_category_id',
        'price_rub',
        'old_price_rub',
        'purchase_price',
        'purchase_currency',
        'purchase_price_rub',
        'additional_expenses_rub',
        'price_competitiveness',
        'base_price',
        'vat',
        'weight',
        'weight_kg',
        'dimensions',
        'length_cm',
        'width_cm',
        'height_cm',
        'image',
        'pictures',
        'videos',
        'is_active',
        'data',
        'downloadable',
        'adult',
        'age',
        'shelf_life',
        'life_time',
        'guarantee_period',
        'manufacturer_countries',
        'params',
        'ym_errors',
        'group_id',
        'image_updated_at',
        'is_manual',
        'ym_url',
        'send_to_ym_at',
        'shop_id',
        'low_stock_notification_threshold',
        'auto_replenish_enabled',
        'auto_replenish_threshold',
        'auto_replenish_quantity',
        'last_low_stock_notification_at',
    ];



    protected $casts = [
        'pictures' => 'array',
        'purchase_price' => 'decimal:2',
        'data' => \App\Casts\VaultEncryptedJson::class,
        'params' => \App\Casts\VaultEncryptedJson::class,
        'ym_errors' => \App\Casts\VaultEncryptedJson::class,
        'videos' => 'array',
        'age' => 'array',
        'shelf_life' => 'array',
        'life_time' => 'array',
        'guarantee_period' => 'array',
        'manufacturer_countries' => 'array',
        'is_active' => 'boolean',
        'is_manual' => 'boolean',
        'downloadable' => 'boolean',
        'adult' => 'boolean',
        'price_competitiveness' => \App\Enums\Yandex\YmPriceCompetitivenessType::class,
        'send_to_ym_at' => 'datetime',
        'last_low_stock_notification_at' => 'datetime',
        'auto_replenish_enabled' => 'boolean',
        'low_stock_notification_threshold' => 'integer',
        'auto_replenish_threshold' => 'integer',
        'auto_replenish_quantity' => 'integer',
        'wildflow_catalog_sku' => \App\Casts\VaultEncrypted::class . ':wildflow_catalog_sku_bidx',
        'fazer_catalog_sku' => \App\Casts\VaultEncrypted::class . ':fazer_catalog_sku_bidx',
    ];

    public function catalogCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }


    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function legalEntity(): \Illuminate\Database\Eloquent\Relations\HasOneThrough
    {
        return $this->hasOneThrough(
            LegalEntity::class,
            Shop::class,
            'id',
            'id',
            'shop_id',
            'legal_entity_id'
        );
    }

    public function stocks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(WarehouseStock::class);
    }

    public function salesChannels(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductSalesChannel::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class, 'provider_id');
    }

    public function directChannels(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(DirectChannel::class)
            ->withPivot(['is_enabled', 'last_synced_at', 'last_error'])
            ->withTimestamps();
    }

    /**
     * Для redeem: не показывать форму PlayStation (существующий PSN / генерация аккаунта) — только Wildflow и аналоги.
     */

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * Get the brand logo URL or a generated avatar with nominal.
     */
    public function getBrandLogoUrlAttribute(): string
    {
        // ALWAYS use the dynamic avatar generator to ensure the nominal overlay is visible.
        // This is critical for the B2B showcase where face value is primary info.
        return $this->generateDefaultAvatarSvg();
    }

    public function getPurchasePriceAttribute($value)
    {
        if ($this->provider_id == 1) {
            return data_get($this->data, 'price') ?? data_get($this->data, 'data.price') ?? $value;
        }

        return $value;
    }

    public function getPurchaseCurrencyAttribute($value)
    {
        if ($this->provider_id == 1) {
            $code = data_get($this->data, 'product.currency.code') ??
                    data_get($this->data, 'data.product.currency.code') ??
                    data_get($this->data, 'currency') ??
                    data_get($this->data, 'data.currency');

            if (is_array($code)) {
                $code = $code['code'] ?? reset($code);
            }

            return is_string($code) ? $code : ($value ?: 'RUB');
        }

        return is_string($value) ? $value : 'RUB';
    }

    public function getNominalValueAttribute(): float
    {
        // 1. Try to extract from structured JSON fields first
        $val = data_get($this->data, 'face_value') ??
               data_get($this->data, 'data.face_value') ??
               data_get($this->data, 'max_price') ??
               data_get($this->data, 'data.max_price') ??
               data_get($this->data, 'amount') ??
               data_get($this->data, 'data.amount');

        if ($val) {
            return (float) $val;
        }

        // 2. Fallback to Regex on name as last resort
        if (preg_match('/(\d+(?:\.\d+)?)\s*(?:EUR|USD|SAR|AED|TRY|RUB|MXN|GBP|BRL|PLN|JPY|INR)/i', $this->name, $matches)) {
            return (float) $matches[1];
        }

        return 0;
    }

    public function getProviderRetailPriceAttribute()
    {
        if ($this->provider_id == 1) {
            // 1. Try to get explicit price from JSON
            $val = data_get($this->data, 'data.product.price') ??
                   data_get($this->data, 'product.price') ??
                   data_get($this->data, 'price') ??
                   data_get($this->data, 'data.price');

            if ($val) {
                if (is_array($val)) {
                    $val = (float) ($val['amount'] ?? $val['value'] ?? $val['price'] ?? reset($val) ?? 0);
                }

                return (float) $val;
            }

            // 2. Fallback to Nominal Value
            return (float) ($this->nominal_value ?: $this->purchase_price ?: 0);
        }

        return (float) ($this->purchase_price ?: 0);
    }

    public function getProviderBuyingPriceAttribute()
    {
        if ($this->provider_id == 1) {
            $val = data_get($this->data, 'buying_price') ??
                   data_get($this->data, 'data.buying_price') ??
                   data_get($this->data, 'data.product.buying_price');

            if ($val) {
                if (is_array($val)) {
                    return (float) ($val['amount'] ?? $val['value'] ?? $val['price'] ?? reset($val) ?? 0);
                }

                return (float) $val;
            }

            // Fallback for Open Denomination (percentage based)
            $percentage = data_get($this->data, 'percentage_of_buying_price') ??
                          data_get($this->data, 'data.percentage_of_buying_price');

            $retail = $this->provider_retail_price;

            if ($percentage !== null) {
                return (float) ($retail * (1 + (float) $percentage / 100));
            }

            return (float) $retail;
        }

        return (float) ($this->purchase_price ?: 0);
    }

    public function getSellerPurchasePrice(Shop $shop): float
    {
        $retail = (float) $this->provider_retail_price;
        $buying = (float) $this->provider_buying_price;

        // --- GLOBAL PROTECTION ---
        // We always add at least 1% to our cost to cover platform fees and risks.
        $safeBuyingPrice = (float) ($buying * 1.01);

        // --- PRIVILEGED SELLERS (Wholesale) ---
        // They get our wholesale price + 1% platform margin.
        if (($shop->tariff_type ?? $shop->price_base) === 'privileged') {
            return $safeBuyingPrice;
        }

        // --- REGULAR SELLERS (Retail) ---
        // They operate based on Recommended Retail Price (MSRP / Nominal)
        // They pay either Nominal or Safe Buying Price (whichever is higher)
        return max($retail, $safeBuyingPrice);
    }

    /**
     * Закупка селлера по данным каталога: сумма в валюте каталога + код валюты + эквивалент в ₽ (если удалось посчитать).
     *
     * Сначала ищем строку в wildflow_catalogs по SKU (товары с витрины YM часто без provider.type=wildflow,
     * но SKU совпадает с каталогом провайдера). Иначе — цены из карточки Product (Wildflow / purchase_price_rub).
     *
     * @return array{amount: float, currency: string, rub: ?float}|null
     */
    public function getSellerPurchaseCatalogForShop(?Shop $shop): ?array
    {
        if (! $shop) {
            if ($this->purchase_price_rub) {
                $rub = round($this->purchase_price_rub / 100, 2);

                return ['amount' => $rub, 'currency' => 'RUB', 'rub' => $rub];
            }

            return null;
        }

        $catalogSkus = array_values(array_unique(array_filter(array_merge(
            array_filter([$this->wildflow_catalog_sku]),
            [
                $this->sku,
                data_get($this->params, 'wf_provider_sku'),
                data_get($this->data, 'product.sku'),
                data_get($this->data, 'data.product.sku'),
            ]
        ))));

        $wfRow = null;
        foreach ($catalogSkus as $catSku) {
            $wfRow = WildflowCatalog::where('sku', $catSku)->first();
            if ($wfRow) {
                break;
            }
        }

        if ($wfRow) {
            $amount = (float) $wfRow->getPurchasePriceForShop($shop);
            $currency = strtoupper((string) ($wfRow->currency_code ?: 'USD'));
            $rubRaw = app(FinanceService::class)->convertToRub($amount, $currency, 0);
            $rub = $rubRaw > 0 ? round((float) $rubRaw, 2) : null;

            return ['amount' => $amount, 'currency' => $currency, 'rub' => $rub];
        }

        $isWildflowProduct = ($this->provider_id && $this->provider?->type === 'wildflow')
            || (int) $this->provider_id === 1
            || $this->type === 'wildflow';

        if ($isWildflowProduct) {
            $amount = $this->getSellerPurchasePrice($shop);
            $currency = strtoupper((string) ($this->purchase_currency ?: 'USD'));
            $rub = null;
            $currencyRow = Currency::where('code', $this->purchase_currency)->first();
            $rate = $currencyRow?->effective_rate ?? 0.0;
            if ($rate <= 0 && $this->purchase_price > 0) {
                $rate = $this->price_rub / ($this->purchase_price * 100);
            }
            if ($rate > 0) {
                $rub = round($amount * $rate, 2);
            }

            return ['amount' => $amount, 'currency' => $currency, 'rub' => $rub];
        }

        if ($this->purchase_price_rub) {
            $rub = round($this->purchase_price_rub / 100, 2);

            return ['amount' => $rub, 'currency' => 'RUB', 'rub' => $rub];
        }

        return null;
    }

    /**
     * Закупочная цена для селлера в рублях (эквивалент; для валюты каталога см. getSellerPurchaseCatalogForShop).
     */
    public function getSellerPurchasePriceRubForShop(?Shop $shop): ?float
    {
        $catalog = $this->getSellerPurchaseCatalogForShop($shop);

        return $catalog['rub'] ?? null;
    }

    /**
     * Generate a beautiful SVG avatar for the product with nominal value.
     */
    public function generateDefaultAvatarSvg(): string
    {
        $name = $this->name ?? 'Product';
        $letter = mb_strtoupper(mb_substr($name, 0, 1));

        // Try to extract nominal (e.g. 100 EUR, 50.5 USD, 150 MXN)
        $nominal = '';
        $currencyList = 'EUR|USD|GBP|RUB|TL|TRY|AED|PLN|KZT|UAH|CHF|CAD|AUD|MXN|BRL|HKD|TWD|MYR|SAR|QAR|KWD|BHD|OMR|COP|CLP|EGP|INR|VND|THB|IDR|PHP';
        if (preg_match('/(\d+[\.,]?\d*)\s*('.$currencyList.')/i', $name, $matches)) {
            $nominal = str_replace(',', '.', $matches[1]).' '.strtoupper($matches[2]);
        } elseif (! empty($this->params['wf_nominal']) && $this->params['wf_nominal'] !== ' ') {
            $nominal = $this->params['wf_nominal'];
        } elseif ($this->purchase_price > 0) {
            $nominal = (float) $this->purchase_price.' '.($this->purchase_currency ?? '');
        }

        $hash = md5($name);
        $hue1 = hexdec(substr($hash, 0, 2)) % 360;
        $hue2 = ($hue1 + 40) % 360;

        $color1 = "hsl({$hue1}, 65%, 45%)";
        $color2 = "hsl({$hue2}, 65%, 35%)";

        $svg = '<svg width="512" height="512" viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <linearGradient id="grad_'.$this->id.'" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" style="stop-color:'.$color1.';stop-opacity:1" />
                    <stop offset="100%" style="stop-color:'.$color2.';stop-opacity:1" />
                </linearGradient>
            </defs>
            <rect width="100%" height="100%" fill="url(#grad_'.$this->id.')" />
            <text x="50%" y="45%" dominant-baseline="middle" text-anchor="middle" font-family="sans-serif" font-size="280" font-weight="900" fill="#ffffff" style="opacity: 0.95">'.$letter.'</text>
            '.($nominal ? '
            <rect x="0" y="360" width="100%" height="152" fill="#000000" fill-opacity="0.3" />
            <text x="50%" y="436" dominant-baseline="middle" text-anchor="middle" font-family="sans-serif" font-size="85" font-weight="900" fill="#ffffff">'.$nominal.'</text>
            ' : '').'
        </svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /**
     * Картинка для страницы redeem: валидный файл из поля image, сгенерированная карточка в public/img/card,
     * URL из каталога Wildflow, иначе — квадратный SVG-плейсхолдер (как generateDefaultAvatarSvg).
     */
    public function getRedeemDisplayImageSrc(): string
    {
        $trimImage = is_string($this->image) ? trim($this->image) : '';
        if ($trimImage !== '') {
            if (str_starts_with($trimImage, 'http://') || str_starts_with($trimImage, 'https://')) {
                $fromOwnHost = PublicAssetUrl::relativePathFromRemoteOwnHost($trimImage);
                if ($fromOwnHost !== null && is_file(public_path($fromOwnHost))) {
                    return asset($fromOwnHost);
                }
                if ($fromOwnHost === null) {
                    return $trimImage;
                }
            } else {
                $rel = ltrim(str_replace('\\', '/', $trimImage), '/');
                if ($rel !== '' && is_file(public_path($rel))) {
                    return asset($rel);
                }
            }
        }

        $skuUrl = is_string($this->sku) && $this->sku !== '' ? self::redeemSkuImageUrl($this->sku) : null;
        if ($skuUrl !== null) {
            return $skuUrl;
        }

        return $this->generateDefaultAvatarSvg();
    }

    /**
     * Картинка по SKU без строки products: сначала сгенерированный JPEG в public/img/card, затем URL из wildflow_catalogs.
     */
    public static function redeemSkuImageUrl(string $sku): ?string
    {
        $sku = trim($sku);
        if ($sku === '') {
            return null;
        }

        $pathSku = WildflowCatalog::findForOrderOfferSku($sku)?->sku ?? $sku;

        $redeemMatches = glob(public_path('img/card/*/'.$pathSku.'_redeem_v3.jpg')) ?: [];
        if ($redeemMatches !== []) {
            $first = str_replace('\\', '/', $redeemMatches[0]);
            $publicRoot = str_replace('\\', '/', public_path());
            if (str_starts_with($first, $publicRoot)) {
                return asset(ltrim(substr($first, strlen($publicRoot)), '/'));
            }
        }

        $matches = glob(public_path('img/card/*/'.$pathSku.'_v3.jpg')) ?: [];
        if ($matches !== []) {
            $first = str_replace('\\', '/', $matches[0]);
            $publicRoot = str_replace('\\', '/', public_path());
            if (str_starts_with($first, $publicRoot)) {
                $rel = ltrim(substr($first, strlen($publicRoot)), '/');

                return asset($rel);
            }
        }

        $wfCatalog = WildflowCatalog::query()->where('sku', $pathSku)->first();
        if ($wfCatalog) {
            $wfImg = data_get($wfCatalog->data, 'data.product.image')
                ?? data_get($wfCatalog->data, 'product.image')
                ?? data_get($wfCatalog->data, 'image')
                ?? data_get($wfCatalog->data, 'data.image');
            if (is_string($wfImg) && $wfImg !== '' && (str_starts_with($wfImg, 'http://') || str_starts_with($wfImg, 'https://'))) {
                $fromOwnHost = PublicAssetUrl::relativePathFromRemoteOwnHost($wfImg);
                if ($fromOwnHost !== null && is_file(public_path($fromOwnHost))) {
                    return asset($fromOwnHost);
                }
                if ($fromOwnHost === null) {
                    return $wfImg;
                }
            }
        }

        return null;
    }

    /**
     * Для redeem, когда нет связанного Product (например dev:issue-sample-voucher): каталог Wildflow или SVG.
     */
    public static function redeemDisplayImageForSku(string $sku): string
    {
        return self::redeemSkuImageUrl($sku) ?? self::redeemPlaceholderDataUriFromSku($sku);
    }

    /**
     * Строка wildflow_catalogs для оффера / Wildflow API (длинный SKU провайдера).
     */
    public function wildflowCatalog(): ?WildflowCatalog
    {
        $cSku = trim((string) ($this->wildflow_catalog_sku ?? ''));
        if ($cSku !== '') {
            $row = WildflowCatalog::query()->where('sku', $cSku)->first();
            if ($row) {
                return $row;
            }
        }

        return WildflowCatalog::query()->where('sku', $this->sku)
            ->orWhere('sku', 'like', '%'.explode('-RTL-', (string) $this->sku)[0].'%')
            ->first();
    }

    /**
     * Квадратный SVG, если в БД нет Product, но есть SKU позиции заказа.
     */
    public static function redeemPlaceholderDataUriFromSku(string $sku): string
    {
        $sku = trim($sku) !== '' ? trim($sku) : '—';
        $hash = md5($sku);
        $hue1 = hexdec(substr($hash, 0, 2)) % 360;
        $hue2 = ($hue1 + 40) % 360;
        $color1 = "hsl({$hue1}, 55%, 42%)";
        $color2 = "hsl({$hue2}, 55%, 32%)";
        $letter = htmlspecialchars(mb_strtoupper(mb_substr($sku, 0, 1)), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $safeSku = htmlspecialchars(mb_substr($sku, 0, 56), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $gradId = 'g_'.substr($hash, 0, 16);
        $svg = '<svg width="512" height="512" viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg">'
            .'<defs><linearGradient id="'.$gradId.'" x1="0%" y1="0%" x2="100%" y2="100%">'
            .'<stop offset="0%" style="stop-color:'.$color1.';stop-opacity:1"/>'
            .'<stop offset="100%" style="stop-color:'.$color2.';stop-opacity:1"/></linearGradient></defs>'
            .'<rect width="100%" height="100%" fill="url(#'.$gradId.')"/>'
            .'<text x="50%" y="42%" dominant-baseline="middle" text-anchor="middle" font-family="sans-serif" font-size="200" font-weight="900" fill="#ffffff">'.$letter.'</text>'
            .'<text x="50%" y="76%" dominant-baseline="middle" text-anchor="middle" font-family="sans-serif" font-size="26" fill="#ffffff" fill-opacity="0.92">'.$safeSku.'</text>'
            .'</svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }



    /**
     * Get human-readable parameters mapped with their names.
     */
    public function getHumanParams(): array
    {
        $humanParams = [];

        // 1. Standard Category Schema Params
        $schema = $this->catalogCategory?->parameters_schema;
        if (! empty($schema) && ! empty($this->params)) {
            $schemaMap = collect($schema)->keyBy('id');
            foreach ($this->params as $id => $value) {
                $paramSchema = $schemaMap->get($id);
                if ($paramSchema) {
                    $label = $paramSchema['name'];
                    $displayValue = is_array($value) ? implode(', ', $value) : $value;
                    $humanParams[$label] = $displayValue.(isset($paramSchema['unit']) ? ' '.$paramSchema['unit'] : '');
                }
            }
        }

        // 2. Wildflow Dynamic Params
        if ($this->provider_id == 1) {
            $humanParams['Номинал провайдера'] = $this->params['wf_nominal'] ?? '';
            $humanParams['Регионы'] = $this->params['wf_regions'] ?? '';
            $humanParams['Категории провайдера'] = $this->params['wf_categories'] ?? '';
            if (! empty($this->params['wf_upc'])) {
                $humanParams['UPC'] = $this->params['wf_upc'];
            }
        }

        return array_filter($humanParams);
    }

    public function toYmOffer(int $marketCategoryId, ?int $shopId = null): array
    {
        $marketCategoryId = (int) ($marketCategoryId ?: $this->market_category_id);
        $data = $this->data;
        $name = $this->name;
        $richDescription = json_decode($this->rich_description ?? '', true);
        $pictures = [];
        $params = [];
        $vendor = 'Нет бренда';

        if ($shopId) {
            $brandedPath = "img/card/sh_{$shopId}/{$this->sku}.png";
            if (file_exists(public_path($brandedPath))) {
                $pictures = [config('app.url').'/'.$brandedPath];
            }
        }

        // Wildflow specific logic
        $wfItem = $this->data;
        $wfProduct = $wfItem['data']['product'] ?? $wfItem;

        if (empty($pictures)) {
            $pictures = [$wfProduct['image'] ?? ''];
            if ($this->image) {
                $pictures = [config('app.url').'/'.$this->image];
            }
        }

        // --- Canonical brand & region from WildflowCatalog (normalized at parse time) ---
        $wfCatalog = $this->wildflowCatalog()?->loadMissing(['brand', 'region']);
        $vendor = $wfCatalog?->brand?->name ?? $wfCatalog?->brand_name ?? explode(' ', $wfProduct['title'] ?? '')[0] ?? 'Digital';
        $regionCode = $wfCatalog?->region?->code ?? data_get($wfProduct, 'regions.0.code', '');
        $regionName = $wfCatalog?->region?->name_ru ?? data_get($wfProduct, 'regions.0.name', 'все страны');

        $ymNominalRub = $this->resolveYmNominalRub($wfItem);

        // Digital version parameters
        $params[] = ['parameter_id' => 37693330, 'value' => 'электронный ключ'];
        if ($ymNominalRub >= 21) {
            $params[] = ['parameter_id' => 37821410, 'value' => $ymNominalRub];
        }
        $params[] = ['parameter_id' => 37919770, 'value' => 'в течение 1 месяца'];
        $params[] = ['parameter_id' => 37972050, 'value' => 'без сервиса активации'];
        $params[] = [
            'parameter_id' => 16382542,
            'value' => 'Ключ предназначен для активации на сервисе активации. Инструкция по активации будет отправлена вам на электронную почту вместе с кодом в течение 10 минут после покупки.',
        ];
        $params[] = ['parameter_id' => 37919810, 'value' => $regionName];

        // Brand-to-platform mapping
        $brand = strtoupper($vendor);
        $mapping = match (true) {
            str_contains($brand, 'NINTENDO') => [
                'p' => 'Nintendo Switch', 'p_id' => 39020434,
                'os' => 'Nintendo OS',
                'service' => 'Nintendo eShop', 'service_id' => 50882082,
            ],
            str_contains($brand, 'STEAM') => [
                'p' => 'ПК', 'p_id' => 39020444,
                'os' => 'Windows, macOS, Linux',
                'service' => 'Steam', 'service_id' => 50882080,
            ],
            str_contains($brand, 'XBOX') || str_contains($brand, 'MICROSOFT') => [
                'p' => 'Xbox Series XIS', 'p_id' => 39020437,
                'os' => 'Xbox OS',
                'service' => 'Xbox Store', 'service_id' => 51434638,
            ],
            str_contains($brand, 'APPLE') || str_contains($brand, 'APP STORE') || str_contains($brand, 'ITUNES') => [
                'p' => 'мобильное устройство', 'p_id' => 39020439,
                'os' => 'iOS, macOS',
                'service' => 'App Store',
            ],
            str_contains($brand, 'GOOGLE') || str_contains($brand, 'PLAY STORE') => [
                'p' => 'мобильное устройство', 'p_id' => 39020439,
                'os' => 'Android',
                'service' => 'Google Play',
            ],
            default => ['p' => $vendor, 'os' => 'все']
        };

        // --- Dynamic parameters based on category ---
        $catLower = mb_strtolower($masterCategory ?? $wfCatalog?->category ?? '');
        $isGaming = str_contains($catLower, 'game') || str_contains($catLower, 'gaming');

        if ($isGaming) {
            $params[] = ['parameter_id' => 27140631, 'value' => 'Игры']; // Genre
            $params[] = ['parameter_id' => 37810090, 'value' => '1']; // Accounts
            $params[] = ['parameter_id' => 37951450, 'value' => '0+']; // Age
        }

        $params[] = ['parameter_id' => 37949750, 'value' => $regionName]; // Territory
        $params[] = ['parameter_id' => 33453610, 'value' => $mapping['os']]; // OS

        $appValue = match (true) {
            $isGaming => ['v' => 'пополнение счета', 'id' => 39047470],
            str_contains($catLower, 'music') || str_contains($catLower, 'movie') || str_contains($catLower, 'video') => ['v' => 'пополнение счета', 'id' => 39047470],
            default => null
        };

        if ($appValue) {
            $params[] = [
                'parameter_id' => 37978250,
                'value' => $appValue['v'],
                'value_id' => $appValue['id'],
            ];
        }

        $purpose = match (true) {
            $isGaming => ['v' => 'игры', 'id' => 39024051],
            str_contains($catLower, 'music') => ['v' => 'музыка', 'id' => 39024048],
            str_contains($catLower, 'movie') || str_contains($catLower, 'video') || str_contains($catLower, 'cinema') => ['v' => 'онлайн-кинотеатр', 'id' => 39024039],
            str_contains($catLower, 'education') || str_contains($catLower, 'learning') => ['v' => 'обучение', 'id' => 39024045],
            default => null
        };

        if ($purpose) {
            $params[] = [
                'parameter_id' => 37948770,
                'value' => $purpose['v'],
                'value_id' => $purpose['id'],
            ];
        }

        $platformParam = ['parameter_id' => 24915630, 'value' => $mapping['p']];
        if (isset($mapping['p_id'])) {
            $platformParam['value_id'] = $mapping['p_id'];
        }
        $params[] = $platformParam;

        $serviceParam = ['parameter_id' => 50882075, 'value' => $mapping['service'] ?? $mapping['p']];
        if (isset($mapping['service_id'])) {
            $serviceParam['value_id'] = $mapping['service_id'];
        }
        $params[] = $serviceParam;

        // Deterministic group name: "<Service> <RegionCode>" e.g. "Nintendo eShop US"
        $groupBase = $mapping['service'] ?? $mapping['p'] ?? $vendor;
        $params[] = [
            'parameter_id' => 200,
            'value' => trim("{$groupBase} {$regionCode}"),
        ];

        $finalPriceKopeks = $this->price_rub;
        if ($shopId) {
            $shop = Shop::find($shopId);
            if ($shop) {
                $finalPriceKopeks = app(\App\Services\FinanceService::class)->getShopFinalPrice($this, $shop);
            }
        }

        // --- SEO Description Generator ---
        $richDescription = "✅ {$this->name}\n\n";
        $richDescription .= "🚀 Мгновенная доставка цифрового кода сразу после оплаты!\n";
        $richDescription .= "✨ Оригинальный подарочный сертификат для пополнения вашего баланса.\n\n";

        $richDescription .= "🔹 Преимущества покупки у нас:\n";
        $richDescription .= "— 100% рабочие коды активации.\n";
        $richDescription .= "— Подробная инструкция в комплекте (в разделе «Инструкции»).\n";
        $richDescription .= "— Гарантия безопасности вашего аккаунта.\n\n";

        if ($this->description) {
            $richDescription .= "📝 Описание товара:\n".strip_tags($this->description)."\n\n";
        }

        $richDescription .= "⚠️ Внимание: Перед покупкой убедитесь, что регион вашего аккаунта соответствует региону карты.\n";
        $richDescription .= 'Желаем приятных покупок и ярких впечатлений!';

        $rawPictures = is_array($this->pictures) ? $this->pictures : (json_decode((string) $this->pictures, true) ?: []);
        if (empty($rawPictures) && $this->image) {
            $rawPictures = ['main' => $this->image];
        }

        // Final fallback: check WildflowCatalog if still empty
        if (empty($rawPictures)) {
            $wfCatalog = $this->wildflowCatalog();

            if ($wfCatalog) {
                $wfImg = data_get($wfCatalog->data, 'data.product.image')
                    ?? data_get($wfCatalog->data, 'product.image')
                    ?? data_get($wfCatalog->data, 'image')
                    ?? data_get($wfCatalog->data, 'data.image');

                if ($wfImg) {
                    $rawPictures = ['main' => $wfImg];
                }
            }
        }

        $imageService = app(\App\Services\CardImageService::class);
        $publicPictures = [];
        $manuals = [];

        foreach ($rawPictures as $key => $p) {
            if (empty($p)) {
                continue;
            }

            $url = $imageService->uploadToImgBB($p);
            if (! $url) {
                continue;
            }

            if ($key === 'instruction') {
                $manuals[] = [
                    'url' => $url,
                    'title' => 'Инструкция по активации',
                ];
            } else {
                $publicPictures[] = $url;
            }
        }

        // --- Tag Generation ---
        // Tags are internal labels (not visible to buyers) for grouping/filtering in the seller catalog
        $tags = [];

        // Brand tag (e.g. "nintendo", "steam")
        if (! empty($vendor)) {
            $tags[] = mb_strtolower(mb_substr(preg_replace('/[^a-zA-Zа-яёА-ЯЁ0-9\s]/u', '', $vendor), 0, 20));
        }

        // Region tag (e.g. "us", "tr", "eu")
        if (! empty($regionCode)) {
            $tags[] = mb_strtolower($regionCode);
        }

        // Category tag from WildflowCatalog (first word only, max 20 chars)
        $masterCategory = $wfCatalog?->master_category ?? $wfCatalog?->category ?? null;
        if ($masterCategory) {
            $firstWord = explode(' ', $masterCategory)[0];
            $catTag = mb_strtolower(mb_substr(preg_replace('/[^a-zA-Zа-яёА-ЯЁ0-9]/u', '', $firstWord), 0, 20));
            if ($catTag) {
                $tags[] = $catTag;
            }
        }

        // Reward type tag (e.g. "giftcard", "subscription")
        $rewardType = $wfCatalog?->reward_type ?? $this->type ?? null;
        if ($rewardType) {
            $tags[] = mb_strtolower(mb_substr(preg_replace('/[^a-zA-Zа-яёА-ЯЁ0-9]/u', '', $rewardType), 0, 20));
        }

        // Platform tag based on mapping (e.g. "nintendo-switch", "ps5")
        $platformTag = match (true) {
            isset($mapping) && str_contains(strtoupper($vendor ?? ''), 'NINTENDO') => 'nintendo-switch',
            isset($mapping) && str_contains(strtoupper($vendor ?? ''), 'PLAYSTATION') => 'playstation',
            isset($mapping) && str_contains(strtoupper($vendor ?? ''), 'XBOX') => 'xbox',
            isset($mapping) && str_contains(strtoupper($vendor ?? ''), 'STEAM') => 'pc-steam',
            default => null
        };
        if ($platformTag) {
            $tags[] = $platformTag;
        }

        // Remove empty, deduplicate, limit to 10
        $tags = array_values(array_unique(array_filter($tags)));
        $tags = array_slice($tags, 0, 10);

        // Ensure at least one picture exists (Yandex requirement)
        if (empty($publicPictures)) {
            // Try to find any picture from rawPictures that isn't empty, even if it was meant for manuals
            foreach ($rawPictures as $p) {
                if (! empty($p)) {
                    $url = $imageService->uploadToImgBB($p);
                    if ($url) {
                        $publicPictures[] = $url;
                        break;
                    }
                }
            }
        }

        if ($marketCategoryId === 989939) {
            $giftCertificateParameterIds = [37821410, 37693330, 16382542, 200];
            $params = array_values(array_filter(
                $params,
                fn (array $param): bool => in_array((int) ($param['parameter_id'] ?? 0), $giftCertificateParameterIds, true)
            ));
        }

        $offer = [
            'offer_id' => $this->sku,
            'name' => $name,
            'market_category_id' => $marketCategoryId,
            'pictures' => ! empty($publicPictures) ? $publicPictures : [$wfImg ?? 'https://storage.wildcloud.ru/media/logos/enhanced/digital.png'],
            'vendor' => $vendor ?? 'Нет бренда',
            'description' => mb_substr($richDescription, 0, 3000),
            'parameter_values' => $params,
            'tags' => ! empty($tags) ? $tags : null,
            'downloadable' => true,
            'basic_price' => [
                'value' => (int) round($finalPriceKopeks / 100),
                'currency_id' => 'RUR',
            ],
        ];

        if (! empty($manuals)) {
            $offer['manuals'] = $manuals;
        }

        // --- Video Generation ---
        try {
            $videoService = app(\App\Services\VideoInstructionService::class);
            $localVideo = storage_path("app/public/videos/{$this->sku}.mp4");

            $shop = \App\Models\Shop::find($shopId);
            $voucherPrefix = $shop?->voucher_prefix ?? 'VOUCHER';

            // Видео: redeem URL из Shop::getEffectiveRedeemUrl / SystemSetting (не APP_URL).
            $videoService->generateForProduct($this, null, $voucherPrefix);

            if (file_exists($localVideo)) {
                $videoUrl = $videoService->uploadToR2($localVideo, $this->sku);
                if ($videoUrl) {
                    $offer['videos'] = [$videoUrl];
                    $offer['first_video_as_cover'] = true;
                }
            }
        } catch (\Exception $e) {
            \Log::warning("Video integration skipped for {$this->sku}: ".$e->getMessage());
        }

        if ($this->old_price_rub && $this->old_price_rub > $finalPriceKopeks) {
            $offer['basic_price']['discount_base'] = round($this->old_price_rub / 100, 2);
        }

        return $offer;
    }

    private function resolveYmNominalRub(array $wfItem): int
    {
        $nominal = $this->nominal_value;

        if ($nominal <= 0) {
            $nominal = (float) (
                data_get($wfItem, 'face_value')
                ?? data_get($wfItem, 'data.face_value')
                ?? data_get($wfItem, 'price')
                ?? data_get($wfItem, 'data.price')
                ?? data_get($wfItem, 'min_price')
                ?? data_get($wfItem, 'data.min_price')
                ?? 0
            );
        }

        if ($nominal <= 0) {
            return 0;
        }

        $currency = strtoupper((string) (
            $this->purchase_currency
            ?: data_get($wfItem, 'currency')
            ?: data_get($wfItem, 'data.currency')
            ?: data_get($wfItem, 'product.currency.code')
            ?: data_get($wfItem, 'data.product.currency.code')
            ?: 'RUB'
        ));

        try {
            $rate = app(FinanceService::class)->getRate($currency);
        } catch (\Throwable) {
            $rate = $currency === 'RUB' ? 1.0 : 0.0;
        }

        if ($rate <= 0) {
            return 0;
        }

        return (int) round($nominal * $rate);
    }

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(Catalog::class);
    }

    /**
     * Scope a query to only include global products allowed for a specific shop.
     */
    public static function scopeGlobalForShop($query, Shop $shop)
    {
        // 🔒 STICKY: Always filter by Global Catalog (ID 1)
        $query->where('catalog_id', 1)
            ->where('is_active', true);

        // 🚀 Global Override: Allow All Brands (but still only from Global Catalog)
        if ($shop->allow_all_brands) {
            return $query;
        }

        $allowedIds = $shop->allowed_categories ?? [];
        if (empty($allowedIds)) {
            return $query->whereRaw('1=0');
        }

        // Ensure IDs are treated as integers
        $allowedIds = array_map('intval', $allowedIds);

        return $query->whereIn('brand_id', $allowedIds);
    }
}
