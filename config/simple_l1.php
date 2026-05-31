<?php

return [
    'identity_provider_url' => rtrim((string) env('SIMPLE_L1_IDENTITY_PROVIDER_URL', 'https://simplel1.online'), '/'),
    'protocol_gateway_url' => rtrim((string) env('SIMPLE_L1_PROTOCOL_GATEWAY_URL', 'https://api.wildflow.test'), '/'),
    'proof_introspection_path' => env('SIMPLE_L1_PROOF_INTROSPECTION_PATH', '/api/sl1e/proofs/introspect'),
    'client_id' => env('SIMPLE_L1_CLIENT_ID', 'meanly.one'),
    'client_name' => env('SIMPLE_L1_CLIENT_NAME', 'Meanly'),
    'ui_theme' => env('SIMPLE_L1_UI_THEME', 'neobrutalism'),
    'verify_tls' => env('SIMPLE_L1_VERIFY_TLS', true),
];
