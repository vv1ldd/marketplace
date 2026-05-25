<?php

namespace App\Services;

use App\Models\CanonicalProductIdentity;
use App\Models\CanonicalProductIdentitySource;
use App\Models\CommerceEntity;
use App\Models\CommerceEntityLink;
use App\Models\DemandGap;
use App\Models\OpportunityCase;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CommerceEntityGraphService
{
    public function rebuild(): int
    {
        $count = 0;

        CanonicalProductIdentity::query()
            ->with('sources')
            ->orderBy('id')
            ->chunkById(200, function ($identities) use (&$count): void {
                foreach ($identities as $identity) {
                    $entity = $this->syncIdentity($identity);
                    $this->syncMetrics($entity);
                    $count++;
                }
            });

        return $count;
    }

    public function resolveBySlug(string $slug): ?CommerceEntity
    {
        return CommerceEntity::with(['links', 'metrics'])->where('slug', $slug)->first();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function offers(CommerceEntity $entity): Collection
    {
        return $entity->links()
            ->whereIn('link_type', [CommerceEntityLink::TYPE_PRODUCT, CommerceEntityLink::TYPE_PROVIDER_PRODUCT])
            ->orderByDesc('confidence')
            ->get()
            ->map(fn (CommerceEntityLink $link): array => [
                'type' => $link->link_type,
                'id' => $link->link_id,
                'confidence' => (float) $link->confidence,
                'signals' => $link->signals ?? [],
            ]);
    }

    public function syncIdentity(CanonicalProductIdentity $identity): CommerceEntity
    {
        $attributes = $this->attributesForIdentity($identity);

        $entity = CommerceEntity::updateOrCreate(
            ['slug' => $this->slugForAttributes($attributes, (string) $identity->identity_slug)],
            [
                'entity_type' => (string) ($attributes['entity_type'] ?? 'digital_good'),
                'attributes' => $attributes,
                'canonical_query' => $this->canonicalQuery($attributes),
            ],
        );

        CommerceEntityLink::updateOrCreate(
            [
                'commerce_entity_id' => $entity->id,
                'link_type' => CommerceEntityLink::TYPE_CANONICAL_IDENTITY,
                'link_id' => $identity->id,
            ],
            [
                'confidence' => $this->confidenceScore($identity->confidence),
                'signals' => ['identity_slug' => $identity->identity_slug],
            ],
        );

        foreach ($identity->sources as $source) {
            CommerceEntityLink::updateOrCreate(
                [
                    'commerce_entity_id' => $entity->id,
                    'link_type' => $this->linkTypeForSource((string) $source->source_type),
                    'link_id' => (int) $source->source_id,
                ],
                [
                    'confidence' => $this->confidenceScore($source->confidence ?: $identity->confidence),
                    'signals' => $source->signals ?? ['source_sku' => $source->source_sku],
                ],
            );
        }

        return $entity->refresh();
    }

    public function syncMetrics(CommerceEntity $entity): void
    {
        $attributes = (array) ($entity->attributes ?? []);
        $query = DemandGap::query();

        if (filled($attributes['brand'] ?? null)) {
            $query->where('brand_entity_key', Str::slug((string) $attributes['brand']));
        }

        if (filled($attributes['region'] ?? null)) {
            $query->where('region_entity_key', Str::slug((string) $attributes['region']));
        }

        if (filled($attributes['category'] ?? null)) {
            $query->where('category_entity_key', Str::slug((string) $attributes['category']));
        }

        $gaps = $query->get();
        $caseQuery = OpportunityCase::query()
            ->whereIn('canonical_query', $gaps->pluck('canonical_query')->filter()->values());

        $entity->metrics()->updateOrCreate(
            [],
            [
                'searches' => (int) $gaps->sum('search_volume'),
                'views' => (int) $gaps->sum('views_count'),
                'carts' => (int) $gaps->sum('carts_count'),
                'orders' => round((float) $gaps->sum('attributed_orders_count'), 2),
                'attributed_gmv' => round((float) $gaps->sum('attributed_gmv'), 2),
                'estimated_lost_gmv' => round((float) $gaps->sum('estimated_lost_gmv'), 2),
                'opportunity_score' => round((float) $gaps->max('opportunity_score'), 2),
                'active_cases' => (clone $caseQuery)->whereIn('status', [OpportunityCase::STATUS_OPEN, OpportunityCase::STATUS_IN_PROGRESS])->count(),
                'resolved_cases' => (clone $caseQuery)->where('status', OpportunityCase::STATUS_RESOLVED)->count(),
                'calculated_at' => now(),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function attributesForIdentity(CanonicalProductIdentity $identity): array
    {
        return array_filter([
            'brand' => $identity->brand ? Str::slug((string) $identity->brand) : null,
            'brand_label' => $identity->brand,
            'region' => $identity->region ? Str::slug((string) $identity->region) : null,
            'region_label' => $identity->region,
            'category' => $identity->canonical_category,
            'face_value' => $identity->face_value !== null ? (float) $identity->face_value : null,
            'currency' => $identity->face_value_currency ? Str::upper((string) $identity->face_value_currency) : null,
            'platform' => $identity->platform,
            'entity_type' => $this->entityTypeForCategory((string) $identity->canonical_category),
        ], fn ($value): bool => $value !== null && $value !== '');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function slugForAttributes(array $attributes, string $fallback): string
    {
        $parts = [
            $attributes['brand'] ?? null,
            $attributes['region'] ?? null,
            $attributes['face_value'] ?? null,
            isset($attributes['currency']) ? Str::lower((string) $attributes['currency']) : null,
        ];

        $slug = collect($parts)->filter(fn ($part): bool => filled($part))->implode('-');

        return $slug !== '' ? Str::slug($slug) : $fallback;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function canonicalQuery(array $attributes): string
    {
        return collect([
            $attributes['brand'] ?? null,
            $attributes['region'] ?? null,
            $attributes['face_value'] ?? null,
            $attributes['currency'] ?? null,
        ])->filter(fn ($part): bool => filled($part))->implode(' ');
    }

    private function entityTypeForCategory(string $category): string
    {
        return match ($category) {
            'subscriptions' => 'subscription',
            'game_wallet_topups', 'telecom_topups' => 'topup',
            default => 'gift_card',
        };
    }

    private function linkTypeForSource(string $sourceType): string
    {
        return match ($sourceType) {
            CanonicalProductIdentitySource::SOURCE_PROVIDER_PRODUCT => CommerceEntityLink::TYPE_PROVIDER_PRODUCT,
            CanonicalProductIdentitySource::SOURCE_PRODUCT => CommerceEntityLink::TYPE_PRODUCT,
            default => $sourceType,
        };
    }

    private function confidenceScore(?string $confidence): float
    {
        return match ($confidence) {
            'high' => 0.98,
            'medium' => 0.85,
            'low' => 0.65,
            default => 0.75,
        };
    }
}
