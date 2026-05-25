<?php

return [
    'currency' => 'SL1',
    'rub_rate' => 100.0,
    'tariff_version' => '2026-05-usage-v1',

    'tariffs' => [
        'recommendation_generated' => [
            'layer' => 'usage',
            'unit' => 'recommendation',
            'sl1_per_unit' => 0.0500,
        ],
        'recommendation_used' => [
            'layer' => 'usage',
            'unit' => 'interaction',
            'sl1_per_unit' => 0.0200,
        ],
        'recommendation_hit' => [
            'layer' => 'usage',
            'unit' => 'successful_outcome',
            'sl1_per_unit' => 0.0000,
        ],
        'ai_audit_run' => [
            'layer' => 'usage',
            'unit' => 'audit',
            'sl1_per_unit' => 2.0000,
        ],
        'ai_audit_object' => [
            'layer' => 'usage',
            'unit' => 'checked_object',
            'sl1_per_unit' => 0.0100,
        ],
        'catalog_sync' => [
            'layer' => 'usage',
            'unit' => 'sync',
            'sl1_per_unit' => 0.2500,
        ],
        'publish_offer' => [
            'layer' => 'usage',
            'unit' => 'offer',
            'sl1_per_unit' => 0.0100,
        ],
        'api_request_1000' => [
            'layer' => 'usage',
            'unit' => 'request_batch',
            'sl1_per_unit' => 0.1000,
        ],
        'order_fulfillment' => [
            'layer' => 'commerce',
            'unit' => 'order',
            'sl1_per_unit' => 0.1500,
        ],
        'marketplace_usage_fee' => [
            'layer' => 'commerce',
            'unit' => 'fee',
            'sl1_per_unit' => 0.0000,
        ],
        'marketplace_fixed_fee' => [
            'layer' => 'commerce',
            'unit' => 'fee',
            'sl1_per_unit' => 0.0000,
        ],
        'marketplace_success_fee' => [
            'layer' => 'commerce',
            'unit' => 'success_fee',
            'sl1_per_unit' => 0.0000,
            'rate_bps' => 0,
        ],
        'channel_publish_fee' => [
            'layer' => 'commerce',
            'unit' => 'channel_publish',
            'sl1_per_unit' => 0.0500,
        ],
    ],
];
