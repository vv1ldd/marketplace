<?php

namespace App\Services;

class DescriptionGenerator
{
    private array $descriptions;
    private array $countries;

    public function __construct()
    {
        $this->descriptions = \DB::table('mapping')
            ->pluck('description', 'tag')->toArray();
        $this->countries = \DB::table('mapping_countries')
            ->pluck('name_ru', 'code')->toArray();
    }

    public function generate(string $country_code, string $currency, string $category): string
    {

        if (!isset($this->descriptions[$category])) {
            $description = $this->descriptions['default'];
        } else {
            $description = $this->descriptions[$category];
        }

        $country = $this->countries[$country_code] ?? $country_code; // fallback если не нашли

        // Заменяем плейсхолдеры @category@ / @country@ / @currency@
        return str_replace(
            ['@category@', '@country@', '@currency@'],
            [$category, $country, $currency],
            $description
        );
    }
}
