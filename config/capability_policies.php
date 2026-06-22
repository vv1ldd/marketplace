<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Capability policy versions (frozen specifications)
    |--------------------------------------------------------------------------
    |
    | Each version is immutable once published. New rails extend via v2, v3…
    | never by mutating v1. Intents snapshot the version active at T0.
    |
    */
    'default' => env('CAPABILITY_POLICY_VERSION', 'v1'),

    'versions' => [
        'v1' => [
            'version' => 'instrument-capability-policy:v1',
            'assets' => [
                'USDC' => [
                    'payment_routing' => [
                        'polygon_managed',
                        'base_managed',
                        'ethereum_managed',
                    ],
                ],
            ],
            'network_preference' => [
                'polygon',
                'base',
                'ethereum',
            ],
        ],

        'v2' => [
            'version' => 'instrument-capability-policy:v2',
            'assets' => [
                'USDC' => [
                    'payment_routing' => [
                        'polygon_managed',
                        'base_managed',
                        'ethereum_managed',
                        'solana_verified',
                    ],
                ],
            ],
            'network_preference' => [
                'solana',
                'polygon',
                'base',
                'ethereum',
            ],
        ],
    ],
];
