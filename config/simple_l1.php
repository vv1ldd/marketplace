<?php

return [
    'identity_provider_url' => rtrim((string) env('SIMPLE_L1_IDENTITY_PROVIDER_URL', env('APP_URL', 'https://meanly.one')), '/'),
    'identity_browser_url' => env('SIMPLE_L1_IDENTITY_BROWSER_URL'),
    'runtime_url' => rtrim((string) env('SIMPLE_L1_RUNTIME_URL', 'http://localhost:3000'), '/'),
    'handoff_ttl_seconds' => (int) env('SIMPLE_L1_HANDOFF_TTL_SECONDS', 180),
    'protocol_gateway_url' => rtrim((string) (env('SIMPLE_L1_PROTOCOL_GATEWAY_URL') ?: env('APP_URL', 'https://meanly.one')), '/'),
    'proof_introspection_path' => env('SIMPLE_L1_PROOF_INTROSPECTION_PATH', '/api/sl1e/proofs/introspect'),
    'client_id' => env('SIMPLE_L1_CLIENT_ID', env('APP_DOMAIN', 'meanly.one')),
    'client_name' => env('SIMPLE_L1_CLIENT_NAME', 'Meanly One'),
    'market_canonical_hosts' => [
        'global' => env('SIMPLE_L1_GLOBAL_CLIENT_HOST', 'meanly.one'),
        'ru' => env('SIMPLE_L1_RU_CLIENT_HOST', 'meanly.ru'),
        'latam_ar' => env('SIMPLE_L1_LATAM_CLIENT_HOST', 'meanly.ar'),
        'ge' => env('SIMPLE_L1_GE_CLIENT_HOST', 'tsipruli.ge'),
    ],
    'market_client_names' => [
        'global' => env('SIMPLE_L1_GLOBAL_CLIENT_NAME', 'Meanly One'),
        'ru' => env('SIMPLE_L1_RU_CLIENT_NAME', 'Meanly'),
        'latam_ar' => env('SIMPLE_L1_LATAM_CLIENT_NAME', 'Meanly'),
        'ge' => env('SIMPLE_L1_GE_CLIENT_NAME', 'Meanly'),
    ],
    'ui_theme' => env('SIMPLE_L1_UI_THEME', 'neobrutalism'),
    'prefer_native_deep_link' => env('SIMPLE_L1_PREFER_NATIVE_DEEP_LINK', false),
    'native_deep_link_scheme' => env('SIMPLE_L1_NATIVE_DEEP_LINK_SCHEME', 'simplel1'),
    'native_deep_link_auto_launch' => env('SIMPLE_L1_NATIVE_DEEP_LINK_AUTO_LAUNCH', false),
    'accept_native_direct_proof' => env('SIMPLE_L1_ACCEPT_NATIVE_DIRECT_PROOF', false),
    'require_native_direct_proof_signature' => env('SIMPLE_L1_REQUIRE_NATIVE_DIRECT_PROOF_SIGNATURE', true),
    'verify_tls' => env('SIMPLE_L1_VERIFY_TLS', true),
    'authorize_response_mode' => env('SIMPLE_L1_AUTHORIZE_RESPONSE_MODE', 'query'),

    // ADR-0030: storefront login through the issuer Pushed Authorization Request
    // (PAR) and the canonical ceremony origin (connect.identity.<contour>).
    // Disabled by default: with no enabled hosts the storefront keeps the legacy
    // in-page authorize flow, so this block has zero runtime impact until a host
    // is listed in SIMPLE_L1_PAR_ENABLED_HOSTS and a client secret is provided.
    'par' => [
        // Comma-separated storefront hosts where PAR is active (e.g. "meanly.one").
        'enabled_hosts' => array_values(array_filter(array_map(
            static fn ($host) => strtolower(trim((string) $host)),
            explode(',', (string) env('SIMPLE_L1_PAR_ENABLED_HOSTS', '')),
        ))),
        // Public issuer base used to build the short ceremony link, e.g.
        // https://pass.meanly.one (which delegates /r/... to connect.identity.*).
        'issuer_base' => rtrim((string) env('SIMPLE_L1_PAR_ISSUER_BASE', ''), '/'),
        // Map of client_id => client_secret used to authenticate the PAR push.
        // JSON, e.g. {"meanly.one":"<secret>"}.
        'client_secrets' => array_filter(
            (array) json_decode((string) env('SIMPLE_L1_PAR_CLIENT_SECRETS_JSON', '{}'), true),
        ),
        'timeout' => (int) env('SIMPLE_L1_PAR_TIMEOUT', 10),
    ],
];
