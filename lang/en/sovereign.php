<?php

return [
    'navigation' => [
        'groups' => [
            'network' => 'Payment Network',
            'liquidity' => 'Payment Hub',
            'intelligence' => 'Currency Intelligence',
        ],
        'pathfinder' => 'Payment Routes',
        'matrix' => 'Currency Rates',
        'ledger' => 'Operations History',
        'corridors' => 'Payment Routes',
        'methods' => 'Liquidity Methods',
        'mappings' => 'Provider Mappings',
        'pairs' => 'Currency Pairs',
        'currencies' => 'Currencies',
        'countries' => 'Mapping Countries',
    ],
    'pathfinder' => [
        'title' => 'Payment Route Finder',
        'description' => 'Helps choose a clear payment route based on rate, availability, and timing.',
        'form' => [
            'amount' => 'Execution Amount',
            'from' => 'Source Currency',
            'to' => 'Destination Currency',
            'calculate' => 'Find Routes',
        ],
        'route' => [
            'rate' => 'Rate',
            'spread' => 'Spread',
            'trust' => 'Trust Level',
            'capacity' => 'Available Volume',
            'obs' => 'Observability',
            'stress' => 'Stress Index',
            'rails' => 'Supported Methods',
            'inbound' => 'Inbound Methods',
            'outbound' => 'Outbound Methods',
        ],
    ],
    'corridors' => [
        'fields' => [
            'node' => 'Provider',
            'node_hint' => 'The organization or team executing the transfer.',
            'bridge' => 'Intermediate Currency',
            'bridge_hint' => 'The currency used to complete the settlement route.',
            'tier' => 'Trust Tier',
            'tier_hint' => 'Lower tiers are institutional, higher tiers are P2P/Shadow.',
            'sla' => 'Settlement Time (SLA)',
            'sla_hint' => 'Guaranteed time until the final asset reaches the destination.',
        ],
    ],
    'methods' => [
        'fields' => [
            'name' => 'Method Name',
            'type' => 'Payment Method Type',
            'is_global' => 'Global Availability',
            'is_global_hint' => 'If enabled, this method is available for all currency pairs by default.',
        ],
    ],
    'ledger' => [
        'title' => 'Operations History',
        'description' => 'Clear system event history for control, support, and dispute resolution.',
        'fields' => [
            'source' => 'Execution Source',
            'input' => 'Input Arguments (IN)',
            'output' => 'Resulting State (OUT)',
            'fingerprint' => 'Control Mark',
            'previous' => 'Previous Record',
        ],
    ],
];
