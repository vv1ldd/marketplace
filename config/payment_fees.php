<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Payment fee policy versions (frozen specifications)
    |--------------------------------------------------------------------------
    |
    | Fees are quoted at T0 and frozen on the payment intent snapshot.
    | Settlement transfers the payment amount; fee is a separate ledger fact.
    |
    */
    'default' => env('PAYMENT_FEE_POLICY_VERSION', 'v1'),

    'versions' => [
        'v1' => [
            'version' => 'payment-fees:v1',
            'managed_evm' => [
                'USDC' => [
                    'type' => 'percentage',
                    'bps' => 50,
                ],
            ],
        ],

        'v2' => [
            'version' => 'payment-fees:v2',
            'managed_evm' => [
                'USDC' => [
                    'type' => 'percentage',
                    'bps' => 100,
                ],
            ],
        ],
    ],
];
