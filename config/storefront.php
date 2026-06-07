<?php

return [
    'token_ttl_seconds' => (int) env('STOREFRONT_TOKEN_TTL_SECONDS', 900),
    'token_issuer' => env('STOREFRONT_TOKEN_ISSUER', 'meanly-storefront'),
    'token_audience' => env('STOREFRONT_TOKEN_AUDIENCE', 'regional-frontends'),
    'frontend_url' => rtrim((string) env('STOREFRONT_FRONTEND_URL', env('APP_URL', 'https://meanly.test')), '/'),
    'api_hosts' => array_values(array_unique(array_filter(array_map(
        fn (string $host): string => strtolower(trim($host)),
        explode(',', (string) env(
            'STOREFRONT_API_HOSTS',
            implode(',', array_filter(array_unique([
                parse_url((string) env('NEXT_PUBLIC_MARKETPLACE_API_URL', ''), PHP_URL_HOST) ?: null,
                'api.'.env('APP_DOMAIN', 'meanly.test'),
                'api.meanly.test',
            ])))
        ))
    )))),
    'allowed_return_origins' => array_values(array_unique(array_filter(array_map(
        fn (string $origin): string => rtrim(trim($origin), '/'),
        explode(',', (string) env(
            'STOREFRONT_ALLOWED_RETURN_ORIGINS',
            implode(',', array_filter([
                env('STOREFRONT_FRONTEND_URL'),
                env('APP_URL', 'https://meanly.test'),
            ]))
        ))
    )))),
];
