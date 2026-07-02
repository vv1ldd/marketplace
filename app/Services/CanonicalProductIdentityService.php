<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProviderProduct;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CanonicalProductIdentityService
{
    /**
     * @var array<int, string>
     */
    private const DENOMINATION_CATEGORIES = [
        'gift_cards',
        'console_payment_cards',
        'game_wallet_topups',
        'mobile_app_store_cards',
        'payment_prepaid_cards',
        'telecom_topups',
    ];

    /**
     * @var array<string, string>
     */
    private const KNOWN_BRANDS = [
        'playstation' => 'PlayStation',
        'play station' => 'PlayStation',
        'psn' => 'PlayStation',
        'xbox' => 'Xbox',
        'nintendo' => 'Nintendo',
        'steam' => 'Steam',
        'apple' => 'Apple',
        'itunes' => 'Apple',
        'app store' => 'Apple',
        'google play' => 'Google Play',
        'play store' => 'Google Play',
        'bitdefender' => 'Bitdefender',
        'american express' => 'American Express',
        'amex' => 'American Express',
        'roblox' => 'Roblox',
        'pubg' => 'PUBG',
        'netflix' => 'Netflix',
        'spotify' => 'Spotify',
        'razer gold' => 'Razer Gold',
        'garena' => 'Garena',
        'riot' => 'Riot Games',
        'valorant' => 'Riot Games',
        'league of legends' => 'Riot Games',
        'battle.net' => 'Battle.net',
        'battle net' => 'Battle.net',
        'epic games' => 'Epic Games',
        'microsoft' => 'Microsoft',
        'office' => 'Microsoft',
        'windows' => 'Microsoft',
        'adobe' => 'Adobe',
        'kaspersky' => 'Kaspersky',
    ];

    /**
     * Known commercial/game brands used only for identity-quality checks.
     *
     * Keep this separate from KNOWN_BRANDS so contamination signals do not
     * change existing brand/fingerprint derivation for title-only products.
     *
     * @var array<string, string>
     */
    private const BRAND_TOKEN_RULES = [
        'adobe' => 'Adobe',
        'amex' => 'American Express',
        'american express' => 'American Express',
        'apple' => 'Apple',
        'app store' => 'Apple',
        'battle net' => 'Battle.net',
        'battle.net' => 'Battle.net',
        'bigo' => 'Bigo Live',
        'bigo live' => 'Bigo Live',
        'bitdefender' => 'Bitdefender',
        'blizzard' => 'Battle.net',
        'epic games' => 'Epic Games',
        'free fire' => 'Free Fire',
        'garena' => 'Garena',
        'google play' => 'Google Play',
        'itunes' => 'Apple',
        'kaspersky' => 'Kaspersky',
        'league of legends' => 'Riot Games',
        'microsoft' => 'Microsoft',
        'netflix' => 'Netflix',
        'nintendo' => 'Nintendo',
        'office' => 'Microsoft',
        'play station' => 'PlayStation',
        'playstation' => 'PlayStation',
        'play store' => 'Google Play',
        'psn' => 'PlayStation',
        'pubg' => 'PUBG',
        'razer gold' => 'Razer Gold',
        'riot' => 'Riot Games',
        'roblox' => 'Roblox',
        'spotify' => 'Spotify',
        'steam' => 'Steam',
        'valorant' => 'Riot Games',
        'windows' => 'Microsoft',
        'xbox' => 'Xbox',
    ];

    /**
     * @var array<string, string>
     */
    private const PLATFORM_RULES = [
        'playstation|psn|play station' => 'PlayStation Store',
        'xbox|microsoft store' => 'Xbox Store',
        'nintendo|eshop|e shop' => 'Nintendo eShop',
        'steam' => 'Steam',
        'apple|itunes|app store' => 'App Store',
        'google play|play store' => 'Google Play',
        'roblox' => 'Roblox',
        'pubg' => 'PUBG',
        'razer gold' => 'Razer Gold',
        'battle.net|battle net' => 'Battle.net',
        'epic games' => 'Epic Games',
    ];

    /**
     * @var array<int, string>
     */
    private const GENERIC_IDENTITY_TOKENS = [
        'gift', 'card', 'giftcard', 'voucher', 'code', 'digital', 'instant', 'delivery',
        'email', 'online', 'certificate', 'cert', 'key', 'activation', 'redeem',
        'top', 'up', 'topup', 'wallet', 'prepaid', 'pin', 'subscription',
        'usd', 'eur', 'gbp', 'cad', 'aud', 'aed', 'try', 'tl', 'sar', 'rub', 'rur',
        'kwd', 'qar', 'omr', 'pln', 'jpy', 'inr', 'brl', 'mxn',
        'podarochnaia', 'podarochnaya', 'podarocnaia', 'podarocnaya', 'karta', 'sertifikat', 'vaucher', 'kod',
        'tsifrovoi', 'tsifrovaya', 'tsifrovaia', 'mgnovennaia', 'dostavka',
        'elektronnyi', 'elektronnaia', 'elektronnaya', 'popolnenie', 'balans',
        'ssha', 'ssa',
        'podpiska',
    ];

    public function __construct(
        private readonly CanonicalCategoryResolver $categoryResolver,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forProviderProduct(ProviderProduct $product): array
    {
        $product->loadMissing(['brand', 'region']);

        $payload = $this->safeArray($product->data ?? []);
        $title = trim((string) $product->name);
        $category = $this->categoryResolver->forProviderProduct($product);
        $brand = $this->brand([
            [$product->brand?->name, 'brand:relation'],
            [data_get($payload, 'brand.name'), 'brand:payload'],
            [data_get($payload, 'product.brand.name'), 'brand:payload'],
            [data_get($payload, 'data.product.brand.name'), 'brand:payload'],
            [data_get($payload, 'brand'), 'brand:payload'],
        ], $title);
        $faceValue = $this->faceValue([
            [data_get($payload, 'face_value'), 'face_value:payload'],
            [data_get($payload, 'data.face_value'), 'face_value:payload'],
            [data_get($payload, 'product.face_value'), 'face_value:payload'],
            [data_get($payload, 'data.product.face_value'), 'face_value:payload'],
            [data_get($payload, 'product.price'), 'face_value:payload_price'],
            [data_get($payload, 'data.product.price'), 'face_value:payload_price'],
            [data_get($payload, 'amount'), 'face_value:payload'],
            [data_get($payload, 'data.amount'), 'face_value:payload'],
            [$product->retail_price, 'face_value:retail_price'],
            [$product->max_price ?: $product->min_price, 'face_value:range_price'],
        ], $title);
        $currency = $this->currency([
            [data_get($payload, 'product.currency.code'), 'currency:payload'],
            [data_get($payload, 'data.product.currency.code'), 'currency:payload'],
            [data_get($payload, 'currency.code'), 'currency:payload'],
            [data_get($payload, 'currency'), 'currency:payload'],
            [$product->currency, 'currency:model'],
        ], $title);
        $region = $this->region([
            [$product->region?->code, $product->region?->name_en ?? $product->region?->name_ru, 'region:relation'],
            [data_get($payload, 'product.regions.0.code'), data_get($payload, 'product.regions.0.name'), 'region:payload'],
            [data_get($payload, 'data.product.regions.0.code'), data_get($payload, 'data.product.regions.0.name'), 'region:payload'],
            [data_get($payload, 'region.code'), data_get($payload, 'region.name'), 'region:payload'],
            [data_get($payload, 'country_code'), data_get($payload, 'country'), 'region:payload'],
        ], $title);

        return $this->identity([
            'title' => $title,
            'brand' => $brand,
            'face_value' => $faceValue,
            'face_value_currency' => $currency,
            'region' => $region,
            'canonical_category' => $category,
            'platform' => $this->platform($brand['value'] ?? null, $title),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function forProduct(Product $product): array
    {
        $product->loadMissing(['brand', 'provider']);

        $payload = $this->safeArray($product->data ?? []);
        $params = $this->safeArray($product->params ?? []);
        $catalog = null;

        try {
            $catalog = $product->wildflowCatalog()?->loadMissing(['brand', 'region']);
        } catch (\Throwable) {
            $catalog = null;
        }

        $catalogPayload = $this->safeArray($catalog?->data ?? []);
        $title = trim((string) $product->name);
        $category = $this->categoryResolver->forProduct($product);
        $brand = $this->brand([
            [$product->brand?->name, 'brand:relation'],
            [$catalog?->brand?->name, 'brand:catalog_relation'],
            [$product->vendor, 'brand:vendor'],
            [data_get($payload, 'brand.name'), 'brand:payload'],
            [data_get($payload, 'product.brand.name'), 'brand:payload'],
            [data_get($catalogPayload, 'data.product.brand.name'), 'brand:catalog_payload'],
        ], $title);
        $faceValue = $this->faceValue([
            [data_get($payload, 'face_value'), 'face_value:payload'],
            [data_get($payload, 'data.face_value'), 'face_value:payload'],
            [data_get($payload, 'product.face_value'), 'face_value:payload'],
            [data_get($payload, 'data.product.face_value'), 'face_value:payload'],
            [data_get($payload, 'product.price'), 'face_value:payload_price'],
            [data_get($payload, 'data.product.price'), 'face_value:payload_price'],
            [data_get($params, 'wf_nominal'), 'face_value:params'],
            [data_get($catalogPayload, 'data.product.price'), 'face_value:catalog_payload'],
            [$product->nominal_value > 0 ? $product->nominal_value : null, 'face_value:model_nominal'],
            [$product->purchase_price > 0 ? $product->purchase_price : null, 'face_value:purchase_price'],
        ], $title.' '.(string) $product->sku);
        $currency = $this->currency([
            [$product->purchase_currency, 'currency:model'],
            [data_get($payload, 'product.currency.code'), 'currency:payload'],
            [data_get($payload, 'data.product.currency.code'), 'currency:payload'],
            [data_get($catalogPayload, 'data.product.currency.code'), 'currency:catalog_payload'],
            [data_get($catalogPayload, 'currency_code'), 'currency:catalog_payload'],
        ], $title.' '.(string) $product->sku);
        $region = $this->region([
            [$catalog?->region?->code, $catalog?->region?->name_en ?? $catalog?->region?->name_ru, 'region:catalog_relation'],
            [data_get($payload, 'product.regions.0.code'), data_get($payload, 'product.regions.0.name'), 'region:payload'],
            [data_get($payload, 'data.product.regions.0.code'), data_get($payload, 'data.product.regions.0.name'), 'region:payload'],
            [data_get($catalogPayload, 'data.product.regions.0.code'), data_get($catalogPayload, 'data.product.regions.0.name'), 'region:catalog_payload'],
            [null, data_get($catalogPayload, 'region'), 'region:catalog_payload'],
        ], $title);

        return $this->identity([
            'title' => $title,
            'brand' => $brand,
            'face_value' => $faceValue,
            'face_value_currency' => $currency,
            'region' => $region,
            'canonical_category' => $category,
            'platform' => $this->platform($brand['value'] ?? null, $title),
        ]);
    }

    /**
     * @param  Collection<int, ProviderProduct>  $products
     * @return Collection<int, array<string, mixed>>
     */
    public function groupProviderProducts(Collection $products): Collection
    {
        return $products
            ->map(function (ProviderProduct $product) {
                $identity = $this->forProviderProduct($product);

                return [
                    'product' => $product,
                    'identity' => $identity,
                ];
            })
            ->groupBy(fn (array $row) => $row['identity']['fingerprint'])
            ->map(function (Collection $rows) {
                $identity = $rows
                    ->sortBy(fn (array $row) => ['high' => 0, 'medium' => 1, 'low' => 2][$row['identity']['confidence']] ?? 3)
                    ->first()['identity'];
                $products = $rows->pluck('product');

                return [
                    'fingerprint' => $identity['fingerprint'],
                    'identity_slug' => $identity['identity_slug'],
                    'confidence' => $identity['confidence'],
                    'canonical_identity' => $identity,
                    'candidate_count' => $products->count(),
                    'source_count' => $products->pluck('provider_id')->filter()->unique()->count(),
                    'candidate_ids' => $products->pluck('id')->values(),
                    'sample_names' => $products->pluck('name')->filter()->unique()->take(5)->values(),
                ];
            })
            ->sortByDesc('candidate_count')
            ->values();
    }

    /**
     * @param  array<string, mixed>  $parts
     * @return array<string, mixed>
     */
    private function identity(array $parts): array
    {
        $brand = $parts['brand']['value'] ?? null;
        $faceValue = $parts['face_value']['value'] ?? null;
        $currency = $parts['face_value_currency']['value'] ?? null;
        $region = $parts['region']['value'] ?? null;
        $regionCode = $parts['region']['code'] ?? null;
        $category = (string) ($parts['canonical_category'] ?: config('catalog_taxonomy.default', 'gift_cards'));
        $platform = $parts['platform']['value'] ?? null;
        $title = (string) ($parts['title'] ?? '');
        $discoveryIntent = $this->categoryResolver->discoveryIntent($category, [
            $brand,
            $title,
            $platform,
        ]);
        $productFamily = $this->productFamily($title, $brand, $currency, $region, $regionCode, $faceValue);
        $regionSlug = $this->slugPart($regionCode ?: $region ?: 'global') ?: 'global';
        $brandSlug = $this->slugPart($brand ?: '');
        $familySlug = $this->slugPart($productFamily ?: $brand ?: 'product');
        $platformSlug = $this->slugPart($platform ?: '');
        $amount = $faceValue !== null ? $this->amountKey((float) $faceValue) : null;
        $hashParts = [
            'version' => 'canonical_product_identity_v1',
            'category' => $this->slugPart($category),
            'brand' => $brandSlug ?: 'unknown',
            'family' => $familySlug ?: 'unknown',
            'face_value' => $amount ?: 'variable',
            'currency' => $currency ?: 'unknown',
            'region' => $regionSlug,
            'platform' => $platformSlug ?: 'unknown',
        ];
        $identitySlug = collect([
            $brandSlug,
            $familySlug !== $brandSlug ? $familySlug : null,
            $amount ? $amount.'-'.Str::lower((string) $currency) : null,
            $regionSlug !== 'global' ? $regionSlug : null,
            $hashParts['category'],
        ])->filter()->implode('-');

        $signals = collect([
            $parts['brand']['signal'] ?? null,
            $parts['face_value']['signal'] ?? null,
            $parts['face_value_currency']['signal'] ?? null,
            $parts['region']['signal'] ?? null,
            $parts['platform']['signal'] ?? null,
            $category ? 'canonical_category:model' : null,
            $productFamily !== '' ? 'product_family:title' : null,
        ])
            ->merge($this->contaminationSignals($title, $productFamily, $brand))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return [
            'fingerprint' => 'cp_'.substr(hash('sha256', json_encode($hashParts, JSON_UNESCAPED_SLASHES) ?: ''), 0, 24),
            'identity_slug' => Str::limit($identitySlug ?: 'product-'.$hashParts['category'], 120, ''),
            'brand' => $brand,
            'product_family' => $productFamily !== '' ? $productFamily : null,
            'face_value' => $faceValue,
            'face_value_currency' => $currency,
            'region' => $region,
            'region_code' => $regionCode,
            'platform' => $platform,
            'canonical_category' => $category,
            'discovery_intent' => $discoveryIntent,
            'confidence' => $this->confidence($hashParts, $category, $signals),
            'signals' => $signals,
        ];
    }

    /**
     * @param  array<int, array{0:mixed,1:string}>  $candidates
     * @return array{value:?string,signal:?string}
     */
    private function brand(array $candidates, string $title): array
    {
        $masterBrand = MappingService::normalizeBrandName($title);
        if ($masterBrand !== null) {
            return ['value' => $masterBrand, 'signal' => 'brand:master_lexicon'];
        }

        foreach ($candidates as [$value, $signal]) {
            $value = $this->stringValue($value);
            if ($value === null) {
                continue;
            }

            $display = $this->displayLabel($value);
            if (MappingService::isGenericExternalBrandName($display)
                || ! $this->containsNormalizedPhrase($title, $display)) {
                $masterFromContext = MappingService::normalizeBrandName(trim($display.' '.$title));
                if ($masterFromContext !== null && strcasecmp($masterFromContext, $display) !== 0) {
                    return ['value' => $masterFromContext, 'signal' => 'brand:master_override:'.$signal];
                }
            }

            return ['value' => $display, 'signal' => $signal];
        }

        $normalizedTitle = $this->normalizeText($title);
        foreach (self::KNOWN_BRANDS as $needle => $brand) {
            if ($needle !== '' && str_contains($normalizedTitle, $this->normalizeText($needle))) {
                return ['value' => $brand, 'signal' => 'brand:known_name'];
            }
        }

        $fallback = Str::of($title)
            ->replace(['✅', '✨', '🎮', '💳'], '')
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->trim()
            ->explode(' ')
            ->filter(function (string $token) {
                $token = trim($token);

                return strlen($token) > 2 && ! is_numeric($token) && ! in_array($token, self::GENERIC_IDENTITY_TOKENS, true);
            })
            ->take(2)
            ->implode(' ');

        return $fallback !== ''
            ? ['value' => $this->displayLabel($fallback), 'signal' => 'brand:title_prefix']
            : ['value' => null, 'signal' => null];
    }

    /**
     * @param  array<int, array{0:mixed,1:string}>  $candidates
     * @return array{value:?float,signal:?string}
     */
    private function faceValue(array $candidates, string $title): array
    {
        foreach ($candidates as [$value, $signal]) {
            $number = $this->numberValue($value);
            if ($number !== null && $number > 0) {
                return ['value' => $number, 'signal' => $signal];
            }
        }

        if (preg_match('/(?<![a-z0-9])(\d+(?:[\.,]\d{1,2})?)\s*(?:USD|EUR|GBP|CAD|AUD|AED|TRY|TL|SAR|RUB|RUR|KWD|QAR|OMR|PLN|JPY|INR|BRL|MXN|₽|руб)\b/iu', $title, $match)) {
            return ['value' => (float) str_replace(',', '.', $match[1]), 'signal' => 'face_value:title'];
        }

        if (preg_match('/(?:\$|€|£)\s*(\d+(?:[\.,]\d{1,2})?)/u', $title, $match)) {
            return ['value' => (float) str_replace(',', '.', $match[1]), 'signal' => 'face_value:title_symbol'];
        }

        return ['value' => null, 'signal' => null];
    }

    /**
     * @param  array<int, array{0:mixed,1:string}>  $candidates
     * @return array{value:?string,signal:?string}
     */
    private function currency(array $candidates, string $title): array
    {
        foreach ($candidates as [$value, $signal]) {
            $currency = $this->currencyCode($this->stringValue($value));
            if ($currency !== null) {
                return ['value' => $currency, 'signal' => $signal];
            }
        }

        if (preg_match('/\b(USD|EUR|GBP|CAD|AUD|AED|TRY|TL|SAR|RUB|RUR|KWD|QAR|OMR|PLN|JPY|INR|BRL|MXN)\b/iu', $title, $match)) {
            return ['value' => $this->currencyCode($match[1]), 'signal' => 'currency:title'];
        }

        foreach (['$' => 'USD', '€' => 'EUR', '£' => 'GBP', '₽' => 'RUB'] as $symbol => $currency) {
            if (str_contains($title, $symbol)) {
                return ['value' => $currency, 'signal' => 'currency:title_symbol'];
            }
        }

        return ['value' => null, 'signal' => null];
    }

    /**
     * @param  array<int, array{0:mixed,1:mixed,2:string}>  $candidates
     * @return array{value:?string,code:?string,signal:?string}
     */
    private function region(array $candidates, string $title): array
    {
        foreach ($candidates as [$code, $name, $signal]) {
            $code = $this->regionCode($this->stringValue($code));
            $name = $this->stringValue($name);
            if ($code !== null || $name !== null) {
                return [
                    'value' => $this->displayLabel($name ?: $code),
                    'code' => $code,
                    'signal' => $signal,
                ];
            }
        }

        $normalizedTitle = $this->normalizeText($title);
        $aliases = [
            'global' => ['global', 'worldwide', 'ww', 'all regions', 'vse strany', 'globalnyi'],
            'US' => ['us', 'usa', 'united states', 'america', 'amerika', 'ssha', 'ssa'],
            'EU' => ['eu', 'europe', 'european union', 'evropa'],
            'TR' => ['tr', 'turkey', 'turkiye', 'türkiye', 'turtsiia'],
            'RU' => ['ru', 'russia', 'rossiia', 'rossiya'],
            'AE' => ['ae', 'uae', 'united arab emirates'],
            'SA' => ['sa', 'ksa', 'saudi arabia'],
            'GB' => ['gb', 'uk', 'united kingdom'],
        ];

        foreach ($aliases as $code => $needles) {
            foreach ($needles as $needle) {
                if (preg_match('/(^|\s)'.preg_quote($this->normalizeText($needle), '/').'($|\s)/', $normalizedTitle)) {
                    return [
                        'value' => $code === 'global' ? 'global' : $code,
                        'code' => $code === 'global' ? null : $code,
                        'signal' => 'region:title',
                    ];
                }
            }
        }

        return ['value' => 'global', 'code' => null, 'signal' => 'region:default_global'];
    }

    /**
     * @return array{value:?string,signal:?string}
     */
    private function platform(?string $brand, string $title): array
    {
        $text = $this->normalizeText(trim((string) $brand.' '.$title));
        foreach (self::PLATFORM_RULES as $needles => $platform) {
            foreach (explode('|', $needles) as $needle) {
                if (str_contains($text, $this->normalizeText($needle))) {
                    return ['value' => $platform, 'signal' => 'platform:known_name'];
                }
            }
        }

        return $brand !== null && $brand !== ''
            ? ['value' => $brand, 'signal' => 'platform:brand']
            : ['value' => null, 'signal' => null];
    }

    private function productFamily(string $title, ?string $brand, ?string $currency, ?string $region, ?string $regionCode, ?float $faceValue): string
    {
        $text = $this->normalizeText($title);
        $remove = self::GENERIC_IDENTITY_TOKENS;

        foreach ([$brand, $currency, $region, $regionCode] as $part) {
            $slug = $this->slugPart((string) $part);
            if ($slug !== '') {
                $remove = array_merge($remove, explode('-', $slug));
            }
        }

        if ($faceValue !== null) {
            $remove[] = $this->amountKey($faceValue);
        }

        $tokens = collect(explode(' ', $text))
            ->map(fn (string $token) => trim($token))
            ->filter(fn (string $token) => $token !== '' && ! is_numeric($token))
            ->reject(fn (string $token) => in_array($token, $remove, true))
            ->unique()
            ->take(5)
            ->values();

        if ($tokens->isEmpty() && $brand !== null && $brand !== '') {
            return $this->normalizeText($brand);
        }

        return $tokens->implode(' ');
    }

    /**
     * @param  array<string, string>  $hashParts
     * @param  array<int, string>  $signals
     */
    private function confidence(array $hashParts, string $category, array $signals): string
    {
        $score = 0;
        $score += $hashParts['brand'] !== 'unknown' ? 2 : 0;
        $score += $hashParts['family'] !== 'unknown' ? 1 : 0;
        $score += $hashParts['category'] !== '' ? 1 : 0;
        $score += $hashParts['face_value'] !== 'variable' && $hashParts['currency'] !== 'unknown' ? 2 : 0;
        $score += $hashParts['region'] !== 'global' ? 1 : 0;
        $score += $hashParts['platform'] !== 'unknown' ? 1 : 0;

        $requiresDenomination = in_array($category, self::DENOMINATION_CATEGORIES, true);
        $hasStrongBrand = collect($signals)->contains(fn (string $signal) => in_array($signal, [
            'brand:relation',
            'brand:catalog_relation',
            'brand:vendor',
            'brand:known_name',
        ], true));
        $hasBrandNotInTitle = in_array('brand_not_in_title', $signals, true);
        $hasBrandFamilyMismatch = collect($signals)->contains(fn (string $signal) => str_starts_with($signal, 'brand_family_mismatch'));
        $hasMultipleBrandTokens = collect($signals)->contains(fn (string $signal) => str_starts_with($signal, 'multiple_brand_tokens'));
        $maxConfidence = 'high';

        if ($hasBrandFamilyMismatch && $hasBrandNotInTitle) {
            $maxConfidence = 'low';
        } elseif ($hasBrandFamilyMismatch || $hasMultipleBrandTokens || $hasBrandNotInTitle) {
            $maxConfidence = 'medium';
        }

        if ($score >= 6 && $hasStrongBrand && (! $requiresDenomination || $hashParts['face_value'] !== 'variable')) {
            return $this->capConfidence('high', $maxConfidence);
        }

        if ($requiresDenomination && $hashParts['face_value'] === 'variable') {
            return $this->capConfidence($score >= 4 && $hasStrongBrand ? 'medium' : 'low', $maxConfidence);
        }

        $hasWeakBrand = collect($signals)->contains('brand:title_prefix');
        if ($score >= 4 && $hashParts['brand'] !== 'unknown' && (! $hasWeakBrand || $score >= 5)) {
            return $this->capConfidence('medium', $maxConfidence);
        }

        return 'low';
    }

    /**
     * @return array<int, string>
     */
    private function contaminationSignals(string $title, string $productFamily, ?string $brand): array
    {
        $signals = [];
        $text = trim($title.' '.$productFamily);
        $detectedBrands = $this->knownBrandTokensIn($text);
        $detectedBrandsWithResolvedBrand = $this->knownBrandTokensIn(trim((string) $brand.' '.$text));

        if (count($detectedBrandsWithResolvedBrand) > 1) {
            $signals[] = 'multiple_brand_tokens:'.implode('|', $detectedBrandsWithResolvedBrand);
        }

        $brand = trim((string) $brand);
        if ($brand === '') {
            return $signals;
        }

        $brandInTitleOrFamily = $this->containsNormalizedPhrase($text, $brand);
        if (! $brandInTitleOrFamily) {
            $signals[] = 'brand_not_in_title';
        }

        $canonicalBrand = $this->canonicalKnownBrand($brand);
        $brandKey = $this->normalizeText((string) ($canonicalBrand ?: $brand));
        $otherDetectedBrands = collect($detectedBrands)
            ->reject(fn (string $detectedBrand) => $this->normalizeText($detectedBrand) === $brandKey)
            ->values()
            ->all();

        if ($otherDetectedBrands !== [] && ($canonicalBrand !== null || ! $brandInTitleOrFamily)) {
            $signals[] = 'brand_family_mismatch:'.implode('|', $otherDetectedBrands);
        }

        return $signals;
    }

    /**
     * @return array<int, string>
     */
    private function knownBrandTokensIn(string $value): array
    {
        $normalized = ' '.$this->normalizeText($value).' ';
        $matches = [];

        foreach (self::BRAND_TOKEN_RULES as $needle => $brand) {
            $needle = ' '.$this->normalizeText($needle).' ';
            if ($needle !== '  ' && str_contains($normalized, $needle)) {
                $matches[$brand] = $brand;
            }
        }

        return array_values($matches);
    }

    private function canonicalKnownBrand(string $value): ?string
    {
        $matches = $this->knownBrandTokensIn($value);

        return $matches[0] ?? null;
    }

    private function containsNormalizedPhrase(string $haystack, string $needle): bool
    {
        $normalizedNeedle = $this->normalizeText($needle);
        if ($normalizedNeedle === '') {
            return false;
        }

        return str_contains(' '.$this->normalizeText($haystack).' ', ' '.$normalizedNeedle.' ');
    }

    private function capConfidence(string $confidence, string $maxConfidence): string
    {
        $rank = [
            'low' => 0,
            'medium' => 1,
            'high' => 2,
        ];

        return ($rank[$confidence] ?? 0) > ($rank[$maxConfidence] ?? 2)
            ? $maxConfidence
            : $confidence;
    }

    /**
     * @param  array<string, mixed>|mixed  $value
     * @return array<string, mixed>
     */
    private function safeArray(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    private function stringValue(mixed $value): ?string
    {
        if (is_scalar($value)) {
            $value = trim((string) $value);

            return $value !== '' ? $value : null;
        }

        if (is_array($value)) {
            foreach (['code', 'name', 'name_en', 'name_ru', 'title', 'value', 'amount', 'price'] as $key) {
                if (array_key_exists($key, $value)) {
                    $nested = $this->stringValue($value[$key]);
                    if ($nested !== null) {
                        return $nested;
                    }
                }
            }
        }

        return null;
    }

    private function numberValue(mixed $value): ?float
    {
        if (is_array($value)) {
            foreach (['amount', 'value', 'price', 'nominal'] as $key) {
                if (array_key_exists($key, $value)) {
                    $number = $this->numberValue($value[$key]);
                    if ($number !== null) {
                        return $number;
                    }
                }
            }
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        if (is_string($value) && preg_match('/\d/u', $value)) {
            $normalized = str_replace(',', '.', preg_replace('/[^\d,\.]/u', '', $value) ?? '');

            return is_numeric($normalized) ? (float) $normalized : null;
        }

        return null;
    }

    private function currencyCode(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $currency = strtoupper(trim($value));
        $currency = match ($currency) {
            'RUR' => 'RUB',
            'TL' => 'TRY',
            default => $currency,
        };

        return preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
    }

    private function regionCode(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = strtoupper(trim($value));

        return preg_match('/^[A-Z]{2,3}$/', $value) ? $value : null;
    }

    private function displayLabel(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return preg_match('/[a-z]/', $value)
            ? Str::of($value)->squish()->title()->toString()
            : Str::of($value)->squish()->toString();
    }

    private function normalizeText(string $value): string
    {
        return Str::of($value)
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->toString();
    }

    private function slugPart(string $value): string
    {
        return Str::of($this->normalizeText($value))
            ->replace(' ', '-')
            ->trim('-')
            ->toString();
    }

    private function amountKey(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
