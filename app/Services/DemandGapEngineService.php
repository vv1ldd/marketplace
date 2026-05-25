<?php

namespace App\Services;

use App\Models\CatalogSearchLog;
use App\Models\DemandGap;
use App\Models\Order\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemandGapEngineService
{
    /**
     * Recalculate and update the demand_gaps table with supply-deficit analytics.
     */
    public function recalculateGaps(): void
    {
        // 1. Fetch search log aggregations grouped by canonical (normalized) query
        $logs = CatalogSearchLog::query()
            ->select([
                'normalized_query',
                DB::raw("COUNT(*) as search_volume"),
                DB::raw("SUM(views_count) as views_count"),
                DB::raw("SUM(carts_count) as carts_count"),
                DB::raw("SUM(CASE WHEN results_count = 0 THEN 1 ELSE 0 END) as zero_results_count"),
                DB::raw("AVG(results_count) as average_results_count"),
                DB::raw("MAX(created_at) as last_searched_at"),
            ])
            ->groupBy('normalized_query')
            ->get();

        foreach ($logs as $log) {
            $query = $log->normalized_query;
            $searchVolume = (int) $log->search_volume;
            $viewsCount = (int) $log->views_count;
            $cartsCount = (int) $log->carts_count;
            $zeroResultsCount = (int) $log->zero_results_count;
            $avgResultsCount = (float) $log->average_results_count;

            // 2. Fetch all log IDs in this canonical query group to count attributed sales & GMV
            $logIds = CatalogSearchLog::where('normalized_query', $query)->pluck('id');

            // Retrieve multi-touch attribution metrics with backward-compatible legacy fallback
            $hasAttributions = \App\Models\Order\OrderSearchAttribution::whereIn('search_log_id', $logIds)->exists();
            if ($hasAttributions) {
                $salesCount = (float) \App\Models\Order\OrderSearchAttribution::whereIn('search_log_id', $logIds)->sum('attribution_weight');
                $salesGmv = (float) \App\Models\Order\OrderSearchAttribution::whereIn('search_log_id', $logIds)->sum('attributed_gmv');
            } else {
                $salesCount = (float) Order::whereIn('search_log_id', $logIds)->count();
                $salesGmv = (float) Order::whereIn('search_log_id', $logIds)->sum('total_amount');
            }

            // Round values cleanly
            $salesCount = round($salesCount, 2);
            $salesGmv = round($salesGmv, 2);

            // 3. Determine dynamically calculated average price for brand/category or fallback to baseline
            $avgPrice = $this->determineAveragePriceForQuery($logIds);
            $entityKeys = $this->entityKeysForQuery($logIds);

            // 4. Calculate Supply-Demand Deficit Factor
            $gapFactor = $searchVolume > 0 
                ? ($zeroResultsCount + ($searchVolume - $zeroResultsCount) * (1 / (1 + $avgResultsCount))) / $searchVolume
                : 0;

            // 5. Estimate Lost GMV & Score
            $estimatedLostGmv = round($searchVolume * $gapFactor * $avgPrice, 2);
            $score = $estimatedLostGmv;

            // Calculate Opportunity Score v2 (0 - 100)
            // 1. Popularity Weight: min(25, (searchVolume / 120) * 25)
            $popularityWeight = min(25, ($searchVolume / 120) * 25);
            // 2. Deficit Weight: gapFactor * 35
            $deficitWeight = $gapFactor * 35;
            // 3. Funnel Dropoff Weight: min(20, ((carts - orders) / carts) * 20)
            $funnelDropoffWeight = 0;
            if ($cartsCount > 0) {
                $dropoff = max(0, $cartsCount - $salesCount);
                $funnelDropoffWeight = min(20, ($dropoff / $cartsCount) * 20);
            }
            // 4. Lost GMV Weight: min(20, (estimatedLostGmv / 50000) * 20)
            $lostGmvWeight = min(20, ($estimatedLostGmv / 50000) * 20);

            $opportunityScore = round($popularityWeight + $deficitWeight + $funnelDropoffWeight + $lostGmvWeight, 1);

            // Calculate Multi-Cause Diagnosis Graph (Root Cause Engine)
            $graph = [];
            $primaryDiagnosis = 'unknown';
            $primaryConfidence = 0.0;

            if ($searchVolume < 3) {
                $graph[] = ['cause' => 'insufficient_data', 'score' => 0.0];
                $primaryDiagnosis = 'insufficient_data';
                $primaryConfidence = 0.0;
            } else {
                // 1. Checkout Dropoff Score
                $dropoffScore = 0.0;
                if ($cartsCount > 0 && (($cartsCount - $salesCount) / $cartsCount) >= 0.6) {
                    $dropoffRatio = ($cartsCount - $salesCount) / $cartsCount;
                    $dropoffScore = min(99.0, max(50.0, round($dropoffRatio * 100, 1)));
                    $graph[] = ['cause' => 'checkout_dropoff', 'score' => $dropoffScore];
                }

                // 2. Catalog Gap Score
                $catalogScore = 0.0;
                if (($zeroResultsCount / $searchVolume) >= 0.4 || ($viewsCount / $searchVolume) < 0.3) {
                    $ratio = $zeroResultsCount > 0 ? ($zeroResultsCount / $searchVolume) : (1 - ($viewsCount / $searchVolume));
                    $catalogScore = min(99.0, max(50.0, round($ratio * 100, 1)));
                    $graph[] = ['cause' => 'catalog_gap', 'score' => $catalogScore];
                }

                // 3. Pricing / Content Score
                $pricingScore = 0.0;
                if ($viewsCount > 0 && ($cartsCount / $viewsCount) < 0.3) {
                    $pricingRatio = 1 - ($cartsCount / $viewsCount);
                    $pricingScore = min(99.0, max(50.0, round($pricingRatio * 100, 1)));
                    $graph[] = ['cause' => 'pricing_issue', 'score' => $pricingScore];
                }

                // 4. Healthy Score
                $healthyScore = 0.0;
                if (($salesCount / $searchVolume) >= 0.1) {
                    $convRatio = $salesCount / $searchVolume;
                    $healthyScore = min(99.0, max(50.0, round($convRatio * 100, 1)));
                    $graph[] = ['cause' => 'healthy', 'score' => $healthyScore];
                }

                // Sort graph by score descending
                usort($graph, fn($a, $b) => $b['score'] <=> $a['score']);

                if (!empty($graph)) {
                    $primaryDiagnosis = $graph[0]['cause'];
                    $primaryConfidence = $graph[0]['score'];
                }
            }

            // 6. Map Priority based on Estimated Lost GMV Model
            $priority = 'low';
            if ($estimatedLostGmv >= 50000) {
                $priority = 'critical';
            } elseif ($estimatedLostGmv >= 10000) {
                $priority = 'high';
            } elseif ($estimatedLostGmv >= 2000) {
                $priority = 'medium';
            }

            // 7. Update or Create Demand Gap record
            DemandGap::updateOrCreate(
                ['canonical_query' => $query],
                [
                    'brand_entity_key' => $entityKeys['brand'],
                    'region_entity_key' => $entityKeys['region'],
                    'category_entity_key' => $entityKeys['category'],
                    'search_volume' => $searchVolume,
                    'views_count' => $viewsCount,
                    'carts_count' => $cartsCount,
                    'zero_results_count' => $zeroResultsCount,
                    'average_results_count' => $avgResultsCount,
                    'attributed_orders_count' => $salesCount,
                    'attributed_gmv' => $salesGmv,
                    'estimated_lost_gmv' => $estimatedLostGmv,
                    'opportunity_score' => $opportunityScore,
                    'opportunity_diagnosis' => $primaryDiagnosis,
                    'diagnosis_confidence' => $primaryConfidence,
                    'opportunity_diagnosis_graph' => $graph,
                    'demand_gap_score' => $score,
                    'priority_label' => $priority,
                    'last_searched_at' => $log->last_searched_at,
                ]
            );
        }
    }

    /**
     * @return array{brand: string|null, region: string|null, category: string|null}
     */
    private function entityKeysForQuery($logIds): array
    {
        $log = CatalogSearchLog::whereIn('id', $logIds)
            ->whereNotNull('filters')
            ->latest('id')
            ->first();

        $filters = (array) ($log?->filters ?? []);

        return [
            'brand' => filled($filters['brand'] ?? null) ? Str::slug((string) $filters['brand']) : null,
            'region' => filled($filters['region'] ?? null) ? Str::slug((string) $filters['region']) : null,
            'category' => filled($filters['category'] ?? null) ? Str::slug((string) $filters['category']) : null,
        ];
    }

    /**
     * Helper to compute dynamic average price based on orders of brand/category in log filters.
     */
    private function determineAveragePriceForQuery($logIds): float
    {
        $sampleLog = CatalogSearchLog::whereIn('id', $logIds)
            ->whereNotNull('filters')
            ->first();

        if ($sampleLog && ! empty($sampleLog->filters)) {
            $brand = $sampleLog->filters['brand'] ?? null;
            $category = $sampleLog->filters['category'] ?? null;

            if ($brand) {
                $avg = Order::whereHas('items', fn($q) => $q->where('sku', 'like', "%{$brand}%"))
                    ->avg('total_amount');
                if ($avg > 0) {
                    return (float) $avg;
                }
            }

            if ($category) {
                $avg = Order::whereHas('items', fn($q) => $q->where('sku', 'like', "%{$category}%"))
                    ->avg('total_amount');
                if ($avg > 0) {
                    return (float) $avg;
                }
            }
        }

        return 1000.00; // Baseline default of 1,000 RUB
    }
}
