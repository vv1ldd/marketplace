<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application, which will be used when the
    | framework needs to place the application's name in a notification or
    | other UI elements where an application name needs to be displayed.
    |
    */

    'name' => env('APP_NAME', 'Laravel'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | the application so that it's available within Artisan commands.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. The timezone
    | is set to "UTC" by default as it is suitable for most use cases.
    |
    */

    'timezone' => env('APP_TIMEZONE', 'UTC'),

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by Laravel's translation / localization methods. This option can be
    | set to any locale for which you plan to have translation strings.
    |
    */

    'locale' => env('APP_LOCALE', 'en'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'supported_locales' => array_values(array_filter(array_map('trim', explode(',', (string) env('APP_SUPPORTED_LOCALES', 'ru,en,es,tk,uz,ka,hy,kk,tr'))))),

    'locale_labels' => [
        'ru' => 'Русский',
        'en' => 'English',
        'es' => 'Español',
        'tk' => 'Türkmen',
        'uz' => 'Oʻzbek',
        'ka' => 'ქართული',
        'hy' => 'Հայերեն',
        'kk' => 'Қазақша',
        'tr' => 'Türkçe',
    ],

    'supported_themes' => array_values(array_filter(array_map('trim', explode(',', (string) env('APP_SUPPORTED_THEMES', 'consortium,partner,retro,nordic,synthwave,carbon'))))),
    'theme_fallback' => env('APP_THEME_FALLBACK', 'consortium'),
    'theme_labels' => [
        'consortium' => 'Flagship',
        'partner' => 'Partner',
        'retro' => 'Retro',
        'nordic' => 'Nordic',
        'synthwave' => 'Synthwave',
        'carbon' => 'Carbon',
    ],

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is utilized by Laravel's encryption services and should be set
    | to a random, 32 character string to ensure that all encrypted values
    | are secure. You should do this prior to deploying the application.
    |
    */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache"
    |
    */

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

    'domain' => env('APP_DOMAIN', '1gros.local'),
    'production_domain' => env('APP_PRODUCTION_DOMAIN', env('APP_DOMAIN', '1gros.local')),
    'public_domains' => array_values(array_unique(array_filter(array_map(
        static fn (?string $host): string => strtolower(trim((string) $host)),
        array_merge(
            explode(',', (string) env('APP_PUBLIC_DOMAINS', '')),
            [
                env('APP_DOMAIN', '1gros.local'),
                env('APP_PRODUCTION_DOMAIN', env('APP_DOMAIN', '1gros.local')),
                parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST),
            ],
        ),
    )))),

    /**
     * Legacy panel hosts (without scheme). Kept for redirect and allowlist compatibility.
     */
    'admin_panel_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env('OPS_PANEL_DOMAIN', env('ADMIN_PANEL_DOMAIN', '')))))),
    'partner_panel_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env('CONSORTIUM_PANEL_DOMAIN', env('PARTNER_PANEL_DOMAIN', '')))))),
    'treasury_panel_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env('TREASURY_PANEL_DOMAIN', ''))))),
    'kernel_panel_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env('KERNEL_PANEL_DOMAIN', ''))))),
    'audit_panel_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env('TRIBUNAL_PANEL_DOMAIN', env('AUDIT_PANEL_DOMAIN', '')))))),
    'support_panel_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env('SUPPORT_PANEL_DOMAIN', ''))))),

    'wildflow_token' => env('APP_WILDFLOW_TOKEN'),

    /**
     * На local с дампом БД с прода: абсолютные ссылки на свой маркет (см. remote_asset_hosts)
     * трактовать как файлы из public и открывать через asset() на текущем APP_URL.
     * Выкл: APP_REWRITE_REMOTE_ASSETS=false
     */
    'rewrite_remote_asset_urls' => filter_var(
        env('APP_REWRITE_REMOTE_ASSETS', env('APP_ENV') === 'local'),
        FILTER_VALIDATE_BOOL
    ),

    /** @var list<string> */
    'remote_asset_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env(
        'APP_REMOTE_ASSET_HOSTS',
        'marketplace.meanly.ru,www.marketplace.meanly.ru'
    ))))),

    /**
     * Только local: на шаге redeem после email принять этот код вместо значения из сессии (без реальной почты).
     * Пусто — отключено. На production/staging не задавать.
     */
    'redeem_local_verification_code' => env('REDEEM_LOCAL_VERIFICATION_CODE'),
];
