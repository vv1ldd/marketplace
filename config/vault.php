<?php

return [
    'driver' => env('VAULT_DRIVER', 'local'), // 'local' or 'transit'
    'local_key' => env('VAULT_LOCAL_KEY', env('APP_KEY')), // Key for local driver
    
    'transit' => [
        'base_url' => env('VAULT_ADDR', 'http://127.0.0.1:8200'),
        'token'    => env('VAULT_TOKEN'),
        'key_name' => env('VAULT_TRANSIT_KEY', 'sovereign-pii-key'),
    ],
    'blind_index' => [
        'salt' => env('PII_BLIND_INDEX_SALT', 'sovereign-blind-salt-replace-in-production'),
    ],
];
