<?php

namespace App\Services;

use App\Models\CommerceEntity;
use App\Models\Currency;
use App\Models\IntentLiquidityCorridor;
use App\Models\IntentLiquidityNode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class IntentLiquidityGraphService
{
    public function rebuild(): int
    {
        $count = 0;

        CommerceEntity::query()
            ->with(['links', 'metrics'])
            ->orderBy('id')
            ->chunkById(200, function ($entities) use (&$count): void {
                foreach ($entities as $entity) {
                    $this->syncCommerceEntity($entity);
                    $count++;
                }
            });

        Currency::query()
            ->orderBy('code')
            ->chunkById(200, function ($currencies) use (&$count): void {
                foreach ($currencies as $currency) {
                    $this->syncCurrency($currency);
                    $count++;
                }
            });

        return $count;
    }

    public function syncCommerceEntity(CommerceEntity $entity): void
    {
        $entity->loadMissing(['links', 'metrics']);
        $attributes = (array) ($entity->attributes ?? []);
        $label = $this->commerceLabel($entity);
        $metrics = $entity->metrics;
        $offersCount = $entity->links->whereIn('link_type', ['product', 'provider_product'])->count();
        $linkConfidence = (float) ($entity->links->avg('confidence') ?? 0.75);
        $opportunityScore = (float) ($metrics?->opportunity_score ?? 0);
        $demandScore = min(100.0, $opportunityScore);
        $supplyReadiness = $offersCount > 0 ? min(100.0, 45 + ($offersCount * 15) + ($linkConfidence * 25)) : 20.0;

        $buy = $this->upsertNode([
            'intent_key' => 'buy:commerce:'.$entity->slug,
            'intent_type' => IntentLiquidityNode::INTENT_BUY,
            'actor_role' => 'buyer',
            'entity_type' => 'commerce_entity',
            'entity_id' => $entity->id,
            'entity_slug' => $entity->slug,
            'entity_label' => $label,
            'attributes' => $attributes,
            'demand_score' => $demandScore,
            'readiness_score' => $supplyReadiness,
            'confidence_score' => round($linkConfidence * 100, 4),
            'status' => $supplyReadiness >= 60 ? 'routable' : 'thin',
        ]);

        $this->upsertCorridor($buy, [
            'corridor_type' => 'product',
            'corridor_key' => 'commerce-offers:'.$entity->slug,
            'source' => 'commerce_entity_graph',
            'route_type' => $offersCount > 0 ? 'offer_resolution' : 'demand_only',
            'route_score' => $supplyReadiness,
            'capacity' => $offersCount,
            'friction_score' => max(0, 100 - $supplyReadiness),
            'failure_modes' => $offersCount > 0 ? [] : ['NO_OFFER_LINKS'],
            'diagnostics' => [
                'offers_count' => $offersCount,
                'avg_link_confidence' => round($linkConfidence, 4),
                'orders' => (float) ($metrics?->orders ?? 0),
                'opportunity_score' => $opportunityScore,
            ],
            'execution_ready' => $offersCount > 0,
        ]);

        $this->syncCurrencyCorridorForNode($buy, (string) ($attributes['currency'] ?? ''));

        $indexReadiness = $this->indexReadiness($entity);
        $index = $this->upsertNode([
            'intent_key' => 'index:commerce:'.$entity->slug,
            'intent_type' => IntentLiquidityNode::INTENT_INDEX,
            'actor_role' => 'indexer',
            'entity_type' => 'commerce_entity',
            'entity_id' => $entity->id,
            'entity_slug' => $entity->slug,
            'entity_label' => $label,
            'attributes' => $attributes,
            'demand_score' => $demandScore,
            'readiness_score' => $indexReadiness['score'],
            'confidence_score' => $indexReadiness['confidence'],
            'status' => $indexReadiness['score'] >= 70 ? 'index_ready' : 'needs_structure',
        ]);

        $this->upsertCorridor($index, [
            'corridor_type' => 'index',
            'corridor_key' => 'llm-search:'.$entity->slug,
            'source' => 'llm_catalog',
            'route_type' => 'ai_readable_entity',
            'route_score' => $indexReadiness['score'],
            'capacity' => (float) ($metrics?->searches ?? 0),
            'friction_score' => max(0, 100 - $indexReadiness['score']),
            'failure_modes' => $indexReadiness['failure_modes'],
            'diagnostics' => $indexReadiness['diagnostics'],
            'execution_ready' => $indexReadiness['score'] >= 70,
        ]);

        $sell = $this->upsertNode([
            'intent_key' => 'sell:commerce:'.$entity->slug,
            'intent_type' => IntentLiquidityNode::INTENT_SELL,
            'actor_role' => 'seller',
            'entity_type' => 'commerce_entity',
            'entity_id' => $entity->id,
            'entity_slug' => $entity->slug,
            'entity_label' => $label,
            'attributes' => $attributes,
            'demand_score' => $demandScore,
            'readiness_score' => max($supplyReadiness, $indexReadiness['score']),
            'confidence_score' => round(($linkConfidence * 70) + min(30, $opportunityScore / 3), 4),
            'status' => $opportunityScore >= 50 ? 'opportunity' : 'available',
        ]);

        $this->upsertCorridor($sell, [
            'corridor_type' => 'opportunity',
            'corridor_key' => 'demand:'.$entity->slug,
            'source' => 'opportunity_graph',
            'route_type' => $opportunityScore >= 50 ? 'demand_gap' : 'catalog_presence',
            'route_score' => $opportunityScore,
            'capacity' => (float) ($metrics?->estimated_lost_gmv ?? 0),
            'friction_score' => max(0, 100 - $opportunityScore),
            'failure_modes' => $opportunityScore >= 50 ? ['UNMET_DEMAND'] : [],
            'diagnostics' => [
                'searches' => (int) ($metrics?->searches ?? 0),
                'estimated_lost_gmv' => (float) ($metrics?->estimated_lost_gmv ?? 0),
                'active_cases' => (int) ($metrics?->active_cases ?? 0),
            ],
            'execution_ready' => $opportunityScore > 0,
        ]);
    }

    public function syncCurrency(Currency $currency): void
    {
        $node = $this->upsertNode([
            'intent_key' => 'exchange:currency:'.$currency->code,
            'intent_type' => IntentLiquidityNode::INTENT_EXCHANGE,
            'actor_role' => 'liquidity_provider',
            'entity_type' => 'currency',
            'entity_id' => $currency->id,
            'entity_slug' => Str::lower($currency->code),
            'entity_label' => $currency->code,
            'attributes' => [
                'code' => $currency->code,
                'market_regime' => $currency->market_regime,
                'base_asset' => $currency->base_asset,
                'quote_asset' => $currency->quote_asset,
            ],
            'demand_score' => 0,
            'readiness_score' => $this->currencyReadiness($currency),
            'confidence_score' => round((float) $currency->confidence_score * 100, 4),
            'status' => $currency->execution_ready ? 'execution_ready' : 'not_ready',
        ]);

        $this->syncCurrencyCorridors($node, $currency);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function graph(array $filters = []): array
    {
        $query = IntentLiquidityNode::query()->with('corridors');

        if (filled($filters['intent'] ?? null)) {
            $query->where('intent_type', (string) $filters['intent']);
        }

        if (filled($filters['actor'] ?? null)) {
            $query->where('actor_role', (string) $filters['actor']);
        }

        if (filled($filters['entity_type'] ?? null)) {
            $query->where('entity_type', (string) $filters['entity_type']);
        }

        if (filled($filters['q'] ?? null)) {
            $term = mb_strtolower((string) $filters['q']);
            $query->where(function (Builder $builder) use ($term): void {
                $builder->whereRaw('LOWER(entity_slug) LIKE ?', ['%'.$term.'%'])
                    ->orWhereRaw('LOWER(entity_label) LIKE ?', ['%'.$term.'%'])
                    ->orWhereRaw('LOWER(intent_key) LIKE ?', ['%'.$term.'%']);
            });
        }

        $limit = max(1, min((int) ($filters['limit'] ?? 50), 100));
        $nodes = $query
            ->orderByDesc('demand_score')
            ->orderByDesc('readiness_score')
            ->limit($limit)
            ->get()
            ->map(fn (IntentLiquidityNode $node): array => $this->nodePayload($node))
            ->values();

        return [
            'type' => 'MeanlyIntentLiquidityGraph',
            'version' => 1,
            'generated_at' => now()->toIso8601String(),
            'filters' => $filters,
            'count' => $nodes->count(),
            'nodes' => $nodes,
        ];
    }

    public function node(string $intentKey): ?array
    {
        $node = IntentLiquidityNode::with('corridors')->where('intent_key', $intentKey)->first();

        return $node ? $this->nodePayload($node) : null;
    }

    private function syncCurrencyCorridorForNode(IntentLiquidityNode $node, string $currencyCode): void
    {
        if ($currencyCode === '') {
            $this->upsertCorridor($node, [
                'corridor_type' => 'currency',
                'corridor_key' => 'currency:unknown',
                'source' => 'commerce_entity_attributes',
                'route_type' => 'missing_currency',
                'route_score' => 0,
                'friction_score' => 100,
                'failure_modes' => ['NO_FACE_VALUE_CURRENCY'],
                'diagnostics' => [],
                'execution_ready' => false,
            ]);

            return;
        }

        $currency = Currency::where('code', Str::upper($currencyCode))->first();

        if (! $currency) {
            $this->upsertCorridor($node, [
                'corridor_type' => 'currency',
                'corridor_key' => 'currency:'.Str::upper($currencyCode),
                'source' => 'currency_graph',
                'route_type' => 'unknown_currency',
                'route_score' => 0,
                'friction_score' => 100,
                'failure_modes' => ['CURRENCY_NODE_MISSING'],
                'diagnostics' => ['currency' => Str::upper($currencyCode)],
                'execution_ready' => false,
            ]);

            return;
        }

        $this->syncCurrencyCorridors($node, $currency);
    }

    private function syncCurrencyCorridors(IntentLiquidityNode $node, Currency $currency): void
    {
        $corridors = collect($currency->corridors ?? []);

        if ($corridors->isEmpty()) {
            $this->upsertCorridor($node, [
                'corridor_type' => 'currency',
                'corridor_key' => 'USDT/'.$currency->code,
                'source' => $currency->p2p_source ?: 'currency_graph',
                'route_type' => $currency->market_regime ?: 'UNKNOWN',
                'route_score' => $this->currencyReadiness($currency),
                'capacity' => (float) ($currency->max_executable_size ?? 0),
                'friction_score' => round((float) ($currency->liquidity_stress_index ?? 1) * 100, 4),
                'failure_modes' => $currency->execution_ready ? [] : ['NO_LIVE_CORRIDOR'],
                'diagnostics' => [
                    'market_regime' => $currency->market_regime,
                    'confidence' => (float) $currency->confidence_score,
                    'estimated_slippage' => (float) $currency->estimated_slippage,
                    'observability' => (float) $currency->observability_score,
                ],
                'execution_ready' => (bool) $currency->execution_ready,
            ]);

            return;
        }

        foreach ($corridors as $key => $corridor) {
            $this->upsertCorridor($node, [
                'corridor_type' => 'currency',
                'corridor_key' => (string) $key,
                'source' => (string) ($corridor['source'] ?? $currency->p2p_source ?? 'currency_graph'),
                'route_type' => (string) ($corridor['route_type'] ?? $corridor['regime'] ?? $currency->market_regime ?? 'UNKNOWN'),
                'route_score' => (float) ($corridor['route_score'] ?? $this->currencyReadiness($currency)),
                'capacity' => (float) ($corridor['capacity'] ?? $currency->max_executable_size ?? 0),
                'latency_ms' => null,
                'friction_score' => round((float) ($currency->liquidity_stress_index ?? 0) * 100, 4),
                'failure_modes' => $corridor['failure_modes'] ?? [],
                'diagnostics' => $corridor + [
                    'currency_code' => $currency->code,
                    'observability' => (float) $currency->observability_score,
                    'confidence' => (float) $currency->confidence_score,
                ],
                'execution_ready' => (bool) $currency->execution_ready && ((float) ($corridor['route_score'] ?? 0) >= 40),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function upsertNode(array $attributes): IntentLiquidityNode
    {
        return IntentLiquidityNode::updateOrCreate(
            ['intent_key' => $attributes['intent_key']],
            $attributes + ['calculated_at' => now()],
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function upsertCorridor(IntentLiquidityNode $node, array $attributes): IntentLiquidityCorridor
    {
        return $node->corridors()->updateOrCreate(
            [
                'corridor_type' => $attributes['corridor_type'],
                'corridor_key' => $attributes['corridor_key'],
                'source' => $attributes['source'] ?? null,
            ],
            $attributes + ['observed_at' => now()],
        );
    }

    private function commerceLabel(CommerceEntity $entity): string
    {
        $attributes = (array) ($entity->attributes ?? []);

        return collect([
            $attributes['brand_label'] ?? $attributes['brand'] ?? null,
            $attributes['region_label'] ?? $attributes['region'] ?? null,
            $attributes['face_value'] ?? null,
            $attributes['currency'] ?? null,
        ])->filter(fn ($part): bool => filled($part))->implode(' ') ?: $entity->slug;
    }

    /**
     * @return array{score: float, confidence: float, failure_modes: array<int, string>, diagnostics: array<string, mixed>}
     */
    private function indexReadiness(CommerceEntity $entity): array
    {
        $attributes = (array) ($entity->attributes ?? []);
        $metrics = $entity->metrics;
        $checks = [
            'has_brand' => filled($attributes['brand'] ?? null),
            'has_region' => filled($attributes['region'] ?? null),
            'has_category' => filled($attributes['category'] ?? null),
            'has_canonical_query' => filled($entity->canonical_query),
            'has_product_links' => $entity->links->isNotEmpty(),
            'has_demand_metrics' => (int) ($metrics?->searches ?? 0) > 0,
        ];

        $score = round((collect($checks)->filter()->count() / count($checks)) * 100, 4);
        $failureModes = collect($checks)
            ->filter(fn (bool $passed): bool => ! $passed)
            ->keys()
            ->map(fn (string $key): string => Str::upper($key))
            ->values()
            ->all();

        return [
            'score' => $score,
            'confidence' => min(100.0, $score + min(20.0, ((float) ($metrics?->searches ?? 0) / 10))),
            'failure_modes' => $failureModes,
            'diagnostics' => $checks + [
                'canonical_query' => $entity->canonical_query,
                'searches' => (int) ($metrics?->searches ?? 0),
            ],
        ];
    }

    private function currencyReadiness(Currency $currency): float
    {
        $confidence = (float) ($currency->confidence_score ?? 0);
        $observability = (float) ($currency->observability_score ?? 0);
        $stress = (float) ($currency->liquidity_stress_index ?? 1);
        $capacityFactor = min(1.0, ((float) ($currency->max_executable_size ?? 0)) / 50000);

        return round(max(0, min(100, (($confidence * 35) + ($observability * 35) + ((1 - $stress) * 20) + ($capacityFactor * 10)))), 4);
    }

    private function nodePayload(IntentLiquidityNode $node): array
    {
        return [
            'intent_key' => $node->intent_key,
            'intent_type' => $node->intent_type,
            'actor_role' => $node->actor_role,
            'entity' => [
                'type' => $node->entity_type,
                'id' => $node->entity_id,
                'slug' => $node->entity_slug,
                'label' => $node->entity_label,
                'attributes' => $node->attributes ?? [],
            ],
            'scores' => [
                'demand' => (float) $node->demand_score,
                'readiness' => (float) $node->readiness_score,
                'confidence' => (float) $node->confidence_score,
            ],
            'status' => $node->status,
            'calculated_at' => $node->calculated_at?->toIso8601String(),
            'corridors' => $node->corridors
                ->map(fn (IntentLiquidityCorridor $corridor): array => [
                    'type' => $corridor->corridor_type,
                    'key' => $corridor->corridor_key,
                    'source' => $corridor->source,
                    'route_type' => $corridor->route_type,
                    'route_score' => (float) $corridor->route_score,
                    'capacity' => $corridor->capacity !== null ? (float) $corridor->capacity : null,
                    'latency_ms' => $corridor->latency_ms,
                    'friction_score' => (float) $corridor->friction_score,
                    'failure_modes' => $corridor->failure_modes ?? [],
                    'diagnostics' => $corridor->diagnostics ?? [],
                    'execution_ready' => (bool) $corridor->execution_ready,
                    'observed_at' => $corridor->observed_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
        ];
    }
}
