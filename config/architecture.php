<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Snapshot-only fulfillment (ADR 0038 Phase 4)
    |--------------------------------------------------------------------------
    |
    | When enabled, provider dispatch reads SKU/provider context from pinned
    | OfferSnapshot instead of live WildflowCatalog / ProviderProduct rows.
    |
    */
    'snapshot_fulfillment_mode' => env('ARCHITECTURE_SNAPSHOT_FULFILLMENT', false),

    /*
    |--------------------------------------------------------------------------
    | Sidecar writes
    |--------------------------------------------------------------------------
    |
    | Parallel OfferSnapshot + ExecutionRecord writes at checkout / redeem.
    | Disable only for emergency rollback; fulfillment keeps legacy fallback.
    |
    */
    'sidecar_enabled' => env('ARCHITECTURE_SIDECAR_ENABLED', true),
];
