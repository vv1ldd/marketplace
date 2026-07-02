<?php

namespace App\Services\Architecture;

use App\Domain\Routing\ProviderStickySelector;
use App\Domain\Routing\RoutingPolicy;
use App\Domain\Routing\WeightedOfferScorer;
use App\DTO\Architecture\OfferSnapshotData;
use App\Models\CanonicalProductIdentity;
use App\Models\Product;
use App\Services\ProductIntentResolutionService;
use App\Services\SellerOfferRankingService;
use Illuminate\Support\Collection;

class OfferRoutingService
{
    public function __construct(
        private readonly OfferSnapshotServiceInterface $offerSnapshotService,
        private readonly SellerOfferRankingService $offerRanking,
        private readonly ProductIntentResolutionService $intentResolution,
        private readonly WeightedOfferScorer $offerScorer,
        private readonly ProviderStickySelector $stickySelector,
    ) {}

    /**
     * ADR 0039 routing boundary: rank available offers for an entitlement, then pin snapshot.
     *
     * @param  Collection<int, array<string, mixed>>  $availableOffers
     * @param  array<int, int>  $excludeProviderIds
     */
    public function selectOffer(
        ?string $intent,
        CanonicalProductIdentity $entitlement,
        Collection $availableOffers,
        RoutingPolicy|array|null $routingPolicy = null,
        array $excludeProviderIds = [],
    ): ?OfferSnapshotData {
        if ($availableOffers->isEmpty()) {
            return null;
        }

        $intent = $this->intentResolution->normalizeIntent($intent);
        $ranked = $this->rankOffers(
            $availableOffers,
            $intent,
            $routingPolicy,
            $excludeProviderIds,
            (string) ($entitlement->fingerprint ?: $entitlement->id),
        );
        $selected = $ranked->first();

        if (! is_array($selected)) {
            return null;
        }

        $productId = (int) ($selected['product_id'] ?? 0);
        $product = Product::query()->find($productId);
        if (! $product) {
            return null;
        }

        return $this->offerSnapshotService->createFromProduct($product, 'select_offer:'.$intent);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $availableOffers
     * @param  array<int, int>  $excludeProviderIds
     * @return Collection<int, array<string, mixed>>
     */
    public function rankOffers(
        Collection $availableOffers,
        ?string $intent = null,
        RoutingPolicy|array|null $routingPolicy = null,
        array $excludeProviderIds = [],
        ?string $entitlementFingerprint = null,
    ): Collection {
        if (is_array($routingPolicy)) {
            return $this->rankOffersLegacy($availableOffers, $intent, $routingPolicy);
        }

        if (! config('routing.enabled', false)) {
            return $this->rankOffersLegacy($availableOffers, $intent, null);
        }

        return $this->rankOffersWeighted(
            $availableOffers,
            $intent,
            $routingPolicy ?? RoutingPolicy::fromConfig(),
            $excludeProviderIds,
            $entitlementFingerprint,
        );
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $availableOffers
     * @param  array<int, int>  $excludeProviderIds
     * @return Collection<int, array<string, mixed>>
     */
    private function rankOffersWeighted(
        Collection $availableOffers,
        ?string $intent,
        RoutingPolicy $policy,
        array $excludeProviderIds,
        ?string $entitlementFingerprint,
    ): Collection {
        $intent = $this->intentResolution->normalizeIntent($intent);
        $entitlementId = $entitlementFingerprint ?: 'unknown';

        $scored = $availableOffers->map(function (array $offer) use ($availableOffers, $policy, $excludeProviderIds): array {
            $offer['routing_score'] = $this->offerScorer->score(
                $offer,
                $availableOffers,
                $policy,
                $excludeProviderIds,
            );

            return $offer;
        });

        $providerScores = $scored
            ->groupBy(fn (array $offer): int => $this->offerScorer->providerId($offer))
            ->map(fn (Collection $group): float => (float) $group->max('routing_score'));

        $selectedProviderId = $this->stickySelector->selectProviderId(
            $policy,
            $entitlementId,
            $intent,
            $providerScores,
        );

        $stickySlot = $policy->calculateStickySlot($entitlementId, $intent);

        $ranked = $scored->sort(function (array $left, array $right) use ($selectedProviderId): int {
            $leftProvider = $this->offerScorer->providerId($left);
            $rightProvider = $this->offerScorer->providerId($right);

            if ($selectedProviderId !== null) {
                if ($leftProvider === $selectedProviderId && $rightProvider !== $selectedProviderId) {
                    return -1;
                }

                if ($rightProvider === $selectedProviderId && $leftProvider !== $selectedProviderId) {
                    return 1;
                }
            }

            $scoreCompare = ($right['routing_score'] ?? 0) <=> ($left['routing_score'] ?? 0);
            if ($scoreCompare !== 0) {
                return $scoreCompare;
            }

            return ((int) ($left['product_id'] ?? 0)) <=> ((int) ($right['product_id'] ?? 0));
        });

        return $ranked->values()->map(function (array $offer, int $index) use (
            $intent,
            $policy,
            $stickySlot,
            $selectedProviderId,
        ): array {
            $offer['routing'] = [
                'intent' => $intent,
                'position' => $index + 1,
                'method' => $policy->type.'_'.$policy->version,
                'policy_version' => $policy->version,
                'weighted_score' => $offer['routing_score'] ?? 0.0,
                'sticky_slot' => $stickySlot,
                'selected_provider_id' => $selectedProviderId,
                'provider_id' => $this->offerScorer->providerId($offer),
            ];

            return $offer;
        });
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $availableOffers
     * @param  array<string, mixed>|null  $routingPolicy
     * @return Collection<int, array<string, mixed>>
     */
    private function rankOffersLegacy(
        Collection $availableOffers,
        ?string $intent,
        ?array $routingPolicy,
    ): Collection {
        $intent = $this->intentResolution->normalizeIntent($intent);
        $policy = $routingPolicy['method'] ?? 'intent_'.$intent.'_seller_offer_v1';

        $ranked = match ($intent) {
            'lowest_price' => $availableOffers->sortBy(fn (array $offer) => (float) data_get($offer, 'price.amount', PHP_FLOAT_MAX)),
            'in_stock' => $availableOffers->sortByDesc(fn (array $offer) => (int) (data_get($offer, 'ranking.metrics.stock_count', 0) > 0)),
            'trusted_seller' => $availableOffers->sortByDesc(fn (array $offer) => (int) data_get($offer, 'ranking.metrics.seller_completed_90_days', 0)),
            default => $availableOffers->sortByDesc(fn (array $offer) => (int) data_get($offer, 'ranking.score', 0)),
        };

        return $ranked->values()->map(function (array $offer, int $index) use ($intent, $policy): array {
            $offer['routing'] = [
                'intent' => $intent,
                'position' => $index + 1,
                'method' => $policy,
            ];

            return $offer;
        });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function availableOffersForEntitlement(CanonicalProductIdentity $entitlement): Collection
    {
        $product = $entitlement->bestOfferProduct;
        if (! $product) {
            return collect();
        }

        return $this->offerRanking->rankedOffersForProducts(collect([$product]));
    }
}
