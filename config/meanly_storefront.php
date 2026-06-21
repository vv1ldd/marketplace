<?php

return [
    'legal_entity' => [
        'inn' => env('MEANLY_LEGAL_ENTITY_INN', '770000000001'),
        'name' => env('MEANLY_LEGAL_ENTITY_NAME', 'Meanly First-Party Storefront'),
        'short_name' => env('MEANLY_LEGAL_ENTITY_SHORT_NAME', 'Meanly'),
        'email' => env('MEANLY_LEGAL_ENTITY_EMAIL', 'storefront@meanly.ru'),
        'available_balance' => (float) env('MEANLY_LEGAL_ENTITY_BALANCE', 1000000),
        'currency' => 'RUB',
    ],

    'b2b' => [
        'email' => env('MEANLY_B2B_INQUIRIES_EMAIL', env('ACQUIRING_COMPANY_EMAIL', 'support@meanly.one')),
    ],

    'shop' => [
        'name' => env('MEANLY_SHOP_NAME', 'Meanly Store'),
        'domain' => env('MEANLY_STOREFRONT_DOMAIN', env('APP_DOMAIN', 'meanly.local')),
        'voucher_prefix' => env('MEANLY_VOUCHER_PREFIX', 'MEAN'),
        'business_id' => env('MEANLY_YM_BUSINESS_ID'),
        'campaign_id' => env('MEANLY_YM_CAMPAIGN_ID'),
        'api_key' => env('MEANLY_YM_API_KEY'),
        'notification_token' => env('MEANLY_YM_NOTIFICATION_TOKEN'),
        'ym_warehouse_id' => env('MEANLY_YM_WAREHOUSE_ID'),
        'ym_stock' => (int) env('MEANLY_YM_STOCK', 10),
    ],

    'channels' => [
        'storefront' => 'meanly_storefront',
        'yandex' => 'yandex_market',
    ],

    'provider_fulfillment' => [
        'allow_live_redemption' => (bool) env('MEANLY_ALLOW_LIVE_PROVIDER_REDEMPTION', false),
    ],
];
