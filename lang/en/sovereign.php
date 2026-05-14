<?php

return [
    'navigation' => [
        'groups' => [
            'network' => 'Sovereign Network',
            'liquidity' => 'Liquidity Hub',
            'intelligence' => 'Currency Intelligence',
        ],
        'pathfinder' => 'Sovereign Pathfinder',
        'matrix' => 'Sovereign Matrix',
        'ledger' => 'Causality Ledger (MDK)',
        'corridors' => 'Liquidity Corridors',
        'methods' => 'Liquidity Methods',
        'mappings' => 'Provider Mappings',
        'pairs' => 'Currency Pairs',
        'currencies' => 'Currencies',
        'countries' => 'Mapping Countries',
    ],
    'pathfinder' => [
        'title' => 'Sovereign Liquidity Pathfinder',
        'description' => 'Autonomous routing engine for navigating fragmented liquidity and sanctioned corridors.',
        'form' => [
            'amount' => 'Execution Amount',
            'from' => 'Source Asset',
            'to' => 'Destination Asset',
            'calculate' => 'Find Routes',
        ],
        'route' => [
            'rate' => 'Rate',
            'spread' => 'Spread',
            'trust' => 'Trust Level',
            'capacity' => 'Available Liquidity',
            'obs' => 'Observability',
            'stress' => 'Stress Index',
            'rails' => 'Supported Rails',
            'inbound' => 'Inbound Methods',
            'outbound' => 'Outbound Methods',
        ],
    ],
    'corridors' => [
        'fields' => [
            'node' => 'Provider Node',
            'node_hint' => 'The identity of the entity executing the transfer (e.g. "Dubai OTC Desk").',
            'bridge' => 'Bridge Asset',
            'bridge_hint' => 'The intermediary asset used for settlement (usually USDT).',
            'tier' => 'Trust Tier',
            'tier_hint' => 'Lower tiers are institutional, higher tiers are P2P/Shadow.',
            'sla' => 'Settlement Time (SLA)',
            'sla_hint' => 'Guaranteed time until the final asset reaches the destination.',
        ],
    ],
    'methods' => [
        'fields' => [
            'name' => 'Method Name',
            'type' => 'Payment Rail Type',
            'is_global' => 'Global Availability',
            'is_global_hint' => 'If enabled, this rail is available for all currency pairs by default.',
        ],
    ],
    'ledger' => [
        'title' => 'Causality Ledger',
        'description' => 'Immutable chronography of system events preserving proven identity and state physics.',
        'fields' => [
            'source' => 'Execution Source',
            'input' => 'Input Arguments (IN)',
            'output' => 'Resulting State (OUT)',
            'fingerprint' => 'Fingerprint (SHA-256)',
            'previous' => 'Previous Link',
        ],
    ],
];
