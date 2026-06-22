<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Payment limit policy versions (frozen specifications)
    |--------------------------------------------------------------------------
    |
    | Limits are evaluated at T0 and frozen on the payment intent snapshot.
    | Daily consumption is derived from accounting events — never a counter.
    |
    */
    'default' => env('PAYMENT_LIMIT_POLICY_VERSION', 'v1'),

    'versions' => [
        'v1' => [
            'version' => 'payment-limits:v1',
            'daily_consumption_mode' => 'net_outbound',
            'managed_evm' => [
                'USDC' => [
                    'per_transaction' => '10000',
                    'daily' => '50000',
                ],
            ],
        ],

        'v2' => [
            'version' => 'payment-limits:v2',
            'daily_consumption_mode' => 'net_outbound',
            'managed_evm' => [
                'USDC' => [
                    'per_transaction' => '1000',
                    'daily' => '5000',
                ],
            ],
        ],
    ],
];
