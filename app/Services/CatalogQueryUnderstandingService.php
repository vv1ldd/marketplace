<?php

namespace App\Services;

use App\Models\CanonicalProductIdentity;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CatalogQueryUnderstandingService
{
    private const MAX_QUERY_LENGTH = 240;

    /**
     * @var array<string, array{display: string, aliases: array<int, string>, confidence: float}>
     */
    private const BRAND_ALIASES = [
        'PlayStation' => [
            'display' => 'PlayStation/PSN',
            'aliases' => ['playstation', 'play station', 'psn', 'ps store', 'playstation network'],
            'confidence' => 0.94,
        ],
        'Xbox' => [
            'display' => 'Xbox',
            'aliases' => ['xbox', 'xbox live', 'microsoft xbox'],
            'confidence' => 0.93,
        ],
        'Nintendo' => [
            'display' => 'Nintendo',
            'aliases' => ['nintendo', 'nintendo switch', 'switch online'],
            'confidence' => 0.93,
        ],
        'Steam' => [
            'display' => 'Steam',
            'aliases' => ['steam', 'steam wallet'],
            'confidence' => 0.94,
        ],
        'Apple' => [
            'display' => 'Apple/App Store',
            'aliases' => ['apple', 'app store', 'itunes', 'icloud'],
            'confidence' => 0.9,
        ],
        'Google Play' => [
            'display' => 'Google Play',
            'aliases' => ['google play', 'play store'],
            'confidence' => 0.93,
        ],
        'Roblox' => [
            'display' => 'Roblox',
            'aliases' => ['roblox', 'robux'],
            'confidence' => 0.94,
        ],
        'PUBG' => [
            'display' => 'PUBG',
            'aliases' => ['pubg', 'pubg mobile', 'unknown cash'],
            'confidence' => 0.92,
        ],
        'Free Fire' => [
            'display' => 'Free Fire',
            'aliases' => ['free fire', 'garena free fire'],
            'confidence' => 0.92,
        ],
        'Bigo Live' => [
            'display' => 'Bigo Live',
            'aliases' => ['bigo live', 'bigo'],
            'confidence' => 0.92,
        ],
        'Bitdefender' => [
            'display' => 'Bitdefender',
            'aliases' => ['bitdefender'],
            'confidence' => 0.94,
        ],
        'American Express' => [
            'display' => 'American Express',
            'aliases' => ['american express', 'amex'],
            'confidence' => 0.93,
        ],
        'Abbonamenti.it' => [
            'display' => 'Abbonamenti.it',
            'aliases' => ['abbonamenti.it', 'abbonamenti it', 'abbonamenti'],
            'confidence' => 0.94,
        ],
    ];

    /**
     * @var array<string, array<int, string>>
     */
    private const CURRENCY_ALIASES = [
        'USD' => ['usd', 'us dollar', 'us dollars', 'dollar', 'dollars', 'доллар', 'доллара', 'долларов', '$'],
        'EUR' => ['eur', 'euro', 'euros', 'евро', '€'],
        'RUB' => ['rub', 'ruble', 'rubles', 'руб', 'рубль', 'рубля', 'рублей', '₽'],
        'GBP' => ['gbp', 'pound', 'pounds', 'фунт', 'фунта', 'фунтов', '£'],
    ];

    /**
     * @var array<string, array<int, string>>
     */
    private const REGION_ALIASES = [
        'turkey' => ['turkey', 'turkiye', 'türkiye', 'tr', 'турция', 'турции', 'турецкий'],
        'us' => ['us', 'usa', 'united states', 'america', 'сша', 'америка'],
        'eu' => ['eu', 'europe', 'european', 'европа', 'европейский'],
        'global' => ['global', 'worldwide', 'international', 'глобальный', 'международный'],
        'gb' => ['uk', 'gb', 'great britain', 'united kingdom', 'британия', 'великобритания'],
        'italy' => ['italy', 'italia', 'it', 'италия', 'италии', 'итальянский'],
        'russia' => ['russia', 'ru', 'россия', 'россии', 'российский'],
    ];

    /**
     * @return array<string, mixed>
     */
    public function understand(string $query, ?string $locale = null): array
    {
        $originalQuery = $this->cleanQuery($query);

        // Apply Query Normalization Layer
        $canonicalQuery = app(\App\Services\QueryNormalizationService::class)->normalize($originalQuery);

        $normalizedQuery = $this->normalizeHumanText($canonicalQuery !== '' ? $canonicalQuery : $originalQuery);
        $asciiQuery = $this->normalizeAsciiText($normalizedQuery);

        $entities = [];
        $filters = [];

        $intent = $this->detectIntent($normalizedQuery, $asciiQuery, $entities);
        $this->detectAmountsAndCurrencies($normalizedQuery, $asciiQuery, $filters, $entities);
        $this->detectBrands($normalizedQuery, $asciiQuery, $filters, $entities);
        $this->detectCategory($normalizedQuery, $asciiQuery, $filters, $entities);
        $this->detectRegion($normalizedQuery, $asciiQuery, $filters, $entities);
        $this->detectAvailabilityFilters($normalizedQuery, $asciiQuery, $filters, $entities);

        return [
            'type' => 'CatalogQueryUnderstanding',
            'version' => 1,
            'original_query' => $originalQuery,
            'canonical_query' => $canonicalQuery !== '' ? $canonicalQuery : $originalQuery,
            'normalized_query' => $normalizedQuery,
            'locale' => $this->cleanLocale($locale),
            'intent' => $intent,
            'filters' => (object) $filters,
            'entities' => $entities,
            'confidence' => $this->confidence($entities, $intent),
            'rewritten_query' => $this->rewrittenQuery($normalizedQuery, $filters, $entities),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $entities
     */
    private function detectIntent(string $normalizedQuery, string $asciiQuery, array &$entities): string
    {
        $rules = [
            'trusted_seller' => [
                'trusted', 'reliable', 'rating', 'rated seller', 'best seller',
                'надежный', 'надёжный', 'рейтинг', 'проверенный продавец',
            ],
            'lowest_price' => [
                'lowest', 'cheapest', 'cheap', 'low price', 'best price',
                'дешевле', 'дешево', 'дёшево', 'самый дешевый', 'самая дешевая', 'низкая цена',
            ],
            'in_stock' => [
                'in stock', 'stock', 'available', 'availability',
                'в наличии', 'есть в наличии', 'доступно',
            ],
            'buy_now' => [
                'buy now', 'buy', 'purchase', 'order', 'now',
                'купить', 'куплю', 'заказать', 'сейчас', 'хочу',
            ],
        ];

        foreach ($rules as $intent => $aliases) {
            $matched = $this->firstMatchedAlias($normalizedQuery, $asciiQuery, $aliases);
            if ($matched === null) {
                continue;
            }

            $entities[] = $this->entity('intent', $intent, $matched, 0.72, 'heuristic.intent_keyword');

            return $intent;
        }

        return ProductIntentResolutionService::DEFAULT_INTENT;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<int, array<string, mixed>>  $entities
     */
    private function detectAmountsAndCurrencies(string $normalizedQuery, string $asciiQuery, array &$filters, array &$entities): void
    {
        $matchedCurrency = null;

        foreach ([
            '/(?P<symbol>[$€£₽])\s*(?P<amount>\d+(?:[.,]\d+)?)/u',
            '/(?P<amount>\d+(?:[.,]\d+)?)\s*(?P<symbol>[$€£₽])/u',
        ] as $pattern) {
            if (! preg_match($pattern, $normalizedQuery, $match)) {
                continue;
            }

            $amount = $this->numberValue($match['amount']);
            $currency = $this->currencyFromAlias($match['symbol']);
            if ($amount === null || $currency === null) {
                continue;
            }

            $filters['face_value'] = $amount;
            $filters['currency'] = $currency;
            $matchedCurrency = $currency;
            $entities[] = $this->entity('amount', $amount, $match[0], 0.9, 'heuristic.amount_symbol');
            $entities[] = $this->entity('currency', $currency, $match['symbol'], 0.92, 'heuristic.currency_symbol');

            break;
        }

        if (! isset($filters['face_value']) && preg_match('/(?P<amount>\d+(?:[.,]\d+)?)\s*(?P<currency>usd|us dollar|us dollars|dollar|dollars|eur|euro|euros|rub|ruble|rubles|gbp|pound|pounds)/i', $asciiQuery, $match)) {
            $amount = $this->numberValue($match['amount']);
            $currency = $this->currencyFromAlias($match['currency']);
            if ($amount !== null && $currency !== null) {
                $filters['face_value'] = $amount;
                $filters['currency'] = $currency;
                $matchedCurrency = $currency;
                $entities[] = $this->entity('amount', $amount, $match[0], 0.88, 'heuristic.amount_currency');
                $entities[] = $this->entity('currency', $currency, $match['currency'], 0.9, 'heuristic.currency_word');
            }
        }

        if (! isset($filters['face_value']) && preg_match('/(?P<amount>\d+(?:[.,]\d+)?)\s*(?P<currency>доллар(?:а|ов)?|евро|руб(?:ль|ля|лей)?|фунт(?:а|ов)?)/u', $normalizedQuery, $match)) {
            $amount = $this->numberValue($match['amount']);
            $currency = $this->currencyFromAlias($match['currency']);
            if ($amount !== null && $currency !== null) {
                $filters['face_value'] = $amount;
                $filters['currency'] = $currency;
                $matchedCurrency = $currency;
                $entities[] = $this->entity('amount', $amount, $match[0], 0.88, 'heuristic.amount_currency');
                $entities[] = $this->entity('currency', $currency, $match['currency'], 0.9, 'heuristic.currency_word');
            }
        }

        if ($matchedCurrency !== null) {
            return;
        }

        foreach (self::CURRENCY_ALIASES as $currency => $aliases) {
            $matched = $this->firstMatchedAlias($normalizedQuery, $asciiQuery, $aliases);
            if ($matched === null) {
                continue;
            }

            $filters['currency'] = $currency;
            $entities[] = $this->entity('currency', $currency, $matched, 0.82, 'heuristic.currency_alias');

            return;
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<int, array<string, mixed>>  $entities
     */
    private function detectBrands(string $normalizedQuery, string $asciiQuery, array &$filters, array &$entities): void
    {
        $matches = [];

        foreach ($this->brandCandidates() as $brand => $candidate) {
            $matched = $this->firstMatchedAlias($normalizedQuery, $asciiQuery, $candidate['aliases']);
            if ($matched === null) {
                continue;
            }

            $matches[] = [
                'brand' => $brand,
                'display' => $candidate['display'],
                'matched' => $matched,
                'confidence' => $candidate['confidence'],
                'source' => $candidate['source'],
            ];
        }

        usort($matches, fn (array $a, array $b): int => $b['confidence'] <=> $a['confidence']);

        foreach (array_slice($matches, 0, 3) as $match) {
            $entities[] = $this->entity('brand', $match['display'], $match['matched'], $match['confidence'], $match['source']);
        }

        if ($matches !== []) {
            $filters['brand'] = $matches[0]['brand'];
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<int, array<string, mixed>>  $entities
     */
    private function detectCategory(string $normalizedQuery, string $asciiQuery, array &$filters, array &$entities): void
    {
        $categories = (array) config('catalog_taxonomy.categories', []);
        $keywordRules = (array) config('catalog_taxonomy.keyword_rules', []);
        $best = null;

        foreach ($categories as $slug => $definition) {
            $aliases = array_values(array_filter([
                $slug,
                str_replace('_', ' ', (string) $slug),
                data_get($definition, 'label_en'),
                data_get($definition, 'label_ru'),
            ]));

            foreach ((array) ($keywordRules[$slug] ?? []) as $keyword) {
                $aliases[] = (string) $keyword;
            }

            $matched = $this->firstMatchedAlias($normalizedQuery, $asciiQuery, $aliases);
            if ($matched === null) {
                continue;
            }

            $confidence = in_array($matched, (array) ($keywordRules[$slug] ?? []), true) ? 0.78 : 0.72;
            if ($best === null || $confidence > $best['confidence']) {
                $best = [
                    'slug' => (string) $slug,
                    'matched' => $matched,
                    'confidence' => $confidence,
                ];
            }
        }

        if ($best === null) {
            return;
        }

        $filters['category'] = $best['slug'];
        $entities[] = $this->entity('category', $best['slug'], $best['matched'], $best['confidence'], 'taxonomy.keyword');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<int, array<string, mixed>>  $entities
     */
    private function detectRegion(string $normalizedQuery, string $asciiQuery, array &$filters, array &$entities): void
    {
        foreach (self::REGION_ALIASES as $region => $aliases) {
            $matched = $this->firstMatchedAlias($normalizedQuery, $asciiQuery, $aliases);
            if ($matched === null) {
                continue;
            }

            $filters['region'] = $region;
            $entities[] = $this->entity('region', $region, $matched, 0.82, 'heuristic.region_alias');

            return;
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<int, array<string, mixed>>  $entities
     */
    private function detectAvailabilityFilters(string $normalizedQuery, string $asciiQuery, array &$filters, array &$entities): void
    {
        $hasOffer = $this->firstMatchedAlias($normalizedQuery, $asciiQuery, [
            'seller offer', 'with offer', 'has offer', 'checkout offer',
            'предложение продавца', 'есть оффер', 'с оффером',
        ]);

        if ($hasOffer !== null) {
            $filters['has_offer'] = true;
            $entities[] = $this->entity('filter', 'has_offer:true', $hasOffer, 0.74, 'heuristic.availability_filter');
        }

        $providerNetwork = $this->firstMatchedAlias($normalizedQuery, $asciiQuery, [
            'provider network', 'network candidate', 'provider candidate',
            'провайдер', 'провайдерская сеть', 'кандидат провайдера',
        ]);

        if ($providerNetwork !== null) {
            $filters['provider_network_only'] = true;
            $entities[] = $this->entity('filter', 'provider_network_only:true', $providerNetwork, 0.74, 'heuristic.provider_filter');
        }
    }

    /**
     * @return array<string, array{display: string, aliases: array<int, string>, confidence: float, source: string}>
     */
    private function brandCandidates(): array
    {
        $candidates = [];

        foreach (self::BRAND_ALIASES as $brand => $definition) {
            $candidates[$brand] = [
                'display' => $definition['display'],
                'aliases' => $definition['aliases'],
                'confidence' => $definition['confidence'],
                'source' => 'heuristic.brand_alias',
            ];
        }

        try {
            if (! Schema::hasTable('canonical_product_identities')) {
                return $candidates;
            }

            CanonicalProductIdentity::query()
                ->whereNotNull('brand')
                ->where('brand', '<>', '')
                ->distinct()
                ->orderBy('brand')
                ->limit(100)
                ->pluck('brand')
                ->each(function (string $brand) use (&$candidates): void {
                    if (isset($candidates[$brand])) {
                        return;
                    }

                    $candidates[$brand] = [
                        'display' => $brand,
                        'aliases' => array_values(array_unique([
                            $brand,
                            str_replace(['.', '_', '-'], ' ', $brand),
                        ])),
                        'confidence' => 0.72,
                        'source' => 'indexed_brand',
                    ];
                });
        } catch (\Throwable) {
            return $candidates;
        }

        return $candidates;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<int, array<string, mixed>>  $entities
     */
    private function rewrittenQuery(string $normalizedQuery, array $filters, array $entities): string
    {
        $parts = [];

        foreach ($entities as $entity) {
            if (($entity['type'] ?? null) !== 'brand') {
                continue;
            }

            $value = (string) ($entity['value'] ?? '');
            if ($value !== '') {
                $parts[] = str_contains($value, '/') ? Str::before($value, '/') : $value;
            }
        }

        if (isset($filters['face_value'])) {
            $parts[] = $this->formatNumber((float) $filters['face_value']);
        }

        if (isset($filters['currency'])) {
            $parts[] = (string) $filters['currency'];
        }

        $rewritten = Str::of(implode(' ', array_values(array_unique($parts))))
            ->squish()
            ->toString();

        return $rewritten !== '' ? $rewritten : $normalizedQuery;
    }

    /**
     * @param  array<int, string>  $aliases
     */
    private function firstMatchedAlias(string $normalizedQuery, string $asciiQuery, array $aliases): ?string
    {
        foreach ($aliases as $alias) {
            $alias = $this->normalizeHumanText((string) $alias);
            if ($alias === '') {
                continue;
            }

            if ($this->phraseExists($normalizedQuery, $alias) || $this->phraseExists($asciiQuery, $this->normalizeAsciiText($alias))) {
                return $alias;
            }
        }

        return null;
    }

    private function phraseExists(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return false;
        }

        $quoted = preg_quote($needle, '/');

        return (bool) preg_match('/(?<![\pL\pN])'.$quoted.'(?![\pL\pN])/u', $haystack);
    }

    private function currencyFromAlias(string $alias): ?string
    {
        $normalized = $this->normalizeHumanText($alias);
        $ascii = $this->normalizeAsciiText($alias);

        foreach (self::CURRENCY_ALIASES as $currency => $aliases) {
            foreach ($aliases as $candidate) {
                if ($normalized === $this->normalizeHumanText($candidate) || $ascii === $this->normalizeAsciiText($candidate)) {
                    return $currency;
                }
            }
        }

        return null;
    }

    private function numberValue(string $value): ?float
    {
        $normalized = str_replace(',', '.', $value);
        if (! is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }

    /**
     * @param  array<int, array<string, mixed>>  $entities
     */
    private function confidence(array $entities, string $intent): float
    {
        if ($entities === []) {
            return $intent === ProductIntentResolutionService::DEFAULT_INTENT ? 0.25 : 0.36;
        }

        $entityConfidence = collect($entities)->avg(fn (array $entity): float => (float) ($entity['confidence'] ?? 0.0));
        $coverageBoost = min(0.18, count($entities) * 0.025);
        $intentBoost = $intent !== ProductIntentResolutionService::DEFAULT_INTENT ? 0.04 : 0.0;

        return round(min(0.95, max(0.3, (float) $entityConfidence + $coverageBoost + $intentBoost)), 2);
    }

    /**
     * @return array<string, mixed>
     */
    private function entity(string $type, mixed $value, string $matched, float $confidence, string $source): array
    {
        return [
            'type' => $type,
            'value' => $value,
            'matched' => $matched,
            'confidence' => round($confidence, 2),
            'source' => $source,
        ];
    }

    private function cleanQuery(string $query): string
    {
        return Str::of($query)
            ->squish()
            ->limit(self::MAX_QUERY_LENGTH, '')
            ->toString();
    }

    private function cleanLocale(?string $locale): ?string
    {
        $locale = Str::of((string) $locale)
            ->lower()
            ->replaceMatches('/[^a-z_-]/', '')
            ->limit(16, '')
            ->toString();

        return $locale !== '' ? $locale : null;
    }

    private function normalizeHumanText(string $value): string
    {
        return Str::of($value)
            ->lower()
            ->replaceMatches('/[^\pL\pN$€£₽.]+/u', ' ')
            ->squish()
            ->toString();
    }

    private function normalizeAsciiText(string $value): string
    {
        return Str::of($value)
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9$€£₽.]+/', ' ')
            ->squish()
            ->toString();
    }

    private function formatNumber(float $value): string
    {
        return floor($value) === $value ? (string) (int) $value : rtrim(rtrim((string) $value, '0'), '.');
    }
}
