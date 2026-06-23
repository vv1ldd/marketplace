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
        'bitcoin' => filter_var(env('MANAGED_WALLET_BITCOIN_ENABLED', true), FILTER_VALIDATE_BOOL),
        'solana' => filter_var(env('MANAGED_WALLET_SOLANA_ENABLED', true), FILTER_VALIDATE_BOOL),
        'ton' => filter_var(env('MANAGED_WALLET_TON_ENABLED', true), FILTER_VALIDATE_BOOL),
    ],

    /*
    |--------------------------------------------------------------------------
    | Legacy browser-wallet connect (challenge/verify)
    |--------------------------------------------------------------------------
    |
    | Off by default once managed create/import is the primary onboarding path.
    | Enable only for migration or environments without managed wallet rails.
    |
    */
    'legacy_connect_enabled' => filter_var(env('LEGACY_WALLET_CONNECT_ENABLED', false), FILTER_VALIDATE_BOOL),

    /*
    |--------------------------------------------------------------------------
    | Auto-provision on first vault access
    |--------------------------------------------------------------------------
    |
    | When a vault has no wallet bindings yet, create managed instruments for
    | every enabled network in bootstrap_network_order (EVM rails share one key).
    | Non-EVM failures are reported and do not block other rails.
    |
    */
    'auto_provision_on_vault' => filter_var(env('MANAGED_WALLETS_AUTO_PROVISION', true), FILTER_VALIDATE_BOOL),

    'bootstrap_network_order' => [
        'polygon',
        'base',
        'ethereum',
        'bitcoin',
        'solana',
        'ton',
    ],
];
