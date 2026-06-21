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
