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
    'ui_theme' => env('SIMPLE_L1_UI_THEME', 'neobrutalism'),
    'prefer_native_deep_link' => env('SIMPLE_L1_PREFER_NATIVE_DEEP_LINK', false),
    'native_deep_link_scheme' => env('SIMPLE_L1_NATIVE_DEEP_LINK_SCHEME', 'simplel1'),
    'native_deep_link_auto_launch' => env('SIMPLE_L1_NATIVE_DEEP_LINK_AUTO_LAUNCH', false),
    'accept_native_direct_proof' => env('SIMPLE_L1_ACCEPT_NATIVE_DIRECT_PROOF', false),
    'require_native_direct_proof_signature' => env('SIMPLE_L1_REQUIRE_NATIVE_DIRECT_PROOF_SIGNATURE', true),
    'verify_tls' => env('SIMPLE_L1_VERIFY_TLS', true),
    'authorize_response_mode' => env('SIMPLE_L1_AUTHORIZE_RESPONSE_MODE', 'query'),
];
