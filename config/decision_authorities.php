<?php

use App\Models\SearchDemandRecommendation;
use App\Models\User;

return [
    'transitions' => [
        SearchDemandRecommendation::STATUS_PROPOSED => [
            SearchDemandRecommendation::STATUS_APPROVED,
            SearchDemandRecommendation::STATUS_REJECTED,
        ],
        SearchDemandRecommendation::STATUS_APPROVED => [
            SearchDemandRecommendation::STATUS_REJECTED,
            SearchDemandRecommendation::STATUS_APPLIED,
        ],
        SearchDemandRecommendation::STATUS_REJECTED => [
            SearchDemandRecommendation::STATUS_APPROVED,
        ],
        SearchDemandRecommendation::STATUS_APPLIED => [],
    ],

    'types' => [
        'ADD_ALIAS' => [
            'approve' => ['roles' => [User::ROLE_SOVEREIGN_VALIDATOR]],
            'reject' => ['roles' => [User::ROLE_SOVEREIGN_VALIDATOR]],
        ],
        'ADD_PRODUCT' => [
            'approve' => ['roles' => [User::ROLE_SOVEREIGN_VALIDATOR]],
            'reject' => ['roles' => [User::ROLE_SOVEREIGN_VALIDATOR]],
        ],
        'ADD_REGION_VARIANT' => [
            'approve' => ['roles' => [User::ROLE_SOVEREIGN_VALIDATOR]],
            'reject' => ['roles' => [User::ROLE_SOVEREIGN_VALIDATOR]],
        ],
        'IMPROVE_RANKING' => [
            'approve' => ['roles' => [User::ROLE_SOVEREIGN_VALIDATOR]],
            'reject' => ['roles' => [User::ROLE_SOVEREIGN_VALIDATOR]],
        ],
        'IMPROVE_SUPPLY' => [
            'approve' => ['roles' => [User::ROLE_SOVEREIGN_VALIDATOR, User::ROLE_MERCHANT_NODE]],
            'reject' => ['roles' => [User::ROLE_SOVEREIGN_VALIDATOR, User::ROLE_MERCHANT_NODE]],
        ],
        'OPEN_PARTNER_OPPORTUNITY' => [
            'approve' => ['roles' => [User::ROLE_SOVEREIGN_VALIDATOR, User::ROLE_MERCHANT_NODE]],
            'reject' => ['roles' => [User::ROLE_SOVEREIGN_VALIDATOR, User::ROLE_MERCHANT_NODE]],
        ],
        'CREATE_COLLECTION' => [
            'approve' => ['roles' => [User::ROLE_SOVEREIGN_VALIDATOR]],
            'reject' => ['roles' => [User::ROLE_SOVEREIGN_VALIDATOR]],
        ],
        'APPLY_REBUILD' => [
            'approve' => [
                'roles' => [User::ROLE_SOVEREIGN_VALIDATOR],
                'dual_control' => true,
            ],
            'reject' => ['roles' => [User::ROLE_SOVEREIGN_VALIDATOR]],
        ],
    ],

    'default' => [
        'approve' => ['roles' => [User::ROLE_SOVEREIGN_VALIDATOR]],
        'reject' => ['roles' => [User::ROLE_SOVEREIGN_VALIDATOR]],
    ],
];
