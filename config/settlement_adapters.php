<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Settlement adapter production boundary
    |--------------------------------------------------------------------------
    |
    | Phase C closure is documented in docs/architecture/phase-c-settlement-attachment-closure.md.
    |
    | Adapters gate production exposure independently from blockchain_networks.networks.*.enabled.
    | Default-off → controlled-on rollout:
    |
    |   enabled=false          adapter unavailable (coming_soon)
    |   enabled=true, read_only  observe deposits, show balance, no write actions
    |   enabled=true, full       write-capable settlement actions (future)
    |
    */
    'polygon' => [
        'enabled' => filter_var(env('SETTLEMENT_ADAPTER_POLYGON_ENABLED', false), FILTER_VALIDATE_BOOL),
        'mode' => env('SETTLEMENT_ADAPTER_POLYGON_MODE', 'read_only'),
        'adapter' => App\Services\SettlementAdapters\PolygonSettlementAdapter::class,
        'stale_observation_hours' => (int) env('SETTLEMENT_ADAPTER_POLYGON_STALE_OBSERVATION_HOURS', 24),
    ],

    'ethereum' => [
        'enabled' => filter_var(env('SETTLEMENT_ADAPTER_ETHEREUM_ENABLED', false), FILTER_VALIDATE_BOOL),
        'mode' => env('SETTLEMENT_ADAPTER_ETHEREUM_MODE', 'read_only'),
        'adapter' => App\Services\SettlementAdapters\EthereumSettlementAdapter::class,
        'stale_observation_hours' => (int) env('SETTLEMENT_ADAPTER_ETHEREUM_STALE_OBSERVATION_HOURS', 24),
    ],

    'base' => [
        'enabled' => filter_var(env('SETTLEMENT_ADAPTER_BASE_ENABLED', false), FILTER_VALIDATE_BOOL),
        'mode' => env('SETTLEMENT_ADAPTER_BASE_MODE', 'read_only'),
        'adapter' => App\Services\SettlementAdapters\BaseSettlementAdapter::class,
        'stale_observation_hours' => (int) env('SETTLEMENT_ADAPTER_BASE_STALE_OBSERVATION_HOURS', 24),
    ],

    'bitcoin' => [
        'enabled' => filter_var(env('SETTLEMENT_ADAPTER_BITCOIN_ENABLED', false), FILTER_VALIDATE_BOOL),
        'mode' => env('SETTLEMENT_ADAPTER_BITCOIN_MODE', 'read_only'),
        'adapter' => App\Services\SettlementAdapters\BitcoinSettlementAdapter::class,
        'stale_observation_hours' => (int) env('SETTLEMENT_ADAPTER_BITCOIN_STALE_OBSERVATION_HOURS', 24),
    ],

    'solana' => [
        'enabled' => filter_var(env('SETTLEMENT_ADAPTER_SOLANA_ENABLED', false), FILTER_VALIDATE_BOOL),
        'mode' => env('SETTLEMENT_ADAPTER_SOLANA_MODE', 'read_only'),
        'adapter' => App\Services\SettlementAdapters\SolanaSettlementAdapter::class,
        'stale_observation_hours' => (int) env('SETTLEMENT_ADAPTER_SOLANA_STALE_OBSERVATION_HOURS', 24),
    ],

    'ton' => [
        'enabled' => filter_var(env('SETTLEMENT_ADAPTER_TON_ENABLED', false), FILTER_VALIDATE_BOOL),
        'mode' => env('SETTLEMENT_ADAPTER_TON_MODE', 'read_only'),
        'adapter' => App\Services\SettlementAdapters\TonSettlementAdapter::class,
        'stale_observation_hours' => (int) env('SETTLEMENT_ADAPTER_TON_STALE_OBSERVATION_HOURS', 24),
    ],
];
