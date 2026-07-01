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

    'node' => [
        'binary' => env('NODE_BINARY', 'node'),
    ],

    'ym' => [
        'api_key' => env('YM_API_KEY'),
        'business_id' => env('YM_BUSINESS_ID', 198666367),
        'campaign_id' => env('YM_CAMPAIGN_ID'),
        'category_id' => env('YM_CATEGORY_ID', 989939),
        'notification_token' => env('YM_NOTIFICATION_TOKEN'),
        'verify_tls' => env('YM_VERIFY_TLS', true),
    ],

    'tg' => [
        'token' => env('TG_TOKEN'),
        'chat_id' => env('TG_CHAT_ID'),
    ],

    'google_translate' => [
        'api_key' => env('GOOGLE_TRANSLATE_API_KEY'),
    ],

    'google_analytics' => [
        'base_url' => env('GOOGLE_ANALYTICS_DATA_BASE_URL', 'https://analyticsdata.googleapis.com'),
        'property_id' => env('GOOGLE_ANALYTICS_PROPERTY_ID'),
        'access_token' => env('GOOGLE_ANALYTICS_ACCESS_TOKEN'),
    ],

    'google_search_console' => [
        'base_url' => env('GOOGLE_SEARCH_CONSOLE_BASE_URL', 'https://www.googleapis.com'),
        'site_url' => env('GOOGLE_SEARCH_CONSOLE_SITE_URL'),
        'access_token' => env('GOOGLE_SEARCH_CONSOLE_ACCESS_TOKEN'),
    ],

    'yandex_webmaster' => [
        'base_url' => env('YANDEX_WEBMASTER_BASE_URL', 'https://api.webmaster.yandex.net'),
        'version' => env('YANDEX_WEBMASTER_API_VERSION', 'v4'),
        'oauth_token' => env('YANDEX_WEBMASTER_OAUTH_TOKEN'),
        'user_id' => env('YANDEX_WEBMASTER_USER_ID'),
        'host_id' => env('YANDEX_WEBMASTER_HOST_ID'),
    ],

    'google_suggest' => [
        'base_url' => env('GOOGLE_SUGGEST_BASE_URL', 'https://suggestqueries.google.com/complete/search'),
    ],

    'yandex_suggest' => [
        'base_url' => env('YANDEX_SUGGEST_BASE_URL', 'https://suggest.yandex.com/suggest-ff.cgi'),
    ],

    'bing_web_search' => [
        'base_url' => env('BING_WEB_SEARCH_BASE_URL', 'https://api.bing.microsoft.com'),
        'subscription_key' => env('BING_WEB_SEARCH_SUBSCRIPTION_KEY'),
    ],

    'google_ads' => [
        'base_url' => env('GOOGLE_ADS_BASE_URL', 'https://googleads.googleapis.com'),
        'version' => env('GOOGLE_ADS_API_VERSION', 'v24'),
        'developer_token' => env('GOOGLE_ADS_DEVELOPER_TOKEN'),
        'access_token' => env('GOOGLE_ADS_ACCESS_TOKEN'),
        'login_customer_id' => env('GOOGLE_ADS_LOGIN_CUSTOMER_ID'),
        'customer_id' => env('GOOGLE_ADS_CUSTOMER_ID'),
    ],

    'yandex_direct' => [
        'base_url' => env('YANDEX_DIRECT_BASE_URL', 'https://api.direct.yandex.com/json/v5'),
        'oauth_token' => env('YANDEX_DIRECT_OAUTH_TOKEN'),
        'client_login' => env('YANDEX_DIRECT_CLIENT_LOGIN'),
        'accept_language' => env('YANDEX_DIRECT_ACCEPT_LANGUAGE', 'en'),
    ],

    'yahoo_search' => [
        'base_url' => env('YAHOO_SEARCH_BASE_URL', 'https://www.searchapi.io/api/v1'),
        'api_key' => env('SEARCHAPI_API_KEY', env('YAHOO_SEARCH_API_KEY')),
    ],

    'duckduckgo_search' => [
        'base_url' => env('DUCKDUCKGO_SEARCH_BASE_URL', 'https://www.searchapi.io/api/v1'),
        'api_key' => env('SEARCHAPI_API_KEY', env('DUCKDUCKGO_SEARCH_API_KEY')),
    ],

    'meta_graph' => [
        'base_url' => env('META_GRAPH_BASE_URL', 'https://graph.facebook.com'),
        'version' => env('META_GRAPH_API_VERSION', 'v25.0'),
        'access_token' => env('META_GRAPH_ACCESS_TOKEN'),
        'ad_account_id' => env('META_GRAPH_AD_ACCOUNT_ID'),
    ],

    'tiktok_ads' => [
        'base_url' => env('TIKTOK_ADS_BASE_URL', 'https://business-api.tiktok.com/open_api/v1.3'),
        'access_token' => env('TIKTOK_ADS_ACCESS_TOKEN'),
        'advertiser_id' => env('TIKTOK_ADS_ADVERTISER_ID'),
    ],

    'indexnow' => [
        'endpoint' => env('INDEXNOW_ENDPOINT', 'https://api.indexnow.org/indexnow'),
        'host' => env('INDEXNOW_HOST'),
        'key' => env('INDEXNOW_KEY'),
        'key_location' => env('INDEXNOW_KEY_LOCATION'),
    ],

    'imgbb' => [
        'key' => env('IMGBB_API_KEY'),
    ],

    'trusted_hosts' => env('TRUSTED_HOSTS', 'meanly.test,meanly.one,www.meanly.one,api.wildflow.dev,meanly.ru,www.meanly.ru,meanly.ar,digitienda.ar,www.digitienda.ar,tsipruli.ge,www.tsipruli.ge'),

    /*
    | При смене SKU в wildflow_catalogs парсером: алиасы + обновление products у этих магазинов (id через запятую).
    | По умолчанию 1 — MEANLY.
    */
    'wildflow' => [
        'kernel_url' => env('DIGITAL_GOODS_SOURCE_URL', env('WILDFLOW_KERNEL_URL')),
        'base_url' => env('DIGITAL_GOODS_SOURCE_URL')
            ?: env('WILDFLOW_KERNEL_URL')
            ?: env('APP_WILDFLOW_URL')
            ?: rtrim((string) env('APP_URL', 'https://meanly.one'), '/').'/api/v1/',
        'verify_tls' => env('WILDFLOW_VERIFY_TLS', true),
        'kernel_mode' => env('WILDFLOW_KERNEL_MODE', (env('DIGITAL_GOODS_SOURCE_URL') || env('WILDFLOW_KERNEL_URL')) ? 'http' : 'local'),
        'financial_secret' => env('DIGITAL_GOODS_SOURCE_FINANCIAL_SECRET', env('WILDFLOW_KERNEL_FINANCIAL_SECRET', env('WILDFLOW_FINANCIAL_SECRET'))),
        'force_direct_supply' => (bool) env('WILDFLOW_FORCE_DIRECT_SUPPLY', false),
        'sku_map_shop_ids' => (function (): array {
            $raw = trim((string) env('WILDFLOW_SKU_MAP_SHOP_IDS', '1'));

            return array_values(array_filter(array_map(
                'intval',
                explode(',', $raw === '' ? '1' : $raw)
            )));
        })(),
    ],

    'ezpin' => [
        'base_url' => env('EZPIN_BASE_URL'),
        'client_id' => env('EZPIN_CLIENT_ID'),
        'secret_key' => env('EZPIN_SECRET_KEY'),
        'terminal_id' => env('EZPIN_TERMINAL_ID'),
        'terminal_pin' => env('EZPIN_TERMINAL_PIN'),
        'sandbox' => env('EZPIN_SANDBOX', false),
    ],

    'dgs_shadow' => [
        'ingest_url' => env('DGS_SHADOW_INGEST_URL'),
        'timeout_seconds' => (int) env('DGS_SHADOW_INGEST_TIMEOUT', 1),
    ],

    'dgs' => [
        'fulfillment_mode' => env('WILDFLOW_FULFILLMENT_MODE', 'http'),
        'fulfillment_url' => env('DGS_FULFILLMENT_URL', 'http://dgs-node-sidecar:8091'),
        'fulfillment_timeout' => (int) env('DGS_FULFILLMENT_TIMEOUT', 60),
        'split_fulfillment_providers' => array_values(array_filter(array_map(
            static fn (string $provider): string => trim($provider),
            explode(',', (string) env('WILDFLOW_SPLIT_FULFILLMENT_PROVIDERS', 'ezpin-sandbox,ezpin'))
        ))),
    ],

    'dadata' => [
        'token' => env('DADATA_TOKEN'),
    ],

    'ollama' => [
        'model' => env('OLLAMA_MODEL', 'gemma4'),
        'url' => env('OLLAMA_URL', 'http://localhost:11434'),
    ],
];
