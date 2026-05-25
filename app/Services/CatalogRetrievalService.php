<?php

namespace App\Services;

use App\Models\CanonicalProductIdentity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CatalogRetrievalService
{
    private const DEFAULT_LIMIT = 20;
    private const MAX_LIMIT = 50;
    private const SCAN_LIMIT = 300;
    private const HYDRATE_LIMIT = 120;

    public function __construct(
        private readonly CanonicalProductPageService $canonicalPages,
        private readonly ProductIndexingPolicyService $indexingPolicy,
        private readonly ProductIntentResolutionService $intentResolver,
        private readonly CanonicalProductIdentityCurationService $curation,
    ) {}

    /**
     * Retrieve canonical catalog matches for an LLM or HTML search surface.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function retrieve(array $input = []): array
    {
        $query = $this->cleanString($input['query'] ?? $input['q'] ?? null);
        $intent = $this->normalizeIntent($input['intent'] ?? null);
        $filters = $this->normalizeFilters((array) ($input['filters'] ?? []));
        $limit = $this->boundedLimit($input['limit'] ?? null);

        if (! $this->identityTablesExist()) {
            return $this->response($query, $intent, $filters, $limit, collect(), [
                'identity_index_unavailable',
            ]);
        }

        $queryTokens = $this->tokens($query);
        $candidates = $this->candidateQuery($query, $queryTokens, $filters)
            ->get()
            ->map(fn (CanonicalProductIdentity $identity) => $this->scoreCandidate($identity, $query, $queryTokens, $filters))
            ->filter()
            ->sortByDesc(fn (array $candidate) => $candidate['score'])
            ->values();

        $hydrateLimit = min(self::HYDRATE_LIMIT, max($limit * 3, 20));
        $matches = $candidates
            ->take($hydrateLimit)
            ->map(fn (array $candidate) => $this->hydrateCandidate($candidate, $intent, $filters))
            ->filter()
            ->sortByDesc(fn (array $match) => $match['score'])
            ->take($limit)
            ->values();

        return $this->response($query, $intent, $filters, $limit, $matches);
    }

    /**
     * @param  Collection<int, string>  $queryTokens
     * @param  array<string, mixed>  $filters
     * @return Builder<CanonicalProductIdentity>
     */
    private function candidateQuery(string $query, Collection $queryTokens, array $filters): Builder
    {
        $builder = CanonicalProductIdentity::query()
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
            ->orderByRaw('case when best_offer_product_id is null then 1 else 0 end')
            ->orderByRaw("case confidence when 'high' then 0 when 'medium' then 1 else 2 end")
            ->orderByDesc('seller_offers_count')
            ->orderByDesc('provider_candidates_count')
            ->orderByDesc('last_seen_at')
            ->limit(self::SCAN_LIMIT);

        if ($this->overrideTableExists()) {
            $builder->with('override');
        }

        if ((bool) ($filters['provider_network_only'] ?? false)) {
            $builder->where('provider_candidates_count', '>', 0);
        }

        if (array_key_exists('has_offer', $filters)) {
            $builder->where(function (Builder $query) use ($filters): void {
                if ((bool) $filters['has_offer']) {
                    $query->where('seller_offers_count', '>', 0)
                        ->orWhereNotNull('best_offer_product_id');

                    return;
                }

                $query->where('seller_offers_count', 0)
                    ->whereNull('best_offer_product_id');
            });
        }

        foreach ([
            'category' => 'canonical_category',
            'currency' => 'face_value_currency',
            'region' => 'region',
        ] as $filter => $column) {
            if (! empty($filters[$filter])) {
                $builder->where($column, $filters[$filter]);
            }
        }

        if (! empty($filters['brand'])) {
            $builder->where('brand', 'like', '%'.$this->escapeLike((string) $filters['brand']).'%');
        }

        if (isset($filters['face_value']) && is_numeric($filters['face_value'])) {
            $amount = (float) $filters['face_value'];
            $builder->whereBetween('face_value', [$amount - 0.0001, $amount + 0.0001]);
        }

        if ($query !== '' && $queryTokens->isNotEmpty()) {
            foreach ($queryTokens->take(6) as $token) {
                $builder->where(function (Builder $builder) use ($token): void {
                    $like = '%'.$this->escapeLike($token).'%';

                    $builder
                        ->where('identity_slug', 'like', $like)
                        ->orWhere('brand', 'like', $like)
                        ->orWhere('product_family', 'like', $like)
                        ->orWhere('canonical_category', 'like', $like)
                        ->orWhere('face_value_currency', 'like', $like)
                        ->orWhere('region', 'like', $like);

                    if (is_numeric($token)) {
                        $amount = (float) $token;
                        $builder->orWhereBetween('face_value', [$amount - 0.0001, $amount + 0.0001]);
                    }
                });
            }
        }

        return $builder;
    }

    /**
     * @param  Collection<int, string>  $queryTokens
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>|null
     */
    private function scoreCandidate(CanonicalProductIdentity $identity, string $query, Collection $queryTokens, array $filters): ?array
    {
        $canonicalIdentity = $this->curation->applyApprovedOverrides($identity->toArray(), $identity);
        if (! $this->matchesFilters($canonicalIdentity, $identity, $filters)) {
            return null;
        }

        $policy = $this->indexingPolicy->forCanonicalProduct(
            $canonicalIdentity,
            $this->policyOfferPlaceholder($identity),
            ['provider_candidates_count' => $identity->provider_candidates_count],
            $identity,
        );

        if (! $this->allowsRetrieval($policy)) {
            return null;
        }

        $score = 0.0;
        $reasons = [];
        $matchedQuery = $queryTokens->isEmpty();
        $brand = $this->normalizedText($canonicalIdentity['brand'] ?? '');
        $family = $this->normalizedText($canonicalIdentity['product_family'] ?? '');
        $slug = $this->normalizedText($canonicalIdentity['identity_slug'] ?? $identity->identity_slug);
        $category = $this->normalizedText($canonicalIdentity['canonical_category'] ?? '');
        $queryText = $this->normalizedText($query);

        if ($brand !== '' && $queryText !== '') {
            if ($queryText === $brand || str_contains(' '.$queryText.' ', ' '.$brand.' ')) {
                $score += 40;
                $matchedQuery = true;
                $reasons[] = 'exact_brand_match';
            } else {
                $brandOverlap = $queryTokens->intersect($this->tokens($brand));
                if ($brandOverlap->isNotEmpty()) {
                    $score += 22;
                    $matchedQuery = true;
                    $reasons[] = 'partial_brand_match:'.$brandOverlap->implode(',');
                }
            }
        }

        $nameTokens = $this->tokens(implode(' ', [$family, $slug]));
        $nameOverlap = $queryTokens->intersect($nameTokens)->values();
        if ($nameOverlap->isNotEmpty()) {
            $score += min(32, $nameOverlap->count() * 8);
            $matchedQuery = true;
            $reasons[] = 'name_token_overlap:'.$nameOverlap->implode(',');
        }

        $categoryOverlap = $queryTokens->intersect($this->tokens($category))->values();
        if ($categoryOverlap->isNotEmpty()) {
            $score += 8;
            $matchedQuery = true;
            $reasons[] = 'category_query_match';
        }

        $faceValue = $canonicalIdentity['face_value'] ?? null;
        if (is_numeric($faceValue) && $queryTokens->contains(fn (string $token) => is_numeric($token) && abs((float) $token - (float) $faceValue) < 0.0001)) {
            $score += 15;
            $matchedQuery = true;
            $reasons[] = 'face_value_match';
        }

        $currency = $this->normalizedToken($canonicalIdentity['face_value_currency'] ?? '');
        if ($currency !== '' && $queryTokens->contains($currency)) {
            $score += 12;
            $matchedQuery = true;
            $reasons[] = 'currency_match';
        }

        $region = $this->normalizedToken($canonicalIdentity['region'] ?? '');
        if ($region !== '' && $queryTokens->contains($region)) {
            $score += 10;
            $matchedQuery = true;
            $reasons[] = 'region_match';
        }

        if (! $matchedQuery) {
            return null;
        }

        foreach (['category', 'brand', 'region', 'currency', 'face_value'] as $filter) {
            if (array_key_exists($filter, $filters)) {
                $score += 6;
                $reasons[] = 'filter_'.$filter.'_match';
            }
        }

        if ($identity->best_offer_product_id !== null) {
            $score += 18;
            $reasons[] = 'persisted_best_offer_available';
        } elseif ((int) $identity->seller_offers_count > 0) {
            $score += 10;
            $reasons[] = 'seller_offers_available';
        }

        $providerCount = (int) $identity->provider_candidates_count;
        if ($providerCount > 0) {
            $score += min(8, $providerCount);
            $reasons[] = 'provider_candidates_available';
        }

        $confidence = (string) ($canonicalIdentity['confidence'] ?? $identity->confidence);
        if ($confidence === 'high') {
            $score += 14;
            $reasons[] = 'high_confidence_identity';
        } elseif ($confidence === 'medium') {
            $score += 7;
            $reasons[] = 'medium_confidence_identity';
        }

        return [
            'identity' => $identity,
            'canonical_identity' => $canonicalIdentity,
            'indexing_policy' => $policy,
            'score' => $score,
            'reasons' => array_values(array_unique($reasons)),
        ];
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>|null
     */
    private function hydrateCandidate(array $candidate, string $intent, array $filters): ?array
    {
        /** @var CanonicalProductIdentity $identity */
        $identity = $candidate['identity'];
        $facts = $this->canonicalPages->resolveBySlug((string) $identity->identity_slug, $intent);
        if ($facts === null) {
            return null;
        }

        $canonicalIdentity = (array) ($facts['canonical_identity'] ?? $candidate['canonical_identity']);
        if (! $this->matchesFilters($canonicalIdentity, $identity, $filters)) {
            return null;
        }

        $policy = (array) ($facts['indexing_policy'] ?? $candidate['indexing_policy']);
        if (! $this->allowsRetrieval($policy)) {
            return null;
        }

        $intentResolution = (array) ($facts['intent_resolution'] ?? []);
        $selectedOffer = data_get($intentResolution, 'selected_offer');
        $score = (float) $candidate['score'];
        $reasons = (array) $candidate['reasons'];

        if (is_array($selectedOffer)) {
            $score += 20;
            $reasons[] = 'selected_offer_available';
            $offerScore = (int) data_get($selectedOffer, 'ranking.score', 0);
            if ($offerScore > 0) {
                $score += min(10, $offerScore / 10);
                $reasons[] = 'selected_offer_score_boost';
            }
        } else {
            $reasons[] = 'awaiting_seller_offer';
        }

        return [
            'type' => 'CatalogRetrievalMatch',
            'canonical_identity' => $canonicalIdentity,
            'url' => $facts['url'] ?? route('meanly.canonical-products.show', $identity->identity_slug),
            'machine_readable_at' => $facts['machine_readable_at'] ?? route('llms.catalog.canonical-products.show', $identity->identity_slug),
            'intent_resolution' => $intentResolution ?: null,
            'selected_offer' => is_array($selectedOffer) ? $selectedOffer : null,
            'score' => round($score, 2),
            'reasons' => array_values(array_unique($reasons)),
            'indexing_policy' => $policy,
        ];
    }

    /**
     * @param  array<string, mixed>  $identity
     * @param  array<string, mixed>  $filters
     */
    private function matchesFilters(array $identity, CanonicalProductIdentity $model, array $filters): bool
    {
        if ((bool) ($filters['provider_network_only'] ?? false) && (int) $model->provider_candidates_count <= 0) {
            return false;
        }

        if (array_key_exists('has_offer', $filters)) {
            $hasOffer = $model->best_offer_product_id !== null || (int) $model->seller_offers_count > 0;
            if ($hasOffer !== (bool) $filters['has_offer']) {
                return false;
            }
        }

        if (isset($filters['category']) && (string) ($identity['canonical_category'] ?? '') !== (string) $filters['category']) {
            return false;
        }

        if (isset($filters['brand']) && ! str_contains($this->normalizedText($identity['brand'] ?? ''), $this->normalizedText($filters['brand']))) {
            return false;
        }

        if (isset($filters['region']) && $this->normalizedToken($identity['region'] ?? '') !== $this->normalizedToken($filters['region'])) {
            return false;
        }

        if (isset($filters['currency']) && $this->normalizedToken($identity['face_value_currency'] ?? '') !== $this->normalizedToken($filters['currency'])) {
            return false;
        }

        if (isset($filters['face_value']) && is_numeric($filters['face_value'])) {
            $faceValue = $identity['face_value'] ?? null;
            if (! is_numeric($faceValue) || abs((float) $faceValue - (float) $filters['face_value']) > 0.0001) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $policy
     */
    private function allowsRetrieval(array $policy): bool
    {
        return in_array((string) ($policy['surface'] ?? $policy['visibility'] ?? ''), ['public_index', 'llm_only'], true);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function policyOfferPlaceholder(CanonicalProductIdentity $identity): ?array
    {
        if ($identity->best_offer_product_id === null && (int) $identity->seller_offers_count <= 0) {
            return null;
        }

        return [
            'availability' => $identity->best_offer_product_id !== null ? 'available_to_order' : null,
            'ranking' => ['score' => $identity->best_offer_product_id !== null ? 50 : 25],
            'indexing' => ['indexable' => $identity->best_offer_product_id !== null],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function normalizeFilters(array $filters): array
    {
        $normalized = [];

        foreach ([
            'category' => 'category',
            'brand' => 'brand',
            'region' => 'region',
            'currency' => 'currency',
        ] as $key => $target) {
            $value = $this->cleanString($filters[$key] ?? null);
            if ($value === '') {
                continue;
            }

            $normalized[$target] = match ($target) {
                'currency' => strtoupper($value),
                'region' => Str::of($value)->lower()->toString(),
                default => $value,
            };
        }

        if (isset($filters['face_value']) && is_numeric($filters['face_value'])) {
            $normalized['face_value'] = (float) $filters['face_value'];
        }

        foreach (['has_offer', 'provider_network_only'] as $key) {
            if (array_key_exists($key, $filters)) {
                $normalized[$key] = filter_var($filters[$key], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? (bool) $filters[$key];
            }
        }

        return $normalized;
    }

    private function normalizeIntent(mixed $intent): string
    {
        $intent = $this->cleanString($intent);
        if (in_array($intent, ['buy_now', 'buy-now', 'buy now'], true)) {
            return ProductIntentResolutionService::DEFAULT_INTENT;
        }

        return $this->intentResolver->normalizeIntent($intent);
    }

    private function boundedLimit(mixed $limit): int
    {
        if (! is_numeric($limit)) {
            return self::DEFAULT_LIMIT;
        }

        return max(1, min(self::MAX_LIMIT, (int) $limit));
    }

    private function cleanString(mixed $value): string
    {
        return Str::of((string) $value)
            ->squish()
            ->limit(200, '')
            ->toString();
    }

    private function normalizedText(mixed $value): string
    {
        return Str::of((string) $value)
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->toString();
    }

    private function normalizedToken(mixed $value): string
    {
        return Str::of($this->normalizedText($value))
            ->replace(' ', '')
            ->toString();
    }

    /**
     * @return Collection<int, string>
     */
    private function tokens(mixed $value): Collection
    {
        $stopWords = [
            'a', 'an', 'and', 'are', 'best', 'buy', 'card', 'cards', 'catalog', 'code', 'digital',
            'for', 'gift', 'llm', 'now', 'offer', 'offers', 'product', 'products', 'the', 'to',
            'voucher', 'vouchers',
        ];

        return Str::of($this->normalizedText($value))
            ->explode(' ')
            ->map(fn (string $token) => trim($token))
            ->filter(fn (string $token) => strlen($token) >= 2 && ! in_array($token, $stopWords, true))
            ->unique()
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $matches
     * @param  array<int, string>  $warnings
     * @return array<string, mixed>
     */
    private function response(string $query, string $intent, array $filters, int $limit, Collection $matches, array $warnings = []): array
    {
        return [
            'type' => 'CatalogRetrievalResponse',
            'version' => 1,
            'generated_at' => now()->toIso8601String(),
            'query' => $query !== '' ? $query : null,
            'intent' => $intent,
            'filters' => (object) $filters,
            'limit' => $limit,
            'match_count' => $matches->count(),
            'matches' => $matches->values(),
            'facets' => $this->facets($matches),
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $matches
     * @return array<string, mixed>
     */
    private function facets(Collection $matches): array
    {
        return [
            'categories' => $this->facetCounts($matches, 'canonical_identity.canonical_category'),
            'brands' => $this->facetCounts($matches, 'canonical_identity.brand'),
            'regions' => $this->facetCounts($matches, 'canonical_identity.region'),
            'currencies' => $this->facetCounts($matches, 'canonical_identity.face_value_currency'),
            'has_offer' => [
                'true' => $matches->filter(fn (array $match) => is_array($match['selected_offer'] ?? null))->count(),
                'false' => $matches->filter(fn (array $match) => ! is_array($match['selected_offer'] ?? null))->count(),
            ],
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $matches
     * @return array<string, int>
     */
    private function facetCounts(Collection $matches, string $path): array
    {
        return $matches
            ->map(fn (array $match) => data_get($match, $path))
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->map(fn ($value) => (string) $value)
            ->countBy()
            ->sortDesc()
            ->all();
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

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '\\%_');
    }
}
