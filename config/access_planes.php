<?php

return [
    'planes' => [
        'storefront' => [
            'label' => 'Storefront',
            'route' => 'home',
            'authority' => 'public',
            'description' => 'Browse and purchase products',
            'requires_auth' => false,
        ],

        'vault' => [
            'label' => 'Vault / Cabinet',
            'route' => 'cabinet.dashboard',
            'authority' => 'owner',
            'description' => 'Personal identity, purchases, and assets',
            'requires_auth' => true,
        ],

        'partner' => [
            'label' => 'Partner Console',
            'route' => 'partner.dashboard',
            'authority' => 'partner',
            'description' => 'B2B seller operations and supply management',
            'requires_auth' => true,
            'required_roles' => ['b2b_partner'],
            'required_legal_entity' => true,
        ],

        'ops' => [
            'label' => 'Ops Center',
            'route' => 'ops.dashboard',
            'authority' => 'operator',
            'description' => 'Global platform operations',
            'requires_auth' => true,
            'required_roles' => ['super_admin'],
            'requires_sovereign_identity' => true,
        ],

        'tribunal' => [
            'label' => 'Ledger Tribunal',
            'route' => 'ops.dashboard',
            'route_params' => ['tab' => 'tribunal'],
            'authority' => 'auditor',
            'description' => 'Sovereign ledger integrity and audit oracle',
            'requires_auth' => true,
            'required_roles' => ['super_admin', 'auditor'],
            'requires_sovereign_identity' => true,
        ],

        'decision_console' => [
            'label' => 'Decision Console',
            'route' => 'ops.dashboard',
            'route_params' => ['tab' => 'decision-console'],
            'authority' => 'governance',
            'description' => 'Authorize semantic market model evolution',
            'requires_auth' => true,
            'required_roles' => ['super_admin'],
            'requires_sovereign_identity' => true,
        ],
    ],
];
