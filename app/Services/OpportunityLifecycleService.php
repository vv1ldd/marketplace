<?php

namespace App\Services;

use App\Models\DemandGap;
use App\Models\OpportunityCase;
use Illuminate\Support\Collection;

class OpportunityLifecycleService
{
    public function openCase(
        DemandGap $gap,
        bool $autoCreated = false,
        ?string $ownerTeam = null,
        ?int $slaHours = null,
        ?string $autoReason = null,
    ): OpportunityCase
    {
        $existing = OpportunityCase::query()
            ->where('canonical_query', $gap->canonical_query)
            ->whereIn('status', [OpportunityCase::STATUS_OPEN, OpportunityCase::STATUS_IN_PROGRESS])
            ->latest()
            ->first();

        if ($existing) {
            return $existing;
        }

        return OpportunityCase::create($this->baselineSnapshot($gap) + [
            'status' => OpportunityCase::STATUS_OPEN,
            'owner_team' => $ownerTeam ?? $this->ownerTeamForDiagnosis($gap->opportunity_diagnosis),
            'sla_due_at' => now()->addHours($slaHours ?? $this->slaHoursForDiagnosis($gap->opportunity_diagnosis)),
            'auto_created' => $autoCreated,
            'auto_reason' => $autoReason,
        ]);
    }

    /**
     * @return Collection<int, OpportunityCase>
     */
    public function autoOpenCases(float $threshold = 80.0): Collection
    {
        return DemandGap::query()
            ->where('opportunity_score', '>=', $threshold)
            ->orderByDesc('opportunity_score')
            ->get()
            ->map(function (DemandGap $gap) use ($threshold): ?OpportunityCase {
                if ($this->hasActiveCase($gap)) {
                    return null;
                }

                return $this->openCase(
                    $gap,
                    autoCreated: true,
                    autoReason: sprintf(
                        'Auto-created because opportunity score %.1f is above threshold %.1f. Primary diagnosis: %s.',
                        (float) $gap->opportunity_score,
                        $threshold,
                        (string) ($gap->opportunity_diagnosis ?? 'unknown'),
                    ),
                );
            })
            ->filter()
            ->values();
    }

    public function recordAction(OpportunityCase $case, string $actionType, ?string $details = null): OpportunityCase
    {
        $case->recordAction($actionType, trim((string) $details));

        return $case->refresh();
    }

    public function resolveCase(OpportunityCase $case, bool $recalculate = true): OpportunityCase
    {
        if ($recalculate) {
            app(DemandGapEngineService::class)->recalculateGaps();
        }

        $gap = DemandGap::where('canonical_query', $case->canonical_query)->first();
        $case->resolve($gap ? $this->currentSnapshot($gap) : $this->fallbackSnapshot($case));

        return $case->refresh();
    }

    /**
     * @return array<string, float|int|string|array|null>
     */
    public function actionEffectiveness(?string $actionType = null): array
    {
        $query = OpportunityCase::query()
            ->where('status', OpportunityCase::STATUS_RESOLVED)
            ->whereNotNull('action_type');

        if ($actionType) {
            $query->where('action_type', $actionType);
        }

        $cases = $query->get();
        $total = $cases->count();

        if ($total === 0) {
            return [
                'cases_count' => 0,
                'success_rate' => 0.0,
                'avg_score_delta' => 0.0,
                'avg_gmv_growth_percentage' => 0.0,
                'avg_conversion_growth_percentage' => 0.0,
            ];
        }

        $successful = $cases->filter(function (OpportunityCase $case): bool {
            $scoreImproved = $case->after_opportunity_score !== null
                && $case->after_opportunity_score < $case->before_opportunity_score;

            return $scoreImproved
                || (float) $case->gmv_growth_percentage > 0
                || (float) $case->conversion_growth_percentage > 0;
        })->count();

        return [
            'cases_count' => $total,
            'success_rate' => round(($successful / $total) * 100, 1),
            'avg_score_delta' => round($cases->avg(fn (OpportunityCase $case): float => (float) $case->before_opportunity_score - (float) $case->after_opportunity_score), 1),
            'avg_gmv_growth_percentage' => round((float) $cases->avg('gmv_growth_percentage'), 1),
            'avg_conversion_growth_percentage' => round((float) $cases->avg('conversion_growth_percentage'), 1),
        ];
    }

    public function recommendedActionForDiagnosis(?string $diagnosis): string
    {
        return match ($diagnosis) {
            'catalog_gap' => OpportunityCase::ACTION_ADD_SUPPLY,
            'pricing_issue' => OpportunityCase::ACTION_IMPROVE_PRICING,
            'checkout_dropoff' => OpportunityCase::ACTION_FIX_CHECKOUT,
            default => OpportunityCase::ACTION_INVESTIGATE,
        };
    }

    public function ownerTeamForDiagnosis(?string $diagnosis): string
    {
        return match ($diagnosis) {
            'catalog_gap' => OpportunityCase::TEAM_CONTENT,
            'pricing_issue' => OpportunityCase::TEAM_COMMERCIAL,
            'checkout_dropoff' => OpportunityCase::TEAM_PAYMENTS,
            default => OpportunityCase::TEAM_OPERATIONS,
        };
    }

    public function slaHoursForDiagnosis(?string $diagnosis): int
    {
        return match ($diagnosis) {
            'checkout_dropoff' => 24,
            'catalog_gap' => 48,
            'pricing_issue' => 72,
            default => 96,
        };
    }

    private function hasActiveCase(DemandGap $gap): bool
    {
        return OpportunityCase::query()
            ->where('canonical_query', $gap->canonical_query)
            ->whereIn('status', [OpportunityCase::STATUS_OPEN, OpportunityCase::STATUS_IN_PROGRESS])
            ->exists();
    }

    /**
     * @return array<string, float|int|string|array|null>
     */
    private function baselineSnapshot(DemandGap $gap): array
    {
        return [
            'canonical_query' => $gap->canonical_query,
            'before_opportunity_score' => (float) $gap->opportunity_score,
            'before_search_volume' => (int) $gap->search_volume,
            'before_views_count' => (int) $gap->views_count,
            'before_carts_count' => (int) $gap->carts_count,
            'before_orders_count' => (float) $gap->attributed_orders_count,
            'before_gmv' => (float) $gap->attributed_gmv,
            'before_diagnosis' => (string) ($gap->opportunity_diagnosis ?? 'unknown'),
            'before_diagnosis_graph' => $gap->opportunity_diagnosis_graph ?? [],
        ];
    }

    /**
     * @return array<string, float|int|string|array|null>
     */
    private function currentSnapshot(DemandGap $gap): array
    {
        return [
            'opportunity_score' => (float) $gap->opportunity_score,
            'search_volume' => (int) $gap->search_volume,
            'views_count' => (int) $gap->views_count,
            'carts_count' => (int) $gap->carts_count,
            'attributed_orders_count' => (float) $gap->attributed_orders_count,
            'attributed_gmv' => (float) $gap->attributed_gmv,
            'opportunity_diagnosis' => (string) ($gap->opportunity_diagnosis ?? 'unknown'),
            'opportunity_diagnosis_graph' => $gap->opportunity_diagnosis_graph ?? [],
        ];
    }

    /**
     * @return array<string, float|int|string|array|null>
     */
    private function fallbackSnapshot(OpportunityCase $case): array
    {
        return [
            'opportunity_score' => (float) $case->before_opportunity_score,
            'search_volume' => (int) $case->before_search_volume,
            'views_count' => (int) $case->before_views_count,
            'carts_count' => (int) $case->before_carts_count,
            'attributed_orders_count' => (float) $case->before_orders_count,
            'attributed_gmv' => (float) $case->before_gmv,
            'opportunity_diagnosis' => (string) $case->before_diagnosis,
            'opportunity_diagnosis_graph' => $case->before_diagnosis_graph ?? [],
        ];
    }
}
