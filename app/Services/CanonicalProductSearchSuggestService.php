<?php

namespace App\Services;

use App\Models\CanonicalProductSearchProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CanonicalProductSearchSuggestService
{
    private const CANDIDATE_LIMIT = 80;

    public function __construct(
        private readonly CanonicalCategoryResolver $categoryResolver,
    ) {}

    /**
     * @return array{query: string, results: array<int, array<string, mixed>>}
     */
    public function suggestions(Request $request, int $limit = 8): array
    {
        $query = trim((string) ($request->query('q') ?? $request->query('intent') ?? ''));
        $limit = max(1, min($limit, 10));

        if (mb_strlen($query) < 2) {
            return ['query' => $query, 'results' => []];
        }

        $context = $this->queryContext($query);
        $candidates = $this->candidateProfiles($context);
        $context['requested_brands'] = $this->requestedValues($candidates, $context, 'brand');
        $context['requested_regions'] = $this->requestedValues($candidates, $context, 'region');

        $results = $candidates
            ->map(fn (CanonicalProductSearchProfile $profile): ?array => $this->rankedSuggestion($profile, $context))
            ->filter()
            ->sort(function (array $left, array $right): int {
                return [$right['_score'], $right['_availability_score'], -$right['_profile_id']]
                    <=> [$left['_score'], $left['_availability_score'], -$left['_profile_id']];
            })
            ->take($limit)
            ->map(function (array $result): array {
                unset($result['_score'], $result['_availability_score'], $result['_profile_id']);

                return $result;
            })
            ->values()
            ->all();

        return ['query' => $query, 'results' => $results];
    }

    /**
     * @param array<string, mixed> $context
     * @return Collection<int, CanonicalProductSearchProfile>
     */
    private function candidateProfiles(array $context): Collection
    {
        $terms = $context['terms'];
        $phrase = $context['phrase'];
        $lookupTerms = array_values(array_unique(array_filter([$phrase, ...$terms])));

        return CanonicalProductSearchProfile::query()
            ->with('identity')
            ->where('profile_version', CanonicalProductSearchProfileBuilder::PROFILE_VERSION)
            ->whereNull('last_error')
            ->where(function ($query) use ($lookupTerms): void {
                foreach ($lookupTerms as $term) {
                    $jsonLike = '%"'.$this->escapeLike($term).'"%';

                    $query
                        ->orWhere('search_tokens', 'like', $jsonLike)
                        ->orWhere('search_aliases', 'like', $jsonLike);

                    if (mb_strlen($term) > 2) {
                        $query->orWhere('search_text', 'like', '%'.$this->escapeLike($term).'%');
                    }
                }
            })
            ->limit(self::CANDIDATE_LIMIT)
            ->get();
    }

    /**
     * @param Collection<int, CanonicalProductSearchProfile> $profiles
     * @param array<string, mixed> $context
     * @return array<int, string>
     */
    private function requestedValues(Collection $profiles, array $context, string $type): array
    {
        return $profiles
            ->filter(fn (CanonicalProductSearchProfile $profile): bool => $this->aliasMatches($profile, $context, $type))
            ->map(fn (CanonicalProductSearchProfile $profile): ?string => $this->metadataValue($profile, $type))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>|null
     */
    private function rankedSuggestion(CanonicalProductSearchProfile $profile, array $context): ?array
    {
        $identity = $profile->identity;
        if ($identity === null) {
            return null;
        }

        $score = 0;
        $breakdown = [
            'alias' => 0,
            'brand' => 0,
            'region' => 0,
            'category' => 0,
            'token' => 0,
            'penalty' => 0,
        ];

        $aliasMatch = $this->aliasMatches($profile, $context);
        if ($aliasMatch) {
            $score += 60;
            $breakdown['alias'] = 60;
        }

        $brandMatch = $this->matchesRequested($profile, $context, 'brand');
        if ($brandMatch) {
            $score += 20;
            $breakdown['brand'] = 20;
        }

        $regionMatch = $this->matchesRequested($profile, $context, 'region');
        if ($regionMatch) {
            $score += 15;
            $breakdown['region'] = 15;
        }

        if ($this->aliasMatches($profile, $context, 'category')) {
            $score += 10;
            $breakdown['category'] = 10;
        }

        $tokenMatches = count(array_intersect($context['terms'], (array) $profile->search_tokens));
        if ($tokenMatches > 0) {
            $tokenScore = min(25, $tokenMatches * 5);
            $score += $tokenScore;
            $breakdown['token'] = $tokenScore;
        }

        if ($context['requested_brands'] !== [] && ! $brandMatch) {
            $score -= 100;
            $breakdown['penalty'] -= 100;
        }

        if ($context['requested_regions'] !== [] && ! $regionMatch) {
            $score -= 10;
            $breakdown['penalty'] -= 10;
        }

        if ($score <= 0) {
            return null;
        }

        $availabilityScore = $identity->best_offer_product_id !== null ? 1 : 0;

        return [
            'id' => (int) $identity->id,
            'name' => $this->displayName($profile),
            'url' => route('meanly.canonical-products.show', $identity->identity_slug),
            'category' => $this->categoryResolver->label((string) data_get($profile->search_metadata, 'category', 'gift_cards')),
            'brand' => data_get($profile->search_metadata, 'brand'),
            'price' => null,
            'image' => null,
            'availability' => $availabilityScore === 1 ? 'available' : 'soon',
            'match_label' => $this->matchLabel($breakdown),
            '_score' => $score,
            '_availability_score' => $availabilityScore,
            '_profile_id' => (int) $profile->id,
        ];
    }

    /**
     * @param array<string, mixed> $context
     */
    private function aliasMatches(CanonicalProductSearchProfile $profile, array $context, ?string $type = null): bool
    {
        $aliases = $type !== null
            ? (array) data_get($profile->search_aliases, $type, [])
            : collect((array) $profile->search_aliases)->flatten()->all();

        foreach ($aliases as $alias) {
            $alias = $this->normalize((string) $alias);
            if ($alias === '') {
                continue;
            }

            if ($context['phrase'] === $alias || in_array($alias, $context['terms'], true)) {
                return true;
            }

            if (str_contains($context['phrase'], $alias) && mb_strlen($alias) > 2) {
                return true;
            }

            foreach ($context['terms'] as $term) {
                if (mb_strlen($term) >= 3 && str_starts_with($alias, $term)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function matchesRequested(CanonicalProductSearchProfile $profile, array $context, string $type): bool
    {
        $requested = $context[$type === 'brand' ? 'requested_brands' : 'requested_regions'] ?? [];
        if ($requested === []) {
            return $this->aliasMatches($profile, $context, $type);
        }

        $value = $this->metadataValue($profile, $type);

        return $value !== null && in_array($value, $requested, true);
    }

    private function metadataValue(CanonicalProductSearchProfile $profile, string $type): ?string
    {
        $key = $type === 'brand' ? 'brand' : 'region';
        $value = data_get($profile->search_metadata, $key);

        return $value !== null ? $this->normalize((string) $value) : null;
    }

    private function displayName(CanonicalProductSearchProfile $profile): string
    {
        $metadata = (array) $profile->search_metadata;
        $parts = collect([
            $metadata['brand'] ?? null,
            $this->identityFamily($profile),
            isset($metadata['face_value']) && is_numeric($metadata['face_value']) ? $this->formatAmount((float) $metadata['face_value']) : null,
            $metadata['currency'] ?? null,
            $metadata['region'] ?? null,
        ])->filter(fn ($value): bool => trim((string) $value) !== '')->values();

        return $parts->isNotEmpty()
            ? $parts->implode(' ')
            : Str::headline(str_replace('-', ' ', (string) $profile->identity?->identity_slug));
    }

    private function identityFamily(CanonicalProductSearchProfile $profile): ?string
    {
        $identity = $profile->identity;
        if ($identity === null) {
            return null;
        }

        $family = trim((string) $identity->product_family);
        $brand = trim((string) data_get($profile->search_metadata, 'brand', ''));

        return $family !== '' && $this->normalize($family) !== $this->normalize($brand)
            ? Str::title($family)
            : null;
    }

    /**
     * @param array<string, int> $breakdown
     */
    private function matchLabel(array $breakdown): string
    {
        if ($breakdown['brand'] > 0 && $breakdown['region'] > 0) {
            return __('runtime.suggest.brand_region');
        }

        if ($breakdown['alias'] > 0) {
            return __('runtime.suggest.alias_match');
        }

        return __('runtime.suggest.token_match');
    }

    /**
     * @return array{phrase: string, terms: array<int, string>, requested_brands: array<int, string>, requested_regions: array<int, string>}
     */
    private function queryContext(string $query): array
    {
        $phrase = $this->normalize($query);
        $terms = collect(preg_split('/[^\pL\pN]+/u', $phrase) ?: [])
            ->map(fn (string $term): string => trim($term))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return [
            'phrase' => $phrase,
            'terms' => $terms,
            'requested_brands' => [],
            'requested_regions' => [],
        ];
    }

    private function normalize(string $value): string
    {
        $value = Str::lower(trim($value));
        $value = preg_replace('/\s+/u', ' ', $value) ?? '';

        return trim($value);
    }

    private function formatAmount(float $amount): string
    {
        return rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.');
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '\\%_');
    }
}
