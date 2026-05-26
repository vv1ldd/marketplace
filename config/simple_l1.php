<?php

return [
    'identity_provider_url' => rtrim((string) env('SIMPLE_L1_IDENTITY_PROVIDER_URL', 'https://api.wildflow.test'), '/'),
    'verify_tls' => env('SIMPLE_L1_VERIFY_TLS', true),
];
