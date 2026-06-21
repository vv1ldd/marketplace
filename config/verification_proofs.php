<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Transfer proof token registry
    |--------------------------------------------------------------------------
    |
    | Verification adapters resolve expected token contracts per binding_key.
    | Proof types stay adapter-agnostic; binding_key selects the network rail.
    |
    */
    'usdc_transfer' => [
        'polygon' => [
            'chain_id' => 137,
            // Native USDC (Circle) — default for new deposits, proofs, and QR receive.
            'token_contract' => '0x3c499c542cEF5E3811e1192ce70d8cC03d5c3359',
            // Legacy bridged USDC.e — still summed for balance observation.
            'legacy_token_contracts' => [
                '0x2791Bca1f2de4661ED88A30C99A7a9449Aa84174',
            ],
            'decimals' => 6,
            'asset' => 'USDC',
            // Backlog: require current_block - tx_block >= N before accepting monetary proofs.
            // 'required_confirmations' => 12,
        ],
    ],
];
