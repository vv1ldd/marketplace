<?php

$domains = static function (string $market, array $defaults): array {
    $raw = (string) env("MARKET_{$market}_DOMAINS", '');
    $configured = $raw !== '' ? explode(',', $raw) : [];

    return array_values(array_unique(array_filter(array_map(
        static fn (mixed $domain): string => strtolower(trim((string) $domain)),
        [...$defaults, ...$configured],
    ))));
};

return [
    'default' => env('MARKET_DEFAULT', 'global'),

    'markets' => [
        'global' => [
            'name' => 'Global',
            'locale' => 'en',
            'currency' => 'USD',
            'catalog_scope' => 'global',
            'pricing_scope' => 'global',
            'display_currency' => 'USD',
            'settlement_currency' => 'RUBT',
            'storage_currency' => 'RUB',
            'demand_region' => null,
            'preferred_product_regions' => ['US', 'TR', 'GB'],
            'domains' => $domains('GLOBAL', [
                env('APP_DOMAIN', 'meanly.one'),
                'meanly.one',
                'www.meanly.one',
                'meanly.test',
                'marketplace.one',
                'marketplace.test',
            ]),
        ],

        'latam_ar' => [
            'name' => 'Argentina',
            'locale' => 'es',
            'currency' => 'ARS',
            'catalog_scope' => 'latam_ar',
            'pricing_scope' => 'latam_ar',
            'display_currency' => 'ARS',
            'settlement_currency' => 'RUBT',
            'storage_currency' => 'RUB',
            'demand_region' => 'AR',
            'preferred_product_regions' => ['AR', 'US', 'TR'],
            'domains' => $domains('LATAM_AR', [
                'ar.marketplace.one',
                'ar.marketplace.test',
                'meanly.ar',
            ]),
        ],

        'ru' => [
            'name' => 'Russia',
            'locale' => 'ru',
            'currency' => 'RUB',
            'catalog_scope' => 'ru',
            'pricing_scope' => 'ru',
            'display_currency' => 'RUB',
            'settlement_currency' => 'RUBT',
            'storage_currency' => 'RUB',
            'demand_region' => 'RU',
            'preferred_product_regions' => ['RU', 'TR', 'US'],
            'domains' => $domains('RU', [
                'ru.marketplace.one',
                'ru.marketplace.test',
                'meanly.ru',
            ]),
        ],
    ],
];
