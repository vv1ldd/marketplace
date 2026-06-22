<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Managed wallet provisioning
    |--------------------------------------------------------------------------
    |
    | Server-provisioned addresses bound as IdentityBinding(managed).
    | EVM networks share the same key generator; enable per rail below.
    |
    */
    'enabled' => filter_var(env('MANAGED_WALLETS_ENABLED', false), FILTER_VALIDATE_BOOL),

    'networks' => [
        'polygon' => filter_var(env('MANAGED_WALLET_POLYGON_ENABLED', true), FILTER_VALIDATE_BOOL),
        'ethereum' => filter_var(env('MANAGED_WALLET_ETHEREUM_ENABLED', false), FILTER_VALIDATE_BOOL),
        'base' => filter_var(env('MANAGED_WALLET_BASE_ENABLED', false), FILTER_VALIDATE_BOOL),
    ],
];
