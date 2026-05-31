<?php

namespace App\Services;

use App\Models\CanonicalProductIdentity;
use App\Models\CanonicalProductSearchProfile;
use Illuminate\Support\Str;

class CanonicalProductSearchProfileBuilder
{
    public const PROFILE_VERSION = 5;

    /**
     * @var array<string, array<int, string>>
     */
    private const BRAND_ALIASES = [
        'apple' => ['app store', 'itunes', 'эпл', 'апп стор', 'айтюнс'],
        'battle.net' => ['battle net', 'blizzard'],
        'google play' => ['google', 'play store', 'гугл плей'],
        'microsoft' => ['windows', 'office', 'майкрософт', 'виндовс', 'офис'],
        'nintendo' => ['switch', 'nintendo switch', 'нинтендо', 'свитч', 'нинтендо свитч'],
        'playstation' => ['sony', 'ps', 'psn', 'play station', 'сони', 'плейстейшн', 'плейстейшен', 'плейстешн', 'псн'],
        'spotify' => ['spotify premium', 'premium', 'спотифай', 'спотифи', 'премиум'],
        'steam' => ['steam wallet', 'стим', 'стим валлет'],
        'xbox' => ['microsoft', 'xbox live', 'иксбокс', 'хбокс', 'икс бокс'],
    ];

    /**
     * @var array<string, array<int, string>>
     */
    private const REGION_ALIASES = [
        'AR' => ['argentina', 'ar', 'аргентина'],
        'BR' => ['brazil', 'brasil', 'br'],
        'EU' => ['europe', 'eur', 'eu', 'европа', 'евро'],
        'GB' => ['uk', 'united kingdom', 'great britain', 'gb'],
        'RU' => ['russia', 'ru', 'россия', 'рф'],
        'TR' => ['turkey', 'turkiye', 'tr', 'турция'],
        'US' => ['usa', 'us', 'united states', 'america', 'сша', 'америка', 'штаты'],
    ];

    /**
     * @var array<string, string>
     */
    private const REGION_CANONICALS = [
        'america' => 'US',
        'argentina' => 'AR',
        'ar' => 'AR',
        'br' => 'BR',
        'brasil' => 'BR',
        'brazil' => 'BR',
        'eu' => 'EU',
        'europe' => 'EU',
        'eur' => 'EU',
        'gb' => 'GB',
        'great britain' => 'GB',
        'ru' => 'RU',
        'russia' => 'RU',
        'tr' => 'TR',
        'turkey' => 'TR',
        'turkiye' => 'TR',
        'uk' => 'GB',
        'united kingdom' => 'GB',
        'united states' => 'US',
        'usa' => 'US',
        'us' => 'US',
    ];

    /**
     * @var array<string, array<int, string>>
     */
    private const CATEGORY_ALIASES = [
        'console_payment_cards' => ['console', 'gaming console', 'payment card', 'консоль', 'игровая консоль', 'карта оплаты'],
        'game_wallet_topups' => ['top up', 'top-up', 'gaming wallet', 'game currency', 'пополнение', 'игровой кошелек', 'игровая валюта'],
        'gift_cards' => ['gift card', 'digital card', 'voucher', 'подарочная карта', 'гифт карта', 'ваучер', 'сертификат'],
        'mobile_app_store_cards' => ['app store', 'mobile store', 'mobile gift card'],
        'payment_prepaid_cards' => ['prepaid card', 'payment card'],
        'software_licenses' => ['software key', 'license key', 'activation key'],
        'subscriptions' => ['subscription', 'premium', 'membership', 'подписка', 'премиум'],
    ];

    public function __construct(
        private readonly CanonicalProductIdentityCurationService $curation,
        private readonly CanonicalCategoryResolver $categoryResolver,
    ) {}

    public function rebuild(CanonicalProductIdentity $identity): CanonicalProductSearchProfile
    {
        $payload = $this->build($identity);

        return CanonicalProductSearchProfile::query()->updateOrCreate(
            ['canonical_product_identity_id' => $identity->id],
            $payload + [
                'profile_version' => self::PROFILE_VERSION,
                'last_rebuild_at' => now(),
                'last_error' => null,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function build(CanonicalProductIdentity $identity): array
    {
        $facts = $this->curation->applyApprovedOverrides($identity->toArray(), $identity);
        $category = (string) ($facts['canonical_category'] ?: config('catalog_taxonomy.default', 'gift_cards'));
        $brand = $this->cleanText($facts['brand'] ?? null);
        $family = $this->cleanText($facts['product_family'] ?? null);
        $currency = Str::upper($this->cleanText($facts['face_value_currency'] ?? null));
        $region = $this->canonicalRegion($facts['region'] ?? null);
        $faceValue = $this->normalizeFaceValue($facts['face_value'] ?? null);
        $categoryLabel = $this->categoryResolver->label($category);

        $aliases = [
            'brand' => $this->brandAliases($brand),
            'product' => $this->productAliases($identity, $brand, $family),
            'category' => $this->categoryAliases($category, $categoryLabel),
            'region' => $this->regionAliases($region),
        ];

        $textParts = [
            $brand,
            $family,
            $faceValue,
            $currency,
            $region,
            $category,
            $categoryLabel,
            $identity->identity_slug,
        ];
        $searchText = $this->dedupeText([
            ...$textParts,
            ...array_merge(...array_values($aliases)),
        ]);
        $tokens = $this->tokens([
            $searchText,
            ...array_merge(...array_values($aliases)),
        ]);

        return [
            'search_text' => $searchText,
            'search_tokens' => $tokens,
            'search_aliases' => $aliases,
            'search_metadata' => [
                'brand' => $brand !== '' ? $brand : null,
                'region' => $region !== '' ? $region : null,
                'currency' => $currency !== '' ? $currency : null,
                'face_value' => $faceValue !== '' ? (float) $faceValue : null,
                'category' => $category,
                'signals' => [
                    'popularity' => 0,
                    'conversion_rate' => 0,
                    'manual_boost' => 0,
                ],
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function brandAliases(string $brand): array
    {
        $normalized = $this->normalizePhrase($brand);
        $aliases = [$brand, $normalized];

        if (isset(self::BRAND_ALIASES[$normalized])) {
            $aliases = array_merge($aliases, self::BRAND_ALIASES[$normalized]);
        }

        return $this->uniquePhrases($aliases);
    }

    /**
     * @return array<int, string>
     */
    private function productAliases(CanonicalProductIdentity $identity, string $brand, string $family): array
    {
        $slug = str_replace('-', ' ', (string) $identity->identity_slug);
        $normalizedBrand = $this->normalizePhrase($brand);
        $normalizedFamily = $this->normalizePhrase($family);
        $normalizedSlug = $this->normalizePhrase($slug);

        $aliases = [$family, $slug, trim($brand.' '.$family)];

        if (preg_match('/\b(playstation|play station|ps)\s*5\b/u', $normalizedBrand.' '.$normalizedFamily.' '.$normalizedSlug)) {
            $aliases = array_merge($aliases, ['ps5', 'ps 5', 'sony ps5', 'sony playstation 5', 'play station 5', 'пс5', 'пс 5', 'плейстейшн 5', 'сони плейстейшн 5']);
        }

        if (str_contains($normalizedBrand.' '.$normalizedFamily.' '.$normalizedSlug, 'playstation')) {
            $aliases = array_merge($aliases, ['psn', 'play station']);
        }

        if (str_contains($normalizedBrand.' '.$normalizedFamily.' '.$normalizedSlug, 'xbox')) {
            $aliases = array_merge($aliases, ['xbox live', 'microsoft xbox']);
        }

        if (
            str_contains($normalizedBrand, 'nintendo')
            && str_contains($normalizedFamily.' '.$normalizedSlug, 'switch')
        ) {
            $aliases = array_merge($aliases, ['switch', 'switch oled']);
        }

        return $this->uniquePhrases($aliases);
    }

    /**
     * @return array<int, string>
     */
    private function categoryAliases(string $category, string $label): array
    {
        return $this->uniquePhrases(array_merge(
            [$category, str_replace('_', ' ', $category), $label],
            self::CATEGORY_ALIASES[$category] ?? [],
        ));
    }

    /**
     * @return array<int, string>
     */
    private function regionAliases(string $region): array
    {
        return $this->uniquePhrases(array_merge(
            [$region],
            self::REGION_ALIASES[$region] ?? [],
        ));
    }

    /**
     * @param array<int, string|null> $parts
     */
    private function dedupeText(array $parts): string
    {
        return implode(' ', $this->uniquePhrases($parts, preserveCase: true));
    }

    /**
     * @param array<int, string> $values
     * @return array<int, string>
     */
    private function tokens(array $values): array
    {
        $tokens = [];
        foreach ($values as $value) {
            foreach (preg_split('/[^\pL\pN]+/u', $this->normalizePhrase($value)) ?: [] as $token) {
                $token = trim($token);
                if ($token !== '') {
                    $tokens[] = $token;
                }
            }
        }

        $tokens = array_values(array_unique($tokens));
        sort($tokens);

        return $tokens;
    }

    /**
     * @param array<int, mixed> $values
     * @return array<int, string>
     */
    private function uniquePhrases(array $values, bool $preserveCase = false): array
    {
        $phrases = [];
        foreach ($values as $value) {
            $phrase = $preserveCase
                ? $this->cleanText($value)
                : $this->normalizePhrase($value);

            if ($phrase !== '') {
                $phrases[$this->normalizePhrase($phrase)] = $phrase;
            }
        }

        ksort($phrases);

        return array_values($phrases);
    }

    private function normalizePhrase(mixed $value): string
    {
        return Str::lower($this->cleanText($value));
    }

    private function canonicalRegion(mixed $value): string
    {
        $clean = $this->cleanText($value);
        if ($clean === '') {
            return '';
        }

        $normalized = $this->normalizePhrase($clean);

        return self::REGION_CANONICALS[$normalized] ?? Str::upper($clean);
    }

    private function cleanText(mixed $value): string
    {
        $value = trim((string) $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? '';

        return trim($value);
    }

    private function normalizeFaceValue(mixed $value): string
    {
        if (! is_numeric($value)) {
            return '';
        }

        return rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.');
    }
}
