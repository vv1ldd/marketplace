<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'ym' => [
        'api_key' => env('YM_API_KEY', 'ACMA:3mHDTfT7sVhGMb6xtQXGOoq5RzpHvLCjTq12Jd1M:bf243683'),
        'business_id' => env('YM_BUSINESS_ID', 198666367),
        'category_id' => env('YM_CATEGORY_ID', 70301474),
        'notification_token' => env('YM_NOTIFICATION_TOKEN'),
    ],

    'tg' => [
        'token' => env('TG_TOKEN'),
        'chat_id' => env('TG_CHAT_ID'),
    ],

    'google_translate' => [
        'api_key' => env('GOOGLE_TRANSLATE_API_KEY'),
    ],

    'imgbb' => [
        'key' => env('IMGBB_API_KEY'),
    ],

    'trusted_hosts' => env('TRUSTED_HOSTS', 'marketplace.wildcloud.ru,1gros.local,platform.local'),

    /*
    | При смене SKU в wildflow_catalogs парсером: алиасы + обновление products у этих магазинов (id через запятую).
    | По умолчанию 1 — MEANLY.
    */
    'wildflow' => [
        'sku_map_shop_ids' => (function (): array {
            $raw = trim((string) env('WILDFLOW_SKU_MAP_SHOP_IDS', '1'));

            return array_values(array_filter(array_map(
                'intval',
                explode(',', $raw === '' ? '1' : $raw)
            )));
        })(),
    ],
];
