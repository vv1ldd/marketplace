<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Weighted competitive routing (ADR 0039 Phase A)
    |--------------------------------------------------------------------------
    |
    | When disabled, OfferRoutingService keeps the legacy intent-based ranking.
    | With a single provider, weighted routing still resolves to 100% traffic.
    |
    */
    'enabled' => env('ROUTING_WEIGHTED_ENABLED', false),

    'default_policy' => 'weighted',

    'policy_version' => 'v1',

    // Metric coefficients (sum need not equal 1; normalized per request).
    'weights' => [
        'margin' => 0.40,
        'success_rate' => 0.30,
        'latency' => 0.15,
        'stock' => 0.15,
    ],

    'circuit_breaker' => [
        'failure_threshold' => 5,
        'cool_down_seconds' => 60,
        'critical_alerts' => [
            'architecture.anomaly.settlement_without_execution',
        ],
    ],

    /*
    | Optional traffic split by provider_id. When empty, the highest-scoring
    | provider wins (deterministic tie-break). Example:
    | [ ['provider_id' => 42, 'traffic_weight' => 70], ... ]
    */
    'provider_split' => [],

];
