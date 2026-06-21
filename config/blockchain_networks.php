<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default settlement network
    |--------------------------------------------------------------------------
    |
    | Geographic markets (config/markets.php) control storefront presentation.
    | Settlement networks control ledger / wallet / on-chain rail semantics.
    |
    */
    'default' => env('SETTLEMENT_NETWORK_DEFAULT', 'simple-layer-1'),

    /*
    |--------------------------------------------------------------------------
    | On-chain commerce rails (Polygon wallet binding, USDC proofs, crypto deposits)
    |--------------------------------------------------------------------------
    |
    | Keep false while simple commerce (SL1, invoices, merchant transfers) is the
    | product focus. When true, EVM networks become storefront-visible and crypto
    | settlement endpoints activate (Polygon RPC still requires POLYGON_RPC_ENABLED).
    |
    */
    'crypto_rails_enabled' => filter_var(env('COMMERCE_CRYPTO_RAILS_ENABLED', false), FILTER_VALIDATE_BOOL),

    'merchant_crypto_network' => env('MERCHANT_CRYPTO_SETTLEMENT_NETWORK', 'polygon'),

    'networks' => [
        'simple-layer-1' => [
            'label' => 'Simple Layer 1',
            'short_label' => 'SL1',
            'protocol' => 'sl1',
            'authority' => 'sovereign-ledger',
            'status' => 'live',
            'enabled' => true,
            'storefront_visible' => true,
            'adapter' => App\Services\Networks\SimpleLayer1NetworkAdapter::class,
            'trace_label' => 'Simple Layer One',
            'contract_key' => 'simple-layer-1',
        ],

        'polygon' => [
            'label' => 'Polygon',
            'short_label' => 'POL',
            'protocol' => 'evm',
            'authority' => 'evm-adapter',
            'status' => 'coming_soon',
            'enabled' => false,
            'storefront_visible' => true,
            'adapter' => App\Services\Networks\EvmNetworkAdapter::class,
            'chain_id' => 137,
            'native_symbol' => 'POL',
            'trace_label' => 'Polygon',
            'contract_key' => 'polygon',
            'assets' => ['USDT', 'USDC'],
            'rpc_url' => env('POLYGON_RPC_URL'),
            'rpc_enabled' => (bool) env('POLYGON_RPC_ENABLED', false),
            'required_confirmations' => 1,
        ],

        'bitcoin' => [
            'label' => 'Bitcoin',
            'short_label' => 'BTC',
            'protocol' => 'utxo',
            'authority' => 'utxo-adapter',
            'status' => 'coming_soon',
            'enabled' => false,
            'storefront_visible' => true,
            'adapter' => App\Services\Networks\UtxoNetworkAdapter::class,
            'native_symbol' => 'BTC',
            'trace_label' => 'Bitcoin',
            'contract_key' => 'bitcoin',
            'assets' => ['BTC'],
            'expected_chain' => env('BITCOIN_EXPECTED_CHAIN', 'main'),
            'rpc_url' => env('BITCOIN_RPC_URL'),
            'rpc_enabled' => (bool) env('BITCOIN_RPC_ENABLED', false),
            'required_confirmations' => 1,
        ],

        'ethereum' => [
            'label' => 'Ethereum',
            'short_label' => 'ETH',
            'protocol' => 'evm',
            'authority' => 'evm-adapter',
            'status' => 'coming_soon',
            'enabled' => false,
            'storefront_visible' => true,
            'adapter' => App\Services\Networks\EvmNetworkAdapter::class,
            'chain_id' => 1,
            'native_symbol' => 'ETH',
            'trace_label' => 'Ethereum',
            'contract_key' => 'ethereum',
            'assets' => ['USDT', 'USDC'],
            'rpc_url' => env('ETHEREUM_RPC_URL'),
            'rpc_enabled' => (bool) env('ETHEREUM_RPC_ENABLED', false),
            'required_confirmations' => 1,
        ],

        'base' => [
            'label' => 'Base',
            'short_label' => 'BASE',
            'protocol' => 'evm',
            'authority' => 'evm-adapter',
            'status' => 'coming_soon',
            'enabled' => false,
            'storefront_visible' => true,
            'adapter' => App\Services\Networks\EvmNetworkAdapter::class,
            'chain_id' => 8453,
            'native_symbol' => 'ETH',
            'trace_label' => 'Base',
            'contract_key' => 'base',
            'assets' => ['USDC'],
            'rpc_url' => env('BASE_RPC_URL'),
            'rpc_enabled' => (bool) env('BASE_RPC_ENABLED', false),
            'required_confirmations' => 1,
        ],

        'solana' => [
            'label' => 'Solana',
            'short_label' => 'SOL',
            'protocol' => 'solana',
            'authority' => 'solana-adapter',
            'status' => 'coming_soon',
            'enabled' => false,
            'storefront_visible' => true,
            'adapter' => App\Services\Networks\SolanaNetworkAdapter::class,
            'native_symbol' => 'SOL',
            'trace_label' => 'Solana',
            'contract_key' => 'solana',
            'assets' => ['SOL', 'USDC'],
            'rpc_url' => env('SOLANA_RPC_URL'),
            'rpc_enabled' => (bool) env('SOLANA_RPC_ENABLED', false),
            'required_confirmations' => 1,
        ],
    ],
];
