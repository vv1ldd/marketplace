<?php

namespace App\Services;

use App\Models\DemandGap;
use App\Models\OpportunityCase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class OpportunityGraphService
{
    public function __construct(
        private readonly DiscoveryEntityGraphService $entities,
        private readonly OpportunityLifecycleService $lifecycle,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function entity(string $type, string $slug): ?array
    {
        return match ($type) {
            'commerce' => $this->commerceEntity($slug),
            'brands' => $this->brandEntity($slug),
            'regions' => $this->regionEntity($slug),
            'intersections' => $this->intersectionEntity($slug),
            default => null,
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function commerceEntity(string $slug): ?array
    {
        $commerce = app(CommerceEntityGraphService::class);
        $entity = $commerce->resolveBySlug($slug);

        if (! $entity) {
            return null;
        }

        return [
            'type' => 'MeanlyCommerceEntityNode',
            'version' => 1,
            'generated_at' => now()->toIso8601String(),
            'entity' => [
                'type' => 'commerce_entity',
                'slug' => $entity->slug,
                'entity_type' => $entity->entity_type,
                'canonical_query' => $entity->canonical_query,
                'attributes' => $entity->attributes ?? [],
            ],
            'metrics' => $entity->metrics ? [
                'searches' => $entity->metrics->searches,
                'views' => $entity->metrics->views,
                'carts' => $entity->metrics->carts,
                'orders' => $entity->metrics->orders,
                'attributed_gmv' => $entity->metrics->attributed_gmv,
                'estimated_lost_gmv' => $entity->metrics->estimated_lost_gmv,
                'opportunity_score' => $entity->metrics->opportunity_score,
                'active_cases' => $entity->metrics->active_cases,
                'resolved_cases' => $entity->metrics->resolved_cases,
                'calculated_at' => optional($entity->metrics->calculated_at)->toIso8601String(),
            ] : null,
            'links' => $entity->links
                ->map(fn ($link): array => [
                    'type' => $link->link_type,
                    'id' => $link->link_id,
                    'confidence' => (float) $link->confidence,
                    'signals' => $link->signals ?? [],
                ])
                ->values(),
            'offers' => $commerce->offers($entity),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function opportunities(array $filters = []): array
    {
        $query = DemandGap::query();

        if (filled($filters['brand'] ?? null)) {
            $query->where('brand_entity_key', (string) $filters['brand']);
        }

        if (filled($filters['region'] ?? null)) {
            $query->where('region_entity_key', (string) $filters['region']);
        }

        if (filled($filters['category'] ?? null)) {
            $query->where('category_entity_key', (string) $filters['category']);
        }

        if (isset($filters['min_score'])) {
            $query->where('opportunity_score', '>=', (float) $filters['min_score']);
        }

        if (array_key_exists('has_active_case', $filters)) {
            $hasActive = filter_var($filters['has_active_case'], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
            if ($hasActive === true) {
                $query->whereExists(fn ($subquery) => $this->activeCaseSubquery($subquery));
            } elseif ($hasActive === false) {
                $query->whereNotExists(fn ($subquery) => $this->activeCaseSubquery($subquery));
            }
        }

        $sort = (string) ($filters['sort'] ?? 'opportunity_score');
        $direction = (string) ($filters['direction'] ?? 'desc');
        $direction = $direction === 'asc' ? 'asc' : 'desc';
        $sortColumn = match ($sort) {
            'lost_gmv', 'estimated_lost_gmv' => 'estimated_lost_gmv',
            'searches', 'search_volume' => 'search_volume',
            'gmv', 'attributed_gmv' => 'attributed_gmv',
            default => 'opportunity_score',
        };

        $limit = max(1, min((int) ($filters['limit'] ?? 50), 100));
        $items = $query
            ->orderBy($sortColumn, $direction)
            ->limit($limit)
            ->get()
            ->map(fn (DemandGap $gap): array => $this->opportunitySummary($gap))
            ->values();

        return [
            'type' => 'MeanlyCommerceOpportunityList',
            'version' => 1,
            'generated_at' => now()->toIso8601String(),
            'filters' => $filters,
            'count' => $items->count(),
            'opportunities' => $items,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function actionEffectiveness(): array
    {
        $actions = [
            OpportunityCase::ACTION_ADD_SUPPLY,
            OpportunityCase::ACTION_IMPROVE_PRICING,
            OpportunityCase::ACTION_FIX_CHECKOUT,
            OpportunityCase::ACTION_INVESTIGATE,
        ];

        return [
            'type' => 'MeanlyCommerceActionEffectiveness',
            'version' => 1,
            'generated_at' => now()->toIso8601String(),
            'actions' => collect($actions)
                ->mapWithKeys(fn (string $action): array => [$action => $this->lifecycle->actionEffectiveness($action)])
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function brandEntity(string $slug): ?array
    {
        $entity = $this->entities->brand($slug);

        return $entity ? $this->entityPayload($entity, ['brand_entity_key' => $entity['slug']]) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function regionEntity(string $slug): ?array
    {
        $entity = $this->entities->region($slug);

        return $entity ? $this->entityPayload($entity, ['region_entity_key' => $entity['slug']]) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function intersectionEntity(string $slug): ?array
    {
        [$brandSlug, $regionSlug] = array_pad(explode('-', $slug, 2), 2, null);

        if (! $brandSlug || ! $regionSlug) {
            return null;
        }

        $entity = $this->entities->brandRegion($brandSlug, $regionSlug);

        return $entity ? $this->entityPayload($entity, [
            'brand_entity_key' => $entity['brand_slug'],
            'region_entity_key' => $entity['region_slug'],
        ]) : null;
    }

    /**
     * @param  array<string, mixed>  $entity
     * @param  array<string, string>  $where
     * @return array<string, mixed>
     */
    private function entityPayload(array $entity, array $where): array
    {
        $gaps = DemandGap::query()
            ->where($where)
            ->orderByDesc('opportunity_score')
            ->get();

        $primaryGap = $gaps->first();

        return [
            'type' => 'MeanlyCommerceEntityOpportunity',
            'version' => 1,
            'generated_at' => now()->toIso8601String(),
            'entity' => $entity,
            'demand' => $this->aggregateDemand($gaps),
            'diagnosis' => $primaryGap ? $this->diagnosisSummary($primaryGap) : null,
            'cases' => $this->caseSummary($where),
            'top_opportunities' => $gaps
                ->take(10)
                ->map(fn (DemandGap $gap): array => $this->opportunitySummary($gap))
                ->values(),
        ];
    }

    /**
     * @param  Collection<int, DemandGap>  $gaps
     * @return array<string, mixed>
     */
    private function aggregateDemand(Collection $gaps): array
    {
        return [
            'searches' => (int) $gaps->sum('search_volume'),
            'estimated_lost_gmv' => round((float) $gaps->sum('estimated_lost_gmv'), 2),
            'attributed_gmv' => round((float) $gaps->sum('attributed_gmv'), 2),
            'max_opportunity_score' => round((float) $gaps->max('opportunity_score'), 1),
            'avg_opportunity_score' => round((float) $gaps->avg('opportunity_score'), 1),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function diagnosisSummary(DemandGap $gap): array
    {
        $graph = collect($gap->opportunity_diagnosis_graph ?? []);

        return [
            'primary' => [
                'code' => $gap->opportunity_diagnosis,
                'confidence' => (float) $gap->diagnosis_confidence,
            ],
            'secondary' => $graph
                ->skip(1)
                ->map(fn (array $item): array => [
                    'code' => $item['cause'] ?? null,
                    'score' => (float) ($item['score'] ?? 0),
                ])
                ->values(),
        ];
    }

    /**
     * @param  array<string, string>  $where
     * @return array<string, mixed>
     */
    private function caseSummary(array $where): array
    {
        $query = OpportunityCase::query()
            ->whereIn('canonical_query', DemandGap::query()->where($where)->select('canonical_query'));

        $active = (clone $query)->whereIn('status', [OpportunityCase::STATUS_OPEN, OpportunityCase::STATUS_IN_PROGRESS])->count();
        $overdue = (clone $query)
            ->whereIn('status', [OpportunityCase::STATUS_OPEN, OpportunityCase::STATUS_IN_PROGRESS])
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<', now())
            ->count();
        $resolved = (clone $query)->where('status', OpportunityCase::STATUS_RESOLVED)->count();

        return [
            'active' => $active,
            'overdue' => $overdue,
            'resolved' => $resolved,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function opportunitySummary(DemandGap $gap): array
    {
        return [
            'canonical_query' => $gap->canonical_query,
            'entity_keys' => [
                'brand' => $gap->brand_entity_key,
                'region' => $gap->region_entity_key,
                'category' => $gap->category_entity_key,
            ],
            'demand' => [
                'searches' => (int) $gap->search_volume,
                'estimated_lost_gmv' => (float) $gap->estimated_lost_gmv,
                'attributed_gmv' => (float) $gap->attributed_gmv,
                'opportunity_score' => (float) $gap->opportunity_score,
            ],
            'diagnosis' => $this->diagnosisSummary($gap),
            'cases' => $this->caseSummary(['canonical_query' => $gap->canonical_query]),
        ];
    }

    private function activeCaseSubquery($subquery): void
    {
        $subquery
            ->selectRaw('1')
            ->from('opportunity_cases')
            ->whereColumn('opportunity_cases.canonical_query', 'demand_gaps.canonical_query')
            ->whereIn('opportunity_cases.status', [OpportunityCase::STATUS_OPEN, OpportunityCase::STATUS_IN_PROGRESS]);
    }
}
