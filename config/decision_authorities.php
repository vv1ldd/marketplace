<?php

use App\Models\SearchDemandRecommendation;

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
            'approve' => ['roles' => ['super_admin']],
            'reject' => ['roles' => ['super_admin']],
        ],
        'ADD_PRODUCT' => [
            'approve' => ['roles' => ['super_admin']],
            'reject' => ['roles' => ['super_admin']],
        ],
        'ADD_REGION_VARIANT' => [
            'approve' => ['roles' => ['super_admin']],
            'reject' => ['roles' => ['super_admin']],
        ],
        'IMPROVE_RANKING' => [
            'approve' => ['roles' => ['super_admin']],
            'reject' => ['roles' => ['super_admin']],
        ],
        'IMPROVE_SUPPLY' => [
            'approve' => ['roles' => ['super_admin', 'b2b_partner']],
            'reject' => ['roles' => ['super_admin', 'b2b_partner']],
        ],
        'OPEN_PARTNER_OPPORTUNITY' => [
            'approve' => ['roles' => ['super_admin', 'b2b_partner']],
            'reject' => ['roles' => ['super_admin', 'b2b_partner']],
        ],
        'CREATE_COLLECTION' => [
            'approve' => ['roles' => ['super_admin']],
            'reject' => ['roles' => ['super_admin']],
        ],
        'APPLY_REBUILD' => [
            'approve' => [
                'roles' => ['super_admin'],
                'dual_control' => true,
            ],
            'reject' => ['roles' => ['super_admin']],
        ],
    ],

    'default' => [
        'approve' => ['roles' => ['super_admin']],
        'reject' => ['roles' => ['super_admin']],
    ],
];
