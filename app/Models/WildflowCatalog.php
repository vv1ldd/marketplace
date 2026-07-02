<?php

namespace App\Models;

use App\Services\BrandActivationUrlResolver;
use App\Services\MappingService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WildflowCatalog extends Model
{
    protected $guarded = [];

    protected $casts = [
        'data' => \App\Casts\VaultEncryptedJson::class,
        'is_active' => 'boolean',
        'service_sku' => \App\Casts\VaultEncrypted::class . ':service_sku_bidx',
    ];

    protected static function booted(): void
    {
        static::saving(function ($item) {
            $resolver = app(\App\Services\CanonicalCategoryResolver::class);

            if (empty($item->canonical_category)) {
                $item->canonical_category = $resolver->fromPayload($item->data ?? [], [
                    $item->title,
                    $item->category,
                    $item->reward_type,
                    $item->type,
                ]);
            }

            $item->discovery_intent = $resolver->discoveryIntent(
                (string) $item->canonical_category,
                [
                    $item->brand?->name,
                    $item->title,
                    $item->category,
                    $item->reward_type,
                    $item->type,
                ],
            );
        });

        static::created(function ($item) {
            app(\App\Services\LedgerService::class)->recordGlobal('CATALOG_ITEM_CREATED', $item, [
                'sku' => $item->sku,
                'retail_price' => $item->retail_price,
                'currency' => $item->currency_code,
            ]);
        });

        static::updated(function ($item) {
            $changes = $item->getChanges();
            unset($changes['updated_at']);
            if (empty($changes)) return;

            app(\App\Services\LedgerService::class)->recordGlobal('CATALOG_ITEM_UPDATED', $item, [
                'sku' => $item->sku,
                'changes' => $changes,
                'original' => array_intersect_key($item->getOriginal(), $changes)
            ]);
        });

        static::deleted(function ($item) {
            app(\App\Services\LedgerService::class)->recordGlobal('CATALOG_ITEM_DELETED', $item, [
                'sku' => $item->sku,
            ]);
        });
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function provider()
    {
        return $this->belongsTo(Provider::class);
    }

    public function region()
    {
        return $this->belongsTo(MappingCountry::class, 'region_id');
    }

    public function getBrandNameAttribute(): string
    {
        if ($this->brand_id && $this->brand) {
            return $this->brand->name;
        }

        $categories = data_get($this->data, 'product.categories', data_get($this->data, 'categories', []));
        $categoryName = collect($categories)->pluck('name')->first() ?? $this->category;

        if (!$categoryName && $this->provider_id) {
             return $this->provider?->name ?? 'Catalog';
        }

        return $categoryName ?: 'Catalog';
    }

    public function getBrandLogoUrlAttribute(): ?string
    {
        if ($this->brand_id && $this->brand) {
            return $this->brand->logo_url;
        }

        return null;
    }

    public function getTitleAttribute(): string
    {
        return data_get($this->data, 'product.title')
            ?? data_get($this->data, 'data.product.title')
            ?? data_get($this->data, 'title')
            ?? data_get($this->data, 'data.title')
            ?? data_get($this->data, 'display_name')
            ?? data_get($this->data, 'name')
            ?? $this->sku;
    }

    public function getTitleForShop($shop): string
    {
        if ($shop instanceof LegalEntity) {
            $shop = $shop->shops()->first();
        }
        $locale = $shop?->shop_region ?? 'RU';
        $rewardType = $this->reward_type;
        $brandName = $this->brand_name;

        $regionName = '';
        if ($this->region) {
            $col = 'name_'.strtolower($locale);
            // Fallback to name_en if locale column doesn't exist or is empty
            $regionName = $this->region->$col ?? $this->region->name_en ?? $this->region->name_ru ?? '';
        }

        $translatedRewardType = match (strtolower($rewardType ?? '')) {
            'gift-card', 'gift card' => match ($locale) {
                'GE' => 'სასაჩუქრე ბარათი',
                'ES' => 'Tarjeta de regalo',
                'TR' => 'Hediye kartı',
                'TK' => 'Sowgat karty',
                'EN' => 'Gift Card',
                default => 'Подарочная карта',
            },
            'game-topup', 'game topup' => match ($locale) {
                'GE' => 'ბალანსის შევსება',
                'ES' => 'Recarga',
                'TR' => 'Bakiye Yükleme',
                'TK' => 'Balans Doldurma',
                'EN' => 'Top-up',
                default => 'Пополнение',
            },
            'subscription' => match ($locale) {
                'GE' => 'გამოწერა',
                'ES' => 'Suscripción',
                'TR' => 'Abonelik',
                'TK' => 'Abunelik',
                'EN' => 'Subscription',
                default => 'Подписка',
            },
            default => match ($locale) {
                'GE' => 'ვაუჩერი',
                'ES' => 'Cupón',
                'TR' => 'Kupon',
                'TK' => 'Wauçer',
                'EN' => 'Voucher',
                default => 'Ваучер',
            },
        };

        $suffix = match ($locale) {
            'GE' => '✨ მყისიერი მიწოდება',
            'ES' => '✨ Entrega instantánea',
            'TR' => '✨ Anında teslimat',
            'TK' => '✨ Dessine gowşurma',
            'EN' => '✨ Instant delivery',
            default => '✨ Мгновенная доставка',
        };

        $formattedPrice = rtrim(rtrim(number_format($this->retail_price, 2, '.', ''), '0'), '.');

        $titleParts = array_filter([
            $translatedRewardType,
            $brandName,
            $formattedPrice,
            $this->currency_code,
            $regionName ? "({$regionName})" : '',
        ]);

        return '✅ '.implode(' ', $titleParts).' '.$suffix;
    }

    public function getShortTitleForShop($shop): string
    {
        if ($shop instanceof LegalEntity) {
            $shop = $shop->shops()->first();
        }
        $brandName = $this->brand_name;
        $formattedPrice = rtrim(rtrim(number_format($this->retail_price, 2, '.', ''), '0'), '.');
        $regionName = $this->region ? ($this->region->name_en ?? $this->region->name_ru) : '';

        $titleParts = array_filter([
            $brandName,
            $formattedPrice,
            $this->currency_code,
            $regionName ? "({$regionName})" : '',
        ]);

        return implode(' ', $titleParts);
    }

    public function getMinPriceAttribute(): float
    {
        $val = data_get($this->data, 'data.product.min_price')
            ?? data_get($this->data, 'product.min_price')
            ?? data_get($this->data, 'data.min_price')
            ?? data_get($this->data, 'min_price');

        return $val ? (float) $val : $this->retail_price;
    }

    public function getMaxPriceAttribute(): float
    {
        $val = data_get($this->data, 'data.product.max_price')
            ?? data_get($this->data, 'product.max_price')
            ?? data_get($this->data, 'data.max_price')
            ?? data_get($this->data, 'max_price');

        return $val ? (float) $val : $this->retail_price;
    }

    public function getIsVariablePriceAttribute(): bool
    {
        // If min and max are significantly different, it's a variable price product
        // We use 0.01 to avoid float precision issues
        return $this->min_price > 0 && $this->max_price > $this->min_price + 0.01;
    }

    public function getPurchasePriceAttribute(): float
    {
        if ($this->attributes['purchase_price'] !== null) {
            return (float) $this->attributes['purchase_price'];
        }

        $val = data_get($this->data, 'data.buying_price')
            ?? data_get($this->data, 'buying_price')
            ?? data_get($this->data, 'raw_data.buying_price')
            ?? data_get($this->data, 'data.product.buying_price')
            ?? data_get($this->data, 'product.buying_price')
            ?? data_get($this->data, 'min_price')
            ?? data_get($this->data, 'raw_data.price');

        if ($val !== null) {
            return (float) $val;
        }

        $percentage = data_get($this->data, 'percentage_of_buying_price')
                      ?? data_get($this->data, 'data.percentage_of_buying_price')
                      ?? data_get($this->data, 'raw_data.percentage_of_buying_price');

        if ($percentage !== null) {
            return (float) ($this->retail_price * (1 + (float) $percentage / 100));
        }

        // Absolute safety block: Return infinity-level price to block transactions financially if misconfigured.
        return 999999.0;
    }

    public function getMinPurchasePriceAttribute(): float
    {
        $percentage = data_get($this->data, 'percentage_of_buying_price')
                      ?? data_get($this->data, 'data.percentage_of_buying_price')
                      ?? data_get($this->data, 'raw_data.percentage_of_buying_price');

        if ($percentage !== null) {
            return (float) ($this->min_price * (1 + (float) $percentage / 100));
        }

        // If no percentage, try to find ratio from default prices
        if ($this->retail_price > 0) {
            $ratio = $this->purchase_price / $this->retail_price;

            return $this->min_price * $ratio;
        }

        return $this->purchase_price;
    }

    public function getMaxPurchasePriceAttribute(): float
    {
        $percentage = data_get($this->data, 'percentage_of_buying_price')
                      ?? data_get($this->data, 'data.percentage_of_buying_price')
                      ?? data_get($this->data, 'raw_data.percentage_of_buying_price');

        if ($percentage !== null) {
            return (float) ($this->max_price * (1 + (float) $percentage / 100));
        }

        // If no percentage, try to find ratio from default prices
        if ($this->retail_price > 0) {
            $ratio = $this->purchase_price / $this->retail_price;

            return $this->max_price * $ratio;
        }

        return $this->purchase_price;
    }

    public function getRetailPriceAttribute(): float
    {
        if ($this->attributes['retail_price'] !== null) {
            return (float) $this->attributes['retail_price'];
        }

        return (float) (
            data_get($this->data, 'data.product.price')
            ?? data_get($this->data, 'product.price')
            ?? data_get($this->data, 'data.price')
            ?? data_get($this->data, 'price')
            ?? data_get($this->data, 'data.max_price')
            ?? data_get($this->data, 'max_price')
            ?? data_get($this->data, 'face_value')
            ?? data_get($this->data, 'nominal_price')
            ?? 0
        );
    }

    public function getCurrencyCodeAttribute(): string
    {
        return data_get($this->data, 'data.product.currency.code')
            ?? data_get($this->data, 'product.currency.code')
            ?? data_get($this->data, 'data.currency.code')
            ?? data_get($this->data, 'currency.code')
            ?? data_get($this->data, 'data.currency')
            ?? data_get($this->data, 'currency')
            ?? data_get($this->data, 'face_currency')
            ?? 'USD';
    }

    public function getUpcAttribute($value): ?string
    {
        $upc = $value ?? data_get($this->data, 'product.upc')
            ?? data_get($this->data, 'upc')
            ?? data_get($this->data, 'upc_string')
            ?? data_get($this->data, 'data.product.upc')
            ?? data_get($this->data, 'data.upc');

        return $upc ? (string) $upc : null;
    }

    public function getUpcForShop($shop): ?string
    {
        if ($shop instanceof LegalEntity) {
            $shop = $shop->shops()->first();
        }
        
        if (! $shop) {
            return $this->upc;
        }

        // Use SKU instead of original UPC to guarantee uniqueness per product variant.
        $hash = hash('sha256', $this->sku.'-shop-'.$shop->id);
        $numbers = preg_replace('/[^0-9]/', '', $hash);

        $generatedUpc = substr(str_pad($numbers, 12, '1', STR_PAD_RIGHT), 0, 12);

        // Prevent Excel/Numbers from truncating leading zeros by ensuring it starts with 1-9
        if ($generatedUpc[0] === '0') {
            $generatedUpc[0] = '4';
        }

        return $generatedUpc;
    }

    public function getRewardTypeAttribute($value): ?string
    {
        return $value ?? data_get($this->data, 'product.reward_type_text')
            ?? data_get($this->data, 'reward_type_text')
            ?? data_get($this->data, 'data.product.reward_type_text')
            ?? data_get($this->data, 'data.reward_type_text');
    }

    /**
     * URL официального сервиса активации бренда: поле activation_url или первая ссылка из redemption_instructions.
     */
    public function getActivationServiceUrl(): ?string
    {
        $direct = trim((string) $this->getAttribute('activation_url'));
        if ($direct !== '') {
            return str_starts_with(strtolower($direct), 'http') ? $direct : 'https://'.$direct;
        }

        $instructions = trim((string) $this->getAttribute('redemption_instructions'));
        if ($instructions === '') {
            return null;
        }

        $fromText = MappingService::extractActivationUrlFromText($instructions);
        if ($fromText) {
            return str_starts_with(strtolower($fromText), 'http') ? $fromText : 'https://'.$fromText;
        }

        return $this->fallbackActivationServiceUrl();
    }

    /**
     * Если провайдер не прислал activation_url и в тексте нет ссылки — см. config/brand_activation.php.
     */
    private function fallbackActivationServiceUrl(): ?string
    {
        $brand = strtoupper(trim((string) ($this->brand?->name ?? $this->brand_name ?? '')));
        $haystack = strtoupper($this->title.' '.($this->category ?? ''));

        return BrandActivationUrlResolver::fallbackActivationUrl($brand, $haystack);
    }

    /**
     * Универсальные шаги до получения секретного кода (маркетплейс + бренд). Сырой текст провайдера не показываем.
     */
    public function getFinalInstructionsAttribute(): string
    {
        $marketplaceStep = "1. Перейдите по ссылке из вашего письма (или в личном кабинете), чтобы активировать ваучер и получить секретный код.\n";

        if ($this->getActivationServiceUrl()) {
            return $marketplaceStep."2. После получения кода на странице с кодом нажмите синюю кнопку «На сайт сервиса — активация» — откроется официальный сервис бренда.\n3. Введите секретный код в форме на сайте производителя и завершите активацию.";
        }

        return $marketplaceStep."2. После получения кода откройте официальный сайт бренда и найдите раздел погашения подарочной карты (gift card / redeem) для вашего региона.\n3. Введите секретный код в форме на сайте производителя.";
    }

    /**
     * Текст для /redeem/finish, когда секретный код уже показан: наши универсальные шаги (ссылка бренда — отдельно в activation_url).
     */
    public function redeemFinishCustomerInstructions(): string
    {
        if ($this->getActivationServiceUrl()) {
            return "1. Скопируйте секретный код выше.\n2. Нажмите синюю кнопку «На сайт сервиса — активация» — откроется официальный сайт бренда.\n3. Вставьте код в форму и завершите активацию по подсказкам на странице.";
        }

        return "1. Скопируйте секретный код выше.\n2. Откройте официальный сайт производителя и найдите раздел погашения цифровой карты (redeem / gift card) для вашего региона.\n3. Вставьте код в форму и завершите активацию.";
    }

    public function getPurchasePriceForShop($shopOrEntity): float
    {
        $legalEntity = null;

        if ($shopOrEntity instanceof \App\Models\LegalEntity) {
            $legalEntity = $shopOrEntity;
        } elseif ($shopOrEntity instanceof \App\Models\Shop) {
            $legalEntity = $shopOrEntity->legalEntity;
        }
        
        $technicalCost = $this->purchase_price;
        $tariffA = $technicalCost * 1.01; // Тариф А: Закупка + 1%

        // --- Тариф А: VIP / Wholesale (privileged) ---
        if ($legalEntity && ($legalEntity->tariff_type === 'privileged')) {
            return (float) $tariffA;
        }

        // --- Тариф Б: Standart / Retail (retail) ---
        // Для этого тарифа мы показываем розничную цену, но не ниже нашей закупки с запасом
        return (float) max($this->retail_price, $tariffA);
    }

    public function getYandexHtmlDescription($shop): string
    {
        if ($shop instanceof LegalEntity) {
            $shop = $shop->shops()->first();
        }
        $brandName = $this->brand?->name ?? 'Бренд';
        $regionName = $this->region?->name_ru ?? 'все страны';
        $priceStr = (int) $this->retail_price.' '.$this->currency_code;

        return "<h3>Подарочная карта {$brandName} {$priceStr}</h3>
<p>✅ <b>Мгновенная доставка после оплаты</b><br>
Ваучер с инструкцией отправляется в чат и на вашу почту сразу после покупки. После активации ваучера вы получаете официальный код пополнения.</p>

<b>Что вы получаете:</b>
<ul>
<li>✅ <b>Официальный цифровой код {$brandName}</b></li>
<li>⭐ Работает для региона: <b>{$regionName}</b></li>
<li>⭐ Без привязки к конкретному аккаунту</li>
</ul>

<b>Как это работает:</b>
<ol>
<li>Оплачиваете товар</li>
<li>Получаете ссылку в чат и на почту</li>
<li>Активируете и пользуетесь!</li>
</ol>

<b>Гарантия:</b>
<ul>
<li>✨ <b>100% рабочий код</b></li>
<li>✨ Замена или возврат при возникновении проблем</li>
<li>✨ Быстрая поддержка 24/7</li>
</ul>

<p>❗ <b>Важно:</b> Для активации может потребоваться аккаунт соответствующего региона (<b>{$regionName}</b>). Пишите, если есть вопросы — отвечаем быстро!</p>";
    }

    public function getYandexParameters($shop): array
    {
        if ($shop instanceof LegalEntity) {
            $shop = $shop->shops()->first();
        }
        $brand = strtoupper($this->brand?->name ?? 'DIGITAL');

        // Robust Mapping Standard
        $mapping = match ($brand) {
            'NINTENDO' => ['p' => 'Nintendo',    's' => 'Nintendo eShop',   'n' => 'игры', 'os' => 'Nintendo OS'],
            'STEAM' => ['p' => 'Steam',       's' => 'Steam',            'n' => 'игры', 'os' => 'Windows, macOS, Linux'],
            'XBOX' => ['p' => 'Xbox',        's' => 'Microsoft Store',  'n' => 'игры', 'os' => 'Xbox OS, Windows'],
            'ROBLOX' => ['p' => 'Roblox',      's' => 'Roblox',           'n' => 'игры', 'os' => 'все'],
            'APPLE' => ['p' => 'Apple',       's' => 'App Store',        'n' => 'игры, музыка, обучение', 'os' => 'iOS, macOS'],
            'GOOGLE' => ['p' => 'Google',      's' => 'Google Play Store', 'n' => 'игры, софт', 'os' => 'Android'],
            default => ['p' => $brand,        's' => $brand,             'n' => 'игры', 'os' => 'все']
        };

        return [
            'Регион' => $this->region?->name_ru ?? 'Global',
            'Номинал' => (string) $this->retail_price.' '.$this->currency_code,
            'Платформа' => $mapping['p'],
            'Сервис активации' => $mapping['s'],
            'Назначение' => $mapping['n'],
            'Совместимость с ОС' => $mapping['os'],
            'Вид поставки' => 'электронный код',
            'Применение' => 'пополнение счета',
            'Территория активации' => $this->region?->name_ru ?? 'Global',
            'Тип вознаграждения' => $this->reward_type,
            'Срок доставки' => 'Мгновенно',
            'Формат' => 'Цифровой код',
            'Срок годности' => 'не ограничен',
            'Количество подключаемых аккаунтов' => '1',
        ];
    }

    /**
     * Aggregate all data for Yandex Market listing.
     */
    public function getYandexMetadata($shop): array
    {
        if ($shop instanceof LegalEntity) {
            $shop = $shop->shops()->first();
        }
        $imageService = app(\App\Services\CardImageService::class);
        $kit = $imageService->generateForCatalogItem($this, $shop);
        $htmlDescription = $this->getYandexHtmlDescription($shop);
        $params = $this->getYandexParameters($shop);

        return [
            'name' => $kit['title'] ?? $this->getTitleForShop($shop),
            'vendor' => $this->brand?->name ?? 'No Brand',
            'vendor_code' => $this->sku,
            'barcode' => $this->getUpcForShop($shop),
            'price' => $this->retail_price,
            'currency' => $this->currency_code,
            'description' => $htmlDescription,
            'category' => $this->reward_type === 'Gift-Card' ? 'Подарочные сертификаты' : 'Подписки и карты оплаты',
            'images' => (function () use ($kit, $imageService) {
                // 1. Try uploaded custom cards (без redeem — квадрат только для нашего /redeem)
                $paths = $kit['images'] ?? [];
                unset($paths['redeem']);
                $images = array_values(array_filter(array_map(function ($path) use ($imageService) {
                    $url = $imageService->uploadToImgBB($path);

                    return $url ? $url.(str_contains($url, '?') ? '&' : '?').'v='.time() : null;
                }, array_values(array_filter($paths)))));

                // 2. Fallback to original catalog image (only if it's a public URL)
                if (empty($images) && ! empty($this->image) && str_starts_with($this->image, 'http') && ! str_contains($this->image, 'localhost')) {
                    $images[] = $this->image;
                }

                // 3. Try to find a public image URL in the raw data
                if (empty($images)) {
                    $dataUrl = data_get($this->data, 'image') ?: data_get($this->data, 'data.image') ?: data_get($this->data, 'data.product.image');
                    if ($dataUrl && str_starts_with($dataUrl, 'http') && ! str_contains($dataUrl, 'localhost')) {
                        $images[] = $dataUrl;
                    }
                }

                // 4. Last resort: use brand logo (only if public)
                if (empty($images) && $this->brand_logo_url && str_starts_with($this->brand_logo_url, 'http') && ! str_contains($this->brand_logo_url, 'localhost')) {
                    $images[] = $this->brand_logo_url;
                }

                return $images;
            })(),
            'params' => $params,
        ];
    }

    /**
     * Та же логика, что при ошибке 614: глобальный wildflow_catalog, товары селлеров, остатки на складах, provider_products.
     *
     * @param  string  $context  manual | api_614
     */
    public static function applyProviderOutOfStockToSku(string $catalogSku, string $context = 'manual'): bool
    {
        $catalogSku = trim($catalogSku);
        if ($catalogSku === '') {
            return false;
        }

        $vault = app(\App\Services\VaultTransitService::class);
        $bidx = $vault->computeBlindIndex($catalogSku);

        // wildflow_catalogs.sku is NOT encrypted, but products.wildflow_catalog_sku IS.
        $catalogUpdated = static::query()->where('sku', $catalogSku)->update(['is_active' => false]);
        
        $productsUpdated = Product::query()->where(function ($q) use ($catalogSku, $bidx) {
            $q->where('sku', $catalogSku) // products.sku is NOT encrypted
              ->orWhere('wildflow_catalog_sku_bidx', $bidx);
        })->update(['is_active' => false]);

        $productIds = Product::query()->where(function ($q) use ($catalogSku, $bidx) {
            $q->where('sku', $catalogSku)->orWhere('wildflow_catalog_sku_bidx', $bidx);
        })->pluck('id');
        
        $stocksUpdated = 0;
        if ($productIds->isNotEmpty()) {
            $stocksUpdated = WarehouseStock::query()->whereIn('product_id', $productIds)->update([
                'count' => 0,
                'synced_at' => now(),
            ]);
        }

        $providerRows = 0;
        $wildflowProviderId = Provider::query()->where('type', 'wildflow')->value('id');
        if ($wildflowProviderId) {
            $providerRows = ProviderProduct::query()
                ->where('provider_id', $wildflowProviderId)
                ->where(function ($q) use ($bidx) {
                    $q->where('sku_bidx', $bidx)->orWhere('market_sku_bidx', $bidx);
                })
                ->update(['is_active' => false]);
        }

        $didSomething = $catalogUpdated > 0 || $productsUpdated > 0 || $stocksUpdated > 0 || $providerRows > 0;

        if ($didSomething) {
            Log::warning('Wildflow: снятие с продажи / нулевой сток по SKU', [
                'sku' => $catalogSku,
                'context' => $context,
                'wildflow_catalog_rows' => $catalogUpdated,
                'product_rows' => $productsUpdated,
                'warehouse_stock_rows' => $stocksUpdated,
                'provider_product_rows' => $providerRows,
            ]);

            app(\App\Services\LedgerService::class)->recordGlobal('CATALOG_BATCH_DEACTIVATE', null, [
                'sku' => $catalogSku,
                'context' => $context,
                'results' => [
                    'catalog' => $catalogUpdated,
                    'products' => $productsUpdated,
                    'stocks' => $stocksUpdated,
                    'providers' => $providerRows,
                ]
            ]);
        }

        return $didSomething;
    }

    /**
     * Провайдер вернул «нет карт в наличии» (EzPayPin 614 и т.п.) — снимаем позицию с продажи в нашем каталоге.
     */
    public static function deactivateIfProviderOutOfStock(string $errorMessage, string $catalogSku): bool
    {
        $catalogSku = trim($catalogSku);
        if ($catalogSku === '' || ! static::messageIndicatesNoProviderStock($errorMessage)) {
            return false;
        }

        return static::applyProviderOutOfStockToSku($catalogSku, 'api_614');
    }

    protected static function messageIndicatesNoProviderStock(string $msg): bool
    {
        if (preg_match('/"code"\s*:\s*"614"/', $msg)) {
            return true;
        }
        if (stripos($msg, 'Not enough cards available') !== false) {
            return true;
        }
        if (stripos($msg, 'not enough cards') !== false) {
            return true;
        }

        return false;
    }

    /**
     * SKU из заказа / Маркета: прямое совпадение с wildflow_catalogs.sku, алиас, или товар селлера с wildflow_catalog_sku.
     */
    public static function findForOrderOfferSku(?string $offerSku): ?self
    {
        if ($offerSku === null) {
            return null;
        }
        $offerSku = trim($offerSku);
        if ($offerSku === '') {
            return null;
        }

        $vault = app(\App\Services\VaultTransitService::class);
        $bidx = $vault->computeBlindIndex($offerSku);

        $direct = static::query()->where('sku', $offerSku)->first();
        if ($direct) {
            return $direct;
        }

        $viaAlias = WildflowSkuAlias::query()->where('alias_sku', $offerSku)->value('wildflow_catalog_sku');
        if ($viaAlias) {
            return static::query()->where('sku', $viaAlias)->first();
        }

        $viaProduct = Product::query()->where('sku', $offerSku)
            ->orWhere('wildflow_catalog_sku_bidx', $bidx)
            ->value('wildflow_catalog_sku');

        if ($viaProduct) {
            return static::query()->where('sku', $viaProduct)->first();
        }

        return null;
    }

    /**
     * Generate a neutral, human-readable SKU for sales channels.
     */
    public function suggestOfferSku(string $channel = 'yandex_market'): string
    {
        return app(\App\Services\SkuGeneratorService::class)->forChannel($this, $channel);
    }

    /**
     * Alias for backward compatibility.
     */
    public function suggestYmOfferSku(): string
    {
        return $this->suggestOfferSku('yandex_market');
    }

    private function slugPriceSegment(float $v): string
    {
        $s = rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');

        return str_replace('.', '-', $s);
    }

    private function sanitizeYmOfferSku(string $sku): string
    {
        $sku = preg_replace('/[\x00-\x08\x0A-\x1F\x7F]/', '', $sku) ?? '';
        $sku = trim(preg_replace('/\s+/', '-', $sku) ?? '');
        $sku = preg_replace('/-+/', '-', $sku) ?? '';
        $sku = trim($sku, '-');
        if ($sku === '') {
            return mb_strtoupper('WF-C'.$this->id, 'UTF-8');
        }
        $sku = mb_strtoupper($sku, 'UTF-8');
        if (mb_strlen($sku) > 255) {
            $sku = mb_substr($sku, 0, 255);
            $sku = rtrim($sku, '-');
        }

        return $sku;
    }
}
