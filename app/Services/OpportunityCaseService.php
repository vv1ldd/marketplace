<?php

namespace App\Services;

use App\Models\DemandGap;
use App\Models\OpportunityCase;

class OpportunityCaseService
{
    /**
     * Create a new open Opportunity Case from an active Demand Gap.
     * Prevents duplicate open/in-progress cases for the same query.
     */
    public function createFromGap(DemandGap $gap): OpportunityCase
    {
        $existing = OpportunityCase::where('canonical_query', $gap->canonical_query)
            ->whereIn('status', ['open', 'in_progress'])
            ->first();

        if ($existing) {
            return $existing;
        }

        return OpportunityCase::create([
            'canonical_query' => $gap->canonical_query,
            'status' => 'open',
            'before_opportunity_score' => $gap->opportunity_score,
            'before_search_volume' => $gap->search_volume,
            'before_views_count' => $gap->views_count,
            'before_carts_count' => $gap->carts_count,
            'before_orders_count' => $gap->attributed_orders_count,
            'before_gmv' => $gap->attributed_gmv,
            'before_diagnosis' => $gap->opportunity_diagnosis,
            'before_diagnosis_graph' => $gap->opportunity_diagnosis_graph ?? [],
        ]);
    }

    /**
     * Compute historical performance aggregates per action_type.
     * Success is defined as after_opportunity_score < before_opportunity_score OR gmv_growth_percentage > 0.
     */
    public function calculateHistoricalStats(): array
    {
        $resolvedCases = OpportunityCase::where('status', 'resolved')->get();

        $stats = [
            'add_supply' => ['success_rate' => 0.0, 'avg_gmv_growth' => 0.0, 'total' => 0],
            'improve_pricing' => ['success_rate' => 0.0, 'avg_gmv_growth' => 0.0, 'total' => 0],
            'fix_checkout' => ['success_rate' => 0.0, 'avg_gmv_growth' => 0.0, 'total' => 0],
        ];

        $grouped = $resolvedCases->groupBy('action_type');

        foreach ($grouped as $actionType => $cases) {
            if (!isset($stats[$actionType])) {
                continue;
            }

            $total = $cases->count();
            if ($total === 0) {
                continue;
            }

            $successCount = 0;
            $gmvGrowths = [];

            foreach ($cases as $case) {
                $isSuccessful = ($case->after_opportunity_score < $case->before_opportunity_score)
                    || ($case->gmv_growth_percentage > 0.0);

                if ($isSuccessful) {
                    $successCount++;
                }

                if ($case->gmv_growth_percentage > 0.0) {
                    $gmvGrowths[] = $case->gmv_growth_percentage;
                }
            }

            $successRate = ($successCount / $total) * 100;
            $avgGmvGrowth = count($gmvGrowths) > 0 ? (array_sum($gmvGrowths) / count($gmvGrowths)) : 0.0;

            $stats[$actionType] = [
                'success_rate' => round($successRate, 1),
                'avg_gmv_growth' => round($avgGmvGrowth, 1),
                'total' => $total,
            ];
        }

        return $stats;
    }
}
