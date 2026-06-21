<?php

return [
    'stream_enabled' => (bool) env('IDENTITY_GOVERNANCE_STREAM_ENABLED', false),
    'stream_authorize_enabled' => (bool) env('IDENTITY_GOVERNANCE_STREAM_AUTHORIZE_ENABLED', false),
    'replay_budget' => [
        'max_ms_per_1k_events' => (int) env('IDENTITY_GOVERNANCE_REPLAY_MS_PER_1K', 500),
        'max_full_replay_ms' => (int) env('IDENTITY_GOVERNANCE_MAX_FULL_REPLAY_MS', 30_000),
        'warn_stream_event_count' => (int) env('IDENTITY_GOVERNANCE_WARN_STREAM_EVENTS', 10_000),
    ],
];
