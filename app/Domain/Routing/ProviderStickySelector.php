<?php

namespace App\Domain\Routing;

use Illuminate\Support\Collection;

class ProviderStickySelector
{
    /**
     * @param  Collection<int, float>  $providerScores  provider_id => score
     */
    public function selectProviderId(
        RoutingPolicy $policy,
        string $entitlementId,
        string $intentId,
        Collection $providerScores,
    ): ?int {
        $eligible = $providerScores
            ->filter(fn (float $score): bool => $score > 0)
            ->sortKeys();

        if ($eligible->isEmpty()) {
            return null;
        }

        if ($eligible->count() === 1) {
            return (int) $eligible->keys()->first();
        }

        $trafficWeights = $policy->providerTrafficWeights();
        $weightedProviders = $eligible->keys()
            ->mapWithKeys(function (int $providerId) use ($trafficWeights, $eligible): array {
                $weight = $trafficWeights[$providerId] ?? (int) round($eligible->get($providerId, 0) * 100);

                return [$providerId => max(0, $weight)];
            })
            ->filter(fn (int $weight): bool => $weight > 0);

        if ($weightedProviders->isEmpty()) {
            return $this->highestScoreProvider($eligible);
        }

        $slot = $policy->calculateStickySlot($entitlementId, $intentId);
        $cursor = 0;

        foreach ($weightedProviders->sortKeys() as $providerId => $weight) {
            $cursor += $weight;
            if ($slot < $cursor) {
                return (int) $providerId;
            }
        }

        return (int) $weightedProviders->keys()->last();
    }

    /**
     * @param  Collection<int, float>  $eligible
     */
    private function highestScoreProvider(Collection $eligible): ?int
    {
        $maxScore = $eligible->max();
        $topProviders = $eligible
            ->filter(fn (float $score): bool => abs($score - $maxScore) < 0.000001)
            ->keys()
            ->sort()
            ->values();

        return $topProviders->isEmpty() ? null : (int) $topProviders->first();
    }
}
