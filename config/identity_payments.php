<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Identity-native payments (v3b)
    |--------------------------------------------------------------------------
    |
    | PaymentIntent operates on identity graphs — not address-to-address sends.
    | Execution requires managed EVM bindings on a shared network rail.
    |
    */
    'enabled' => filter_var(env('IDENTITY_PAYMENTS_ENABLED', false), FILTER_VALIDATE_BOOL),

    'execute_enabled' => filter_var(env('IDENTITY_PAYMENTS_EXECUTE', false), FILTER_VALIDATE_BOOL),

    'disputes_enabled' => filter_var(env('IDENTITY_PAYMENT_DISPUTES_ENABLED', false), FILTER_VALIDATE_BOOL),

    /**
     * Preferred EVM network order when sender and recipient share multiple rails.
     *
     * @var list<string>
     */
    'network_preference' => [
        'polygon',
        'base',
        'ethereum',
    ],

    'assets' => [
        'USDC' => [
            'decimals' => 6,
        ],
    ],
];
