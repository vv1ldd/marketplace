<?php

namespace App\Services;

use App\Models\MappingCountry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class MappingCountryLabelService
{
    public function localizedLabel(string $region, ?string $locale = null): string
    {
        $region = trim($region);

        if ($region === '' || Str::lower($region) === 'global') {
            return (string) __('catalog.show.global_region');
        }

        $locale = $locale ?: app()->getLocale();
        $code = $this->resolveCountryCode($region);
        $label = $code !== null
            ? Str::upper($this->labelForCode($code, $locale))
            : Str::upper($region);

        if ($locale !== 'ru' && $this->containsCyrillic($label) && $code !== null) {
            $label = Str::upper($this->englishName($code) ?? $code);
        }

        return $label;
    }

    private function labelForCode(string $code, string $locale): string
    {
        $country = MappingCountry::query()->where('code', $code)->first(['code', 'name_en', 'name_ru', 'name_es', 'name_tr', 'name_tk']);

        if ($country instanceof MappingCountry) {
            return $this->nameForCountry($country, $locale);
        }

        if ($locale === 'ru') {
            return MappingCountry::query()->where('code', $code)->value('name_ru')
                ?? $this->englishName($code)
                ?? $code;
        }

        return $this->englishName($code) ?? $code;
    }

    private function nameForCountry(MappingCountry $country, string $locale): string
    {
        $code = Str::upper((string) $country->code);

        return match ($locale) {
            'ru' => $country->name_ru ?? $this->englishName($code) ?? $country->name_en ?? $code,
            'es' => $country->name_es ?? $this->englishName($code) ?? $country->name_en ?? $code,
            'tr' => $country->name_tr ?? $this->englishName($code) ?? $country->name_en ?? $code,
            default => $country->name_en ?? $this->englishName($code) ?? $code,
        };
    }

    private function englishName(string $code): ?string
    {
        $code = Str::upper(trim($code));
        $configured = config("mapping_country_labels.en.{$code}");

        return is_string($configured) && $configured !== '' ? $configured : null;
    }

    private function resolveCountryCode(string $region): ?string
    {
        $country = $this->resolveCountry($region);

        if ($country instanceof MappingCountry) {
            return Str::upper((string) $country->code);
        }

        $code = $this->normalizeRegionCode($region);

        return $code !== null && ($this->countryExists($code) || $this->englishName($code) !== null)
            ? $code
            : null;
    }

    private function normalizeRegionCode(string $region): ?string
    {
        $candidate = Str::upper(trim($region));

        if ($candidate === '') {
            return null;
        }

        $aliases = [
            'UK' => 'GB',
            'USA' => 'US',
            'KSA' => 'SA',
            'SAU' => 'SA',
            'ARE' => 'AE',
            'UAE' => 'AE',
            'EGY' => 'EG',
            'DZA' => 'DZ',
            'IRQ' => 'IQ',
        ];

        $candidate = $aliases[$candidate] ?? $candidate;

        if (preg_match('/^[A-Z]{2,4}$/', $candidate) && ($this->countryExists($candidate) || $this->englishName($candidate) !== null)) {
            return $candidate;
        }

        $ascii = Str::upper(Str::ascii(trim($region)));

        foreach ((array) config('mapping_country_labels.en', []) as $code => $englishName) {
            if ($ascii === Str::upper((string) $englishName) || $ascii === $code) {
                return (string) $code;
            }
        }

        $nameMap = [
            'UNITED STATES' => 'US',
            'UNITED KINGDOM' => 'GB',
            'SAUDI ARABIA' => 'SA',
            'UNITED ARAB EMIRATES' => 'AE',
            'SOUTH KOREA' => 'KR',
            'CZECH REPUBLIC' => 'CZ',
            'SOUTH AFRICA' => 'ZA',
            'NEW ZEALAND' => 'NZ',
            'HONG KONG' => 'HK',
            'ALGERIA' => 'DZ',
            'IRAQ' => 'IQ',
        ];

        return $nameMap[$ascii] ?? null;
    }

    private function countryExists(string $code): bool
    {
        if (! Schema::hasTable('mapping_countries')) {
            return false;
        }

        return MappingCountry::query()->where('code', $code)->exists();
    }

    private function resolveCountry(string $region): ?MappingCountry
    {
        $index = $this->aliasIndex();
        $normalized = Str::lower(trim($region));

        if (isset($index[$normalized])) {
            return $index[$normalized];
        }

        $code = $this->normalizeRegionCode($region);

        if ($code !== null && isset($index[Str::lower($code)])) {
            return $index[Str::lower($code)];
        }

        return null;
    }

    /**
     * @return array<string, MappingCountry>
     */
    private function aliasIndex(): array
    {
        return Cache::remember('mapping_country_label_index_v3', 3600, function (): array {
            if (! Schema::hasTable('mapping_countries')) {
                return [];
            }

            $index = [];

            MappingCountry::query()
                ->orderBy('code')
                ->get(['code', 'name_en', 'name_ru', 'name_es', 'name_tr', 'name_tk'])
                ->each(function (MappingCountry $country) use (&$index): void {
                    foreach ($this->aliasesForCountry($country) as $alias) {
                        $index[Str::lower($alias)] = $country;
                    }
                });

            return $index;
        });
    }

    /**
     * @return array<int, string>
     */
    private function aliasesForCountry(MappingCountry $country): array
    {
        return array_values(array_filter([
            (string) $country->code,
            Str::lower((string) $country->code),
            (string) $country->name_en,
            (string) $country->name_ru,
            (string) $country->name_es,
            (string) $country->name_tr,
            (string) $country->name_tk,
        ], fn (string $value): bool => trim($value) !== ''));
    }

    private function containsCyrillic(string $value): bool
    {
        return (bool) preg_match('/[а-яё]/iu', $value);
    }
}
