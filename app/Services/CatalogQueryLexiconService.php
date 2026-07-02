<?php

namespace App\Services;

use App\Models\CanonicalProductIdentity;
use App\Models\MappingCountry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CatalogQueryLexiconService
{
    /**
     * Brand aliases shared with catalog search indexing.
     *
     * @var array<string, array{display: string, aliases: array<int, string>}>
     */
    private const BRAND_DEFINITIONS = [
        'PlayStation' => [
            'display' => 'PlayStation/PSN',
            'aliases' => ['playstation', 'play station', 'psn', 'ps store', 'playstation network', 'sony', 'ps', 'ps4', 'ps5', 'плейстейшн', 'плейстейшен', 'плойка', 'псн'],
        ],
        'Xbox' => [
            'display' => 'Xbox',
            'aliases' => ['xbox', 'xbox live', 'microsoft xbox', 'иксбокс', 'хбокс', 'икс бокс'],
        ],
        'Nintendo' => [
            'display' => 'Nintendo',
            'aliases' => ['nintendo', 'nintendo switch', 'switch online', 'switch', 'нинтендо', 'свитч'],
        ],
        'Steam' => [
            'display' => 'Steam',
            'aliases' => ['steam', 'steam wallet', 'стим', 'стим валлет'],
        ],
        'Apple' => [
            'display' => 'Apple/App Store',
            'aliases' => ['apple', 'app store', 'itunes', 'icloud', 'эпл', 'апп стор', 'айтюнс'],
        ],
        'Google Play' => [
            'display' => 'Google Play',
            'aliases' => ['google play', 'play store', 'google', 'гугл плей'],
        ],
        'Roblox' => [
            'display' => 'Roblox',
            'aliases' => ['roblox', 'robux'],
        ],
        'Spotify' => [
            'display' => 'Spotify',
            'aliases' => ['spotify', 'spotify premium', 'premium', 'спотифай', 'спотифи', 'премиум'],
        ],
        'PUBG' => [
            'display' => 'PUBG',
            'aliases' => ['pubg', 'pubg mobile', 'unknown cash'],
        ],
        'Free Fire' => [
            'display' => 'Free Fire',
            'aliases' => ['free fire', 'garena free fire'],
        ],
        'Bigo Live' => [
            'display' => 'Bigo Live',
            'aliases' => ['bigo live', 'bigo'],
        ],
        'Bitdefender' => [
            'display' => 'Bitdefender',
            'aliases' => ['bitdefender'],
        ],
        'American Express' => [
            'display' => 'American Express',
            'aliases' => ['american express', 'amex'],
        ],
        'Abbonamenti.it' => [
            'display' => 'Abbonamenti.it',
            'aliases' => ['abbonamenti.it', 'abbonamenti it', 'abbonamenti'],
        ],
    ];

    /**
     * Extra region aliases keyed by ISO code.
     *
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
    private const REGION_ENGLISH_NAMES = [
        'AR' => 'argentina',
        'AT' => 'austria',
        'BR' => 'brazil',
        'EU' => 'europe',
        'GB' => 'united kingdom',
        'RU' => 'russia',
        'TR' => 'turkey',
        'US' => 'united states',
    ];

    /**
     * @return array<string, array{display: string, aliases: array<int, string>, confidence: float, source: string}>
     */
    public function brandCandidates(): array
    {
        return Cache::remember('catalog_query_brand_lexicon', 3600, function (): array {
            $candidates = [];

            foreach (self::BRAND_DEFINITIONS as $brand => $definition) {
                $candidates[$brand] = [
                    'display' => $definition['display'],
                    'aliases' => $this->uniqueAliases([$brand, $definition['display'], ...$definition['aliases']]),
                    'confidence' => 0.94,
                    'source' => 'lexicon.brand_alias',
                ];
            }

            $this->mergeConfiguredBrandAliases($candidates);

            if (! Schema::hasTable('canonical_product_identities')) {
                return $candidates;
            }

            try {
                CanonicalProductIdentity::query()
                    ->whereNotNull('brand')
                    ->where('brand', '<>', '')
                    ->distinct()
                    ->orderBy('brand')
                    ->limit(150)
                    ->pluck('brand')
                    ->each(function (string $brand) use (&$candidates): void {
                        if (isset($candidates[$brand])) {
                            return;
                        }

                        $normalized = Str::lower($brand);
                        $aliases = [$brand, str_replace(['.', '_', '-'], ' ', $brand)];

                        foreach (self::BRAND_DEFINITIONS as $definitionBrand => $definition) {
                            if (Str::lower($definitionBrand) !== $normalized) {
                                continue;
                            }

                            $aliases = array_merge($aliases, $definition['aliases']);
                            break;
                        }

                        $candidates[$brand] = [
                            'display' => $brand,
                            'aliases' => $this->uniqueAliases($aliases),
                            'confidence' => 0.72,
                            'source' => 'lexicon.indexed_brand',
                        ];
                    });
            } catch (\Throwable) {
                return $candidates;
            }

            return $candidates;
        });
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function regionAliasMap(): array
    {
        return Cache::remember('catalog_query_region_lexicon', 3600, function (): array {
            $map = [];

            if (Schema::hasTable('mapping_countries')) {
                MappingCountry::query()
                    ->whereNotNull('name_en')
                    ->where('name_en', '<>', '')
                    ->orderBy('code')
                    ->get(['code', 'name_en', 'name_ru', 'name_es', 'name_tr', 'name_tk'])
                    ->each(function (MappingCountry $country) use (&$map): void {
                        $canonical = $this->regionCanonical((string) $country->code, $country);
                        if ($canonical === '') {
                            return;
                        }

                        $aliases = $this->uniqueAliases(array_filter([
                            $country->code,
                            Str::lower((string) $country->code),
                            $country->name_en,
                            $country->name_ru,
                            $country->name_es,
                            $country->name_tr,
                            $country->name_tk,
                            ...(self::REGION_ALIASES[strtoupper((string) $country->code)] ?? []),
                        ]));

                        $map[$canonical] = array_values(array_unique([
                            ...($map[$canonical] ?? []),
                            ...$aliases,
                        ]));
                    });
            }

            foreach (self::REGION_ALIASES as $code => $aliases) {
                $country = MappingCountry::query()->where('code', strtoupper((string) $code))->first();
                $canonical = $this->regionCanonical((string) $code, $country);

                $map[$canonical] = array_values(array_unique([
                    ...($map[$canonical] ?? []),
                    $code,
                    Str::lower((string) $code),
                    ...$aliases,
                ]));
            }

            return $map;
        });
    }

    /**
     * @return array<int, string>
     */
    public function queryTextVariants(string $query): array
    {
        $normalized = $this->normalizeHumanText($query);

        return collect([
            $normalized,
            $this->normalizeAsciiText($normalized),
            app(QueryNormalizationService::class)->transliterate($normalized),
            $this->convertKeyboardLayout($normalized, 'ru_to_en'),
            $this->convertKeyboardLayout($normalized, 'en_to_ru'),
        ])
            ->map(fn (string $variant): string => trim($variant))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, array{display: string, aliases: array<int, string>, confidence: float, source: string}>
     */
    public function intentCandidates(): array
    {
        return Cache::remember('catalog_query_intent_lexicon', 3600, function (): array {
            $candidates = [];
            $extraAliases = (array) config('catalog_taxonomy.search_aliases.intents', []);
            $corridors = (array) config('catalog_taxonomy.intent_corridors', []);

            foreach ($corridors as $slug => $definition) {
                $aliases = [
                    (string) $slug,
                    str_replace('_', ' ', (string) $slug),
                    data_get($definition, 'label_en'),
                    data_get($definition, 'label_ru'),
                    data_get($definition, 'description_en'),
                    data_get($definition, 'description_ru'),
                    ...(array) ($extraAliases[$slug] ?? []),
                ];

                foreach ((array) ($definition['brand_overrides'] ?? []) as $brand => $brandAliases) {
                    $aliases[] = $brand;
                    $aliases = array_merge($aliases, (array) $brandAliases);
                }

                $candidates[(string) $slug] = [
                    'display' => (string) (data_get($definition, 'label_en') ?: $slug),
                    'aliases' => $this->uniqueAliases($aliases),
                    'confidence' => 0.86,
                    'source' => 'lexicon.intent_alias',
                ];
            }

            return $candidates;
        });
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function productKeywordMap(): array
    {
        return Cache::remember('catalog_query_product_keyword_lexicon', 3600, function (): array {
            $map = (array) config('catalog_taxonomy.search_aliases.products', []);
            $keywordRules = (array) config('catalog_taxonomy.keyword_rules', []);

            foreach ($keywordRules as $category => $keywords) {
                $map[$category] = array_values(array_unique([
                    ...(array) ($map[$category] ?? []),
                    ...(array) $keywords,
                ]));
            }

            return $map;
        });
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function searchSynonymMap(): array
    {
        return Cache::remember('catalog_search_synonym_map', 3600, function (): array {
            $map = [];

            foreach ((array) config('catalog_taxonomy.search_aliases.synonyms', []) as $token => $synonyms) {
                $normalized = $this->normalizeSearchToken((string) $token);
                if ($normalized === '') {
                    continue;
                }

                $map[$normalized] = array_values(array_unique([
                    ...($map[$normalized] ?? []),
                    ...array_map(fn (string $synonym): string => $this->normalizeSearchToken($synonym), (array) $synonyms),
                ]));
            }

            foreach ($this->brandCandidates() as $brand => $candidate) {
                $group = collect($candidate['aliases'])
                    ->map(fn (string $alias): string => $this->normalizeSearchToken($alias))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                foreach ($group as $alias) {
                    $map[$alias] = array_values(array_unique([
                        ...($map[$alias] ?? []),
                        ...$group,
                        $this->normalizeSearchToken($brand),
                    ]));
                }
            }

            return $map;
        });
    }

    /**
     * @return array<int, string>
     */
    public function fuzzyBrandTerms(): array
    {
        return Cache::remember('catalog_search_fuzzy_brand_terms', 3600, function (): array {
            return collect($this->brandCandidates())
                ->flatMap(fn (array $candidate): array => $candidate['aliases'])
                ->map(fn (string $alias): string => $this->normalizeSearchToken($alias))
                ->filter(fn (string $term): bool => strlen($term) >= 4 && preg_match('/^[a-z0-9]+$/', $term) === 1)
                ->unique()
                ->values()
                ->all();
        });
    }

    /**
     * @return array<int, string>
     */
    public function expandSearchToken(string $token): array
    {
        $token = $this->normalizeSearchToken($token);
        if ($token === '') {
            return [];
        }

        $queue = collect([
            $token,
            $this->convertKeyboardLayout($token, 'ru_to_en'),
            $this->convertKeyboardLayout($token, 'en_to_ru'),
            app(QueryNormalizationService::class)->transliterate($token),
        ])
            ->map(fn (string $variant): string => $this->normalizeSearchToken($variant))
            ->filter()
            ->unique()
            ->values();

        $expanded = $queue->all();
        $synonyms = $this->searchSynonymMap();

        foreach ($queue as $variant) {
            $expanded = array_merge($expanded, $synonyms[$variant] ?? []);
            $expanded = array_merge($expanded, $this->fuzzyKnownTerms($variant));
        }

        return collect($expanded)
            ->map(fn (string $variant): string => $this->normalizeSearchToken($variant))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function categoriesMatchingToken(string $token): array
    {
        $token = $this->normalizeSearchToken($token);
        if ($token === '') {
            return [];
        }

        $matches = [];

        foreach ((array) config('catalog_taxonomy.categories', []) as $slug => $definition) {
            $haystack = $this->normalizeSearchText(implode(' ', [
                $slug,
                str_replace('_', ' ', (string) $slug),
                $definition['label_ru'] ?? '',
                $definition['label_en'] ?? '',
                $definition['description_ru'] ?? '',
            ]));

            if (str_contains($haystack, $token)) {
                $matches[] = (string) $slug;
            }
        }

        foreach ($this->productKeywordMap() as $category => $keywords) {
            foreach ((array) $keywords as $keyword) {
                if ($this->aliasMatchesToken($token, (string) $keyword)) {
                    $matches[] = (string) $category;
                    break;
                }
            }
        }

        return array_values(array_unique($matches));
    }

    /**
     * @return array<int, string>
     */
    public function intentsMatchingToken(string $token): array
    {
        $token = $this->normalizeSearchToken($token);
        if ($token === '') {
            return [];
        }

        $matches = [];

        foreach ($this->intentCandidates() as $intent => $candidate) {
            foreach ($candidate['aliases'] as $alias) {
                if ($this->aliasMatchesToken($token, (string) $alias)) {
                    $matches[] = (string) $intent;
                    break;
                }
            }
        }

        return array_values(array_unique($matches));
    }

    /**
     * @return array<int, string>
     */
    public function brandsMatchingToken(string $token): array
    {
        $token = $this->normalizeSearchToken($token);
        if ($token === '') {
            return [];
        }

        $matches = [];

        foreach ($this->brandCandidates() as $brand => $candidate) {
            foreach ($candidate['aliases'] as $alias) {
                if ($this->aliasMatchesToken($token, (string) $alias)) {
                    $matches[] = (string) $brand;
                    break;
                }
            }
        }

        return array_values(array_unique($matches));
    }

    public function normalizeSearchToken(string $value): string
    {
        return Str::of($value)
            ->lower()
            ->replaceMatches('/[^\pL\pN$€£₽.]+/u', ' ')
            ->squish()
            ->toString();
    }

    /**
     * @param  array<string, array{display: string, aliases: array<int, string>, confidence: float, source: string}>  $candidates
     */
    private function mergeConfiguredBrandAliases(array &$candidates): void
    {
        $sources = [
            ...(array) config('catalog_taxonomy.search_aliases.brands', []),
        ];

        foreach ((array) config('catalog_taxonomy.intent_corridors', []) as $corridor) {
            foreach ((array) ($corridor['brand_overrides'] ?? []) as $brand => $aliases) {
                $sources[$brand] = array_values(array_unique([
                    ...(array) ($sources[$brand] ?? []),
                    ...(array) $aliases,
                ]));
            }
        }

        foreach ($sources as $brand => $aliases) {
            $existing = $candidates[$brand] ?? null;
            $mergedAliases = $this->uniqueAliases([
                $brand,
                ...(array) $aliases,
                ...($existing['aliases'] ?? []),
            ]);

            $candidates[$brand] = [
                'display' => $existing['display'] ?? (string) $brand,
                'aliases' => $mergedAliases,
                'confidence' => $existing['confidence'] ?? 0.9,
                'source' => $existing['source'] ?? 'lexicon.config_brand_alias',
            ];
        }
    }

    /**
     * @return array<int, string>
     */
    private function fuzzyKnownTerms(string $token): array
    {
        if (strlen($token) < 4 || preg_match('/[^a-z0-9]/', $token)) {
            return [];
        }

        return collect($this->fuzzyBrandTerms())
            ->filter(function (string $term) use ($token): bool {
                if (str_starts_with($term, $token) || str_starts_with($token, $term)) {
                    return true;
                }

                $maxDistance = max(1, min(3, (int) floor(max(strlen($token), strlen($term)) / 4)));

                return levenshtein($token, $term) <= $maxDistance;
            })
            ->values()
            ->all();
    }

    private function aliasMatchesToken(string $token, string $alias): bool
    {
        $alias = $this->normalizeSearchToken($alias);
        if ($alias === '' || $token === '') {
            return false;
        }

        if ($token === $alias) {
            return true;
        }

        if (strlen($alias) >= 4 && str_contains($token, $alias)) {
            return true;
        }

        if (strlen($token) >= 4 && str_contains($alias, $token)) {
            return true;
        }

        return false;
    }

    private function normalizeSearchText(string $value): string
    {
        return $this->normalizeSearchToken($value);
    }

    /**
     * @param  array<int, string>  $values
     * @return array<int, string>
     */
    private function uniqueAliases(array $values): array
    {
        $aliases = [];

        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }

            $aliases[Str::lower($value)] = $value;
        }

        return array_values($aliases);
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

    private function convertKeyboardLayout(string $value, string $direction): string
    {
        $ru = 'йцукенгшщзхъфывапролджэячсмитьбюё';
        $en = 'qwertyuiop[]asdfghjkl;\'zxcvbnm,.`';
        $from = $direction === 'ru_to_en' ? $ru : $en;
        $to = $direction === 'ru_to_en' ? $en : $ru;

        return strtr($value, array_combine(
            preg_split('//u', $from, -1, PREG_SPLIT_NO_EMPTY) ?: [],
            preg_split('//u', $to, -1, PREG_SPLIT_NO_EMPTY) ?: [],
        ) ?: []);
    }

    private function regionCanonical(string $code, ?MappingCountry $country = null): string
    {
        if ($country && filled($country->name_en)) {
            return Str::lower(trim((string) $country->name_en));
        }

        return self::REGION_ENGLISH_NAMES[strtoupper($code)] ?? Str::lower($code);
    }
}
