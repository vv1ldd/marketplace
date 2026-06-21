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

    'merchant_modules_default' => [
        'overview',
        'sales_channels',
        'orders',
        'catalog',
        'provider_storefront',
        'warehouses',
        'vouchers',
        'finance',
        'support',
        'settings',
    ],

    'markets' => [
        'global' => [
            'name' => 'Global',
            'locale' => 'en',
            'currency' => 'USD',
            'catalog_scope' => 'global',
            'pricing_scope' => 'global',
            'display_currency' => 'USD',
            'settlement_currency' => 'RUB',
            'storage_currency' => 'RUB',
            'demand_region' => null,
            'preferred_product_regions' => ['US', 'TR', 'GB'],
            'sales_channels' => ['meanly_storefront', 'offline_store', 'woocommerce'],
            'merchant_modules' => [
                'overview',
                'sales_channels',
                'orders',
                'catalog',
                'provider_storefront',
                'warehouses',
                'vouchers',
                'finance',
                'support',
                'settings',
            ],
            'domains' => $domains('GLOBAL', [
                'meanly.one',
                'www.meanly.one',
                'marketplace.one',
            ]),
        ],

        'latam_ar' => [
            'name' => 'Argentina',
            'locale' => 'es',
            'currency' => 'ARS',
            'catalog_scope' => 'latam_ar',
            'pricing_scope' => 'latam_ar',
            'display_currency' => 'ARS',
            'settlement_currency' => 'RUB',
            'storage_currency' => 'RUB',
            'demand_region' => 'AR',
            'preferred_product_regions' => ['AR', 'US', 'TR'],
            'sales_channels' => ['meanly_storefront', 'offline_store', 'woocommerce'],
            'merchant_modules' => [
                'overview',
                'sales_channels',
                'orders',
                'catalog',
                'provider_storefront',
                'warehouses',
                'vouchers',
                'finance',
                'support',
                'settings',
            ],
            'domains' => $domains('LATAM_AR', [
                'ar.marketplace.one',
                'ar.marketplace.test',
                'digitienda.ar',
                'www.digitienda.ar',
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
            'settlement_currency' => 'RUB',
            'storage_currency' => 'RUB',
            'demand_region' => 'RU',
            'preferred_product_regions' => ['RU', 'TR', 'US'],
            'sales_channels' => ['meanly_storefront', 'yandex_market', 'offline_store', 'woocommerce'],
            'merchant_modules' => [
                'overview',
                'sales_channels',
                'orders',
                'catalog',
                'provider_storefront',
                'warehouses',
                'activations',
                'vouchers',
                'finance',
                'support',
                'settings',
            ],
            'domains' => $domains('RU', [
                'ru.marketplace.one',
                'ru.marketplace.test',
                'meanly.ru',
                'www.meanly.ru',
            ]),
        ],

        'ge' => [
            'name' => 'Georgia',
            'locale' => 'ka',
            'currency' => 'GEL',
            'catalog_scope' => 'ge',
            'pricing_scope' => 'ge',
            'display_currency' => 'GEL',
            'settlement_currency' => 'RUB',
            'storage_currency' => 'RUB',
            'demand_region' => 'GE',
            'preferred_product_regions' => ['GE', 'TR', 'US'],
            'sales_channels' => ['meanly_storefront', 'offline_store', 'woocommerce'],
            'merchant_modules' => [
                'overview',
                'sales_channels',
                'orders',
                'catalog',
                'provider_storefront',
                'warehouses',
                'vouchers',
                'finance',
                'support',
                'settings',
            ],
            'domains' => $domains('GE', [
                'tsipruli.ge',
                'www.tsipruli.ge',
            ]),
        ],
    ],
];
