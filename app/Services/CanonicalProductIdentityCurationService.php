<?php

namespace App\Services;

use App\Models\CanonicalProductIdentity;
use App\Models\CanonicalProductIdentityOverride;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class CanonicalProductIdentityCurationService
{
    /**
     * @var array<int, string>
     */
    public const OVERRIDE_FIELDS = [
        'brand',
        'product_family',
        'face_value',
        'face_value_currency',
        'region',
        'platform',
        'canonical_category',
        'confidence',
        'signals',
    ];

    public function __construct(
        private readonly ProductIndexingPolicyService $indexingPolicy,
    ) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function reviewQueue(int $limit = 20, ?string $sourceType = null, ?int $sourceId = null): Collection
    {
        if (! $this->identityTablesExist()) {
            return collect();
        }

        $limit = max(1, $limit);
        $scanLimit = max($limit * 50, 250);
        $hasOverrideTable = $this->overrideTableExists();

        $query = CanonicalProductIdentity::query()
            ->select([
                'id',
                'fingerprint',
                'identity_slug',
                'canonical_category',
                'brand',
                'product_family',
                'face_value',
                'face_value_currency',
                'region',
                'platform',
                'confidence',
                'signals',
                'provider_candidates_count',
                'seller_offers_count',
                'best_offer_product_id',
                'last_seen_at',
                'updated_at',
            ])
            ->with(['sources' => fn ($query) => $query->orderBy('source_type')->orderBy('source_id')])
            ->withCount('sources')
            ->orderByRaw("case confidence when 'low' then 0 when 'medium' then 1 when 'high' then 2 else 3 end")
            ->orderByDesc('updated_at')
            ->limit($scanLimit);

        if ($hasOverrideTable) {
            $query->with('override');
        }

        if ($sourceType !== null || $sourceId !== null) {
            $query->whereHas('sources', function ($query) use ($sourceType, $sourceId): void {
                if ($sourceType !== null) {
                    $query->where('source_type', $sourceType);
                }

                if ($sourceId !== null) {
                    $query->where('source_id', $sourceId);
                }
            });
        }

        return $query
            ->get()
            ->map(fn (CanonicalProductIdentity $identity) => $this->reviewQueueRow($identity))
            ->filter()
            ->take($limit)
            ->values();
    }

    /**
     * @param  array<string, mixed>  $identity
     * @return array<string, mixed>
     */
    public function applyApprovedOverrides(array $identity, ?CanonicalProductIdentity $identityModel = null): array
    {
        if (! $this->overrideTableExists()) {
            return $identity;
        }

        $fingerprint = trim((string) ($identity['fingerprint'] ?? $identityModel?->fingerprint ?? ''));
        if ($fingerprint === '') {
            return $identity;
        }

        $override = $this->approvedOverrideForFingerprint($fingerprint, $identityModel);
        if ($override === null) {
            return $identity;
        }

        $overlaid = $identity;
        foreach (self::OVERRIDE_FIELDS as $field) {
            if ($field === 'signals') {
                if ($override->signals !== null) {
                    $overlaid['signals'] = collect($override->signals)
                        ->flatten()
                        ->map(fn ($signal) => trim((string) $signal))
                        ->filter()
                        ->values()
                        ->all();
                }

                continue;
            }

            $value = $override->{$field};
            if ($value === null || $value === '') {
                continue;
            }

            $overlaid[$field] = $field === 'face_value' ? (float) $value : $value;
        }

        $overlaid['curation_override'] = [
            'id' => $override->id,
            'review_status' => $override->review_status,
            'reviewed_at' => optional($override->reviewed_at)->toIso8601String(),
        ];
        $overlaid['signals'] = collect((array) ($overlaid['signals'] ?? []))
            ->push('identity_override:approved')
            ->unique()
            ->values()
            ->all();

        return $overlaid;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function saveOverride(string $fingerprint, array $attributes, ?int $actorId = null): CanonicalProductIdentityOverride
    {
        $fingerprint = trim($fingerprint);
        if ($fingerprint === '') {
            throw new \InvalidArgumentException('A canonical identity fingerprint is required.');
        }

        $identity = $this->identityTablesExist()
            ? CanonicalProductIdentity::query()->where('fingerprint', $fingerprint)->first()
            : null;
        $status = $this->normalizeReviewStatus($attributes['review_status'] ?? CanonicalProductIdentityOverride::STATUS_PENDING);
        $reviewed = in_array($status, [
            CanonicalProductIdentityOverride::STATUS_APPROVED,
            CanonicalProductIdentityOverride::STATUS_REJECTED,
            CanonicalProductIdentityOverride::STATUS_IGNORED,
        ], true);

        $fillable = Arr::only($attributes, [
            ...self::OVERRIDE_FIELDS,
            'review_status',
            'review_notes',
            'metadata',
        ]);
        $fillable['review_status'] = $status;
        $fillable['canonical_product_identity_id'] = $identity?->id;

        if (array_key_exists('confidence', $fillable)) {
            $fillable['confidence'] = $this->normalizeConfidence($fillable['confidence']);
        }

        if (array_key_exists('face_value', $fillable) && $fillable['face_value'] !== null && $fillable['face_value'] !== '') {
            $fillable['face_value'] = (float) $fillable['face_value'];
        }

        if (array_key_exists('signals', $fillable) && $fillable['signals'] !== null) {
            $fillable['signals'] = collect((array) $fillable['signals'])
                ->flatten()
                ->map(fn ($signal) => trim((string) $signal))
                ->filter()
                ->values()
                ->all();
        }

        if ($reviewed) {
            $fillable['reviewed_at'] = now();
            $fillable['reviewed_by'] = $actorId;
        }

        $override = CanonicalProductIdentityOverride::query()->firstOrNew(['fingerprint' => $fingerprint]);
        if (! $override->exists) {
            $override->created_by = $actorId;
        }

        $override->fill($fillable);
        $override->save();

        return $override->refresh();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function reviewQueueRow(CanonicalProductIdentity $identity): ?array
    {
        /** @var CanonicalProductIdentityOverride|null $override */
        $override = $identity->relationLoaded('override')
            ? $identity->getRelationValue('override')
            : null;
        if (in_array($override?->review_status, [
            CanonicalProductIdentityOverride::STATUS_APPROVED,
            CanonicalProductIdentityOverride::STATUS_IGNORED,
        ], true)) {
            return null;
        }

        $canonicalIdentity = $this->applyApprovedOverrides($identity->toArray(), $identity);
        $policy = $this->indexingPolicy->forCanonicalProduct(
            $canonicalIdentity,
            $identity->best_offer_product_id !== null ? ['indexing' => ['indexable' => true]] : null,
            ['provider_candidates_count' => $identity->provider_candidates_count],
        );
        $signals = $this->signals($canonicalIdentity);
        $reasons = $this->reviewReasons($canonicalIdentity, $policy, $signals);

        if ($reasons === [] && $override?->review_status !== CanonicalProductIdentityOverride::STATUS_PENDING) {
            return null;
        }

        return [
            'id' => $identity->id,
            'fingerprint' => $identity->fingerprint,
            'slug' => $identity->identity_slug,
            'category' => $canonicalIdentity['canonical_category'] ?? null,
            'confidence' => $canonicalIdentity['confidence'] ?? null,
            'brand' => $canonicalIdentity['brand'] ?? null,
            'family' => $canonicalIdentity['product_family'] ?? null,
            'face_value' => $canonicalIdentity['face_value'] ?? null,
            'currency' => $canonicalIdentity['face_value_currency'] ?? null,
            'region' => $canonicalIdentity['region'] ?? null,
            'platform' => $canonicalIdentity['platform'] ?? null,
            'robots' => $policy['robots'] ?? null,
            'surface' => $policy['surface'] ?? null,
            'policy_reasons' => $policy['reasons'] ?? [],
            'review_reasons' => $reasons,
            'override_status' => $override?->review_status,
            'provider_count' => $identity->provider_candidates_count,
            'seller_count' => $identity->seller_offers_count,
            'best_offer' => $identity->best_offer_product_id,
            'sources' => $identity->sources
                ->take(8)
                ->map(fn ($source) => $source->source_type.':'.$source->source_id)
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $identity
     * @param  array<string, mixed>  $policy
     * @param  array<int, string>  $signals
     * @return array<int, string>
     */
    private function reviewReasons(array $identity, array $policy, array $signals): array
    {
        $reasons = [];

        if (($policy['surface'] ?? null) === 'internal_review') {
            $reasons[] = 'policy_internal_review';
        }

        if (($identity['confidence'] ?? null) === 'low') {
            $reasons[] = 'low_confidence_identity';
        }

        foreach ($signals as $signal) {
            if (
                $signal === 'brand_not_in_title'
                || str_starts_with($signal, 'multiple_brand_tokens')
                || str_starts_with($signal, 'brand_family_mismatch')
            ) {
                $reasons[] = 'suspicious_signal:'.$signal;
            }
        }

        return array_values(array_unique($reasons));
    }

    private function approvedOverrideForFingerprint(string $fingerprint, ?CanonicalProductIdentity $identityModel): ?CanonicalProductIdentityOverride
    {
        if ($identityModel?->relationLoaded('override')) {
            $loadedOverride = $identityModel->getRelationValue('override');

            return $loadedOverride instanceof CanonicalProductIdentityOverride
                && $loadedOverride->review_status === CanonicalProductIdentityOverride::STATUS_APPROVED
                    ? $loadedOverride
                    : null;
        }

        return CanonicalProductIdentityOverride::query()
            ->where('fingerprint', $fingerprint)
            ->where('review_status', CanonicalProductIdentityOverride::STATUS_APPROVED)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $identity
     * @return array<int, string>
     */
    private function signals(array $identity): array
    {
        return collect((array) ($identity['signals'] ?? []))
            ->flatten()
            ->map(fn ($signal) => trim((string) $signal))
            ->filter()
            ->values()
            ->all();
    }

    private function normalizeReviewStatus(mixed $status): string
    {
        $status = trim((string) $status);

        return in_array($status, CanonicalProductIdentityOverride::REVIEW_STATUSES, true)
            ? $status
            : CanonicalProductIdentityOverride::STATUS_PENDING;
    }

    private function normalizeConfidence(mixed $confidence): ?string
    {
        if ($confidence === null || $confidence === '') {
            return null;
        }

        $confidence = trim((string) $confidence);
        if (! in_array($confidence, ['low', 'medium', 'high'], true)) {
            throw new \InvalidArgumentException('Confidence must be one of: low, medium, high.');
        }

        return $confidence;
    }

    private function identityTablesExist(): bool
    {
        return Schema::hasTable('canonical_product_identities')
            && Schema::hasTable('canonical_product_identity_sources');
    }

    private function overrideTableExists(): bool
    {
        return Schema::hasTable('canonical_product_identity_overrides');
    }
}
