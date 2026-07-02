<?php

namespace App\Services;

use Illuminate\Support\Str;

class CatalogQueryUnderstandingService
{
    private const MAX_QUERY_LENGTH = 240;

    public function __construct(
        private readonly CatalogQueryLexiconService $lexicon,
    ) {}

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
        'turkey' => ['turkey', 'turkiye', 'türkiye', 'tr'],
        'us' => ['us', 'usa', 'united states', 'america'],
        'eu' => ['eu', 'europe', 'european'],
        'global' => ['global', 'worldwide', 'international'],
        'gb' => ['uk', 'gb', 'great britain', 'united kingdom'],
        'italy' => ['italy', 'italia', 'it'],
        'russia' => ['russia', 'ru'],
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
        $queryVariants = $this->lexicon->queryTextVariants($canonicalQuery !== '' ? $canonicalQuery : $originalQuery);

        $entities = [];
        $filters = [];

        $intent = $this->detectIntent($queryVariants, $entities);
        $this->detectAmountsAndCurrencies($normalizedQuery, $asciiQuery, $filters, $entities);
        $this->detectBrands($queryVariants, $filters, $entities);
        $this->detectDiscoveryCorridor($queryVariants, $filters, $entities);
        $this->detectCategory($queryVariants, $filters, $entities);
        $this->detectRegion($queryVariants, $filters, $entities);
        $this->detectAvailabilityFilters($queryVariants, $filters, $entities);

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
     * @param  array<int, string>  $queryVariants
     * @param  array<int, array<string, mixed>>  $entities
     */
    private function detectIntent(array $queryVariants, array &$entities): string
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
            $matched = $this->firstMatchedAlias($queryVariants, $aliases);
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
            $matched = $this->firstMatchedAlias([$normalizedQuery, $asciiQuery], $aliases);
            if ($matched === null) {
                continue;
            }

            $filters['currency'] = $currency;
            $entities[] = $this->entity('currency', $currency, $matched, 0.82, 'heuristic.currency_alias');

            return;
        }
    }

    /**
     * @param  array<int, string>  $queryVariants
     * @param  array<string, mixed>  $filters
     * @param  array<int, array<string, mixed>>  $entities
     */
    private function detectBrands(array $queryVariants, array &$filters, array &$entities): void
    {
        $matches = [];

        foreach ($this->lexicon->brandCandidates() as $brand => $candidate) {
            $matched = $this->firstMatchedAlias($queryVariants, $candidate['aliases']);
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

        if ($matches === []) {
            foreach ($this->lexicon->brandCandidates() as $brand => $candidate) {
                $matched = $this->fuzzyBrandMatch($queryVariants, $candidate['aliases']);
                if ($matched === null) {
                    continue;
                }

                $matches[] = [
                    'brand' => $brand,
                    'display' => $candidate['display'],
                    'matched' => $matched,
                    'confidence' => max(0.6, $candidate['confidence'] - 0.12),
                    'source' => 'lexicon.fuzzy_brand',
                ];
            }
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
     * @param  array<int, string>  $queryVariants
     * @param  array<string, mixed>  $filters
     * @param  array<int, array<string, mixed>>  $entities
     */
    private function detectDiscoveryCorridor(array $queryVariants, array &$filters, array &$entities): void
    {
        $best = null;

        foreach ($this->lexicon->intentCandidates() as $intent => $candidate) {
            $matched = $this->firstMatchedAlias($queryVariants, $candidate['aliases']);
            if ($matched === null) {
                continue;
            }

            $confidence = max(0.72, $candidate['confidence'] - (str_contains($matched, ' ') ? 0 : 0.04));
            if ($best === null || $confidence > $best['confidence']) {
                $best = [
                    'intent' => (string) $intent,
                    'display' => $candidate['display'],
                    'matched' => $matched,
                    'confidence' => $confidence,
                ];
            }
        }

        if ($best === null) {
            return;
        }

        $filters['discovery_intent'] = $best['intent'];
        $entities[] = $this->entity('discovery_intent', $best['display'], $best['matched'], $best['confidence'], 'lexicon.intent_alias');
    }

    /**
     * @param  array<int, string>  $queryVariants
     * @param  array<string, mixed>  $filters
     * @param  array<int, array<string, mixed>>  $entities
     */
    private function detectCategory(array $queryVariants, array &$filters, array &$entities): void
    {
        $categories = (array) config('catalog_taxonomy.categories', []);
        $keywordRules = (array) config('catalog_taxonomy.keyword_rules', []);
        $productKeywords = $this->lexicon->productKeywordMap();
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

            foreach ((array) ($productKeywords[$slug] ?? []) as $keyword) {
                $aliases[] = (string) $keyword;
            }

            $matched = $this->firstMatchedAlias($queryVariants, $aliases);
            if ($matched === null) {
                continue;
            }

            $confidence = in_array($matched, (array) ($productKeywords[$slug] ?? []), true) ? 0.82
                : (in_array($matched, (array) ($keywordRules[$slug] ?? []), true) ? 0.78 : 0.72);
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
     * @param  array<int, string>  $queryVariants
     * @param  array<string, mixed>  $filters
     * @param  array<int, array<string, mixed>>  $entities
     */
    private function detectRegion(array $queryVariants, array &$filters, array &$entities): void
    {
        $best = null;

        foreach ($this->lexicon->regionAliasMap() as $region => $aliases) {
            $matched = $this->firstMatchedAlias($queryVariants, $aliases);
            if ($matched === null || $this->isBlockedRegionAliasContext($queryVariants, $matched)) {
                continue;
            }

            $candidate = [
                'region' => $region,
                'matched' => $matched,
                'score' => mb_strlen($matched),
                'confidence' => 0.82,
                'source' => 'lexicon.region_alias',
            ];

            if ($best === null || $candidate['score'] > $best['score']) {
                $best = $candidate;
            }
        }

        foreach (self::REGION_ALIASES as $region => $aliases) {
            $matched = $this->firstMatchedAlias($queryVariants, $aliases);
            if ($matched === null || $this->isBlockedRegionAliasContext($queryVariants, $matched)) {
                continue;
            }

            $candidate = [
                'region' => $region,
                'matched' => $matched,
                'score' => mb_strlen($matched),
                'confidence' => 0.78,
                'source' => 'heuristic.region_alias',
            ];

            if ($best === null || $candidate['score'] > $best['score']) {
                $best = $candidate;
            }
        }

        if ($best === null) {
            return;
        }

        $filters['region'] = $best['region'];
        $entities[] = $this->entity('region', $best['region'], $best['matched'], $best['confidence'], $best['source']);
    }

    /**
     * @param  array<int, string>  $queryVariants
     */
    private function isBlockedRegionAliasContext(array $queryVariants, string $alias): bool
    {
        if (mb_strlen($alias) > 2) {
            return false;
        }

        $blockers = [
            'id' => ['apple id', 'app id', 'user id', 'google id', 'icloud id', 'player id'],
        ];

        foreach ($queryVariants as $variant) {
            $variant = $this->normalizeHumanText($variant);

            foreach ($blockers[$alias] ?? [] as $phrase) {
                if ($this->phraseExists($variant, $phrase)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<int, string>  $queryVariants
     * @param  array<string, mixed>  $filters
     * @param  array<int, array<string, mixed>>  $entities
     */
    private function detectAvailabilityFilters(array $queryVariants, array &$filters, array &$entities): void
    {
        $hasOffer = $this->firstMatchedAlias($queryVariants, [
            'seller offer', 'with offer', 'has offer', 'checkout offer',
            'предложение продавца', 'есть оффер', 'с оффером',
        ]);

        if ($hasOffer !== null) {
            $filters['has_offer'] = true;
            $entities[] = $this->entity('filter', 'has_offer:true', $hasOffer, 0.74, 'heuristic.availability_filter');
        }

        $providerNetwork = $this->firstMatchedAlias($queryVariants, [
            'provider network', 'network candidate', 'provider candidate',
            'провайдер', 'провайдерская сеть', 'кандидат провайдера',
        ]);

        if ($providerNetwork !== null) {
            $filters['provider_network_only'] = true;
            $entities[] = $this->entity('filter', 'provider_network_only:true', $providerNetwork, 0.74, 'heuristic.provider_filter');
        }
    }

    /**
     * @param  array<int, string>  $queryVariants
     * @param  array<int, string>  $aliases
     */
    private function firstMatchedAlias(array $queryVariants, array $aliases): ?string
    {
        foreach ($aliases as $alias) {
            $alias = $this->normalizeHumanText((string) $alias);
            if ($alias === '') {
                continue;
            }

            $asciiAlias = $this->normalizeAsciiText($alias);

            foreach ($queryVariants as $variant) {
                $variant = $this->normalizeHumanText($variant);
                $asciiVariant = $this->normalizeAsciiText($variant);

                if ($this->phraseExists($variant, $alias) || $this->phraseExists($asciiVariant, $asciiAlias)) {
                    return $alias;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $queryVariants
     * @param  array<int, string>  $aliases
     */
    private function fuzzyBrandMatch(array $queryVariants, array $aliases): ?string
    {
        $tokens = collect($queryVariants)
            ->flatMap(fn (string $variant): array => preg_split('/\s+/', $this->normalizeAsciiText($variant)) ?: [])
            ->filter(fn (string $token): bool => strlen($token) >= 4 && preg_match('/^[a-z0-9]+$/', $token) === 1)
            ->unique()
            ->values();

        if ($tokens->isEmpty()) {
            return null;
        }

        $targets = collect($aliases)
            ->map(fn (string $alias): string => $this->normalizeAsciiText($alias))
            ->filter(fn (string $alias): bool => strlen($alias) >= 4 && preg_match('/^[a-z0-9 ]+$/', $alias) === 1)
            ->flatMap(fn (string $alias): array => preg_split('/\s+/', $alias) ?: [])
            ->filter(fn (string $token): bool => strlen($token) >= 4)
            ->unique()
            ->values();

        foreach ($tokens as $token) {
            foreach ($targets as $target) {
                if (str_starts_with($target, $token) || str_starts_with($token, $target)) {
                    return $token;
                }

                $maxDistance = max(1, min(3, (int) floor(max(strlen($token), strlen($target)) / 4)));
                if (levenshtein($token, $target) <= $maxDistance) {
                    return $token;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<int, array<string, mixed>>  $entities
     */
    private function rewrittenQuery(string $normalizedQuery, array $filters, array $entities): string
    {
        $parts = [];

        if (isset($filters['brand'])) {
            $brand = (string) $filters['brand'];
            if ($brand !== '') {
                $parts[] = $brand;
            }
        }

        if (isset($filters['face_value'])) {
            $parts[] = $this->formatNumber((float) $filters['face_value']);
        }

        if (isset($filters['currency'])) {
            $parts[] = (string) $filters['currency'];
        }

        if (isset($filters['region'])) {
            $parts[] = (string) $filters['region'];
        }

        $rewritten = Str::of(implode(' ', array_values(array_unique($parts))))
            ->squish()
            ->toString();

        return $rewritten !== '' ? $rewritten : $normalizedQuery;
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
