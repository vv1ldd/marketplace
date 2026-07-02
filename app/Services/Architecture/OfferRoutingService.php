<?php

namespace App\Services\Architecture;

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
    ) {}

    /**
     * ADR 0039 routing boundary: rank available offers for an entitlement, then pin snapshot.
     *
     * @param  Collection<int, array<string, mixed>>  $availableOffers
     */
    public function selectOffer(
        ?string $intent,
        CanonicalProductIdentity $entitlement,
        Collection $availableOffers,
        ?array $routingPolicy = null,
    ): ?OfferSnapshotData {
        if ($availableOffers->isEmpty()) {
            return null;
        }

        $intent = app(ProductIntentResolutionService::class)->normalizeIntent($intent);
        $ranked = $this->rankOffers($availableOffers, $intent, $routingPolicy);
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
     * @return Collection<int, array<string, mixed>>
     */
    public function rankOffers(
        Collection $availableOffers,
        ?string $intent = null,
        ?array $routingPolicy = null,
    ): Collection {
        $intent = $this->intentResolution->normalizeIntent($intent);
        $policy = $routingPolicy['method'] ?? 'intent_'.$intent.'_seller_offer_v1';

        $ranked = match ($intent) {
            'lowest_price' => $availableOffers->sortBy(fn (array $offer) => (float) data_get($offer, 'price.amount', PHP_FLOAT_MAX)),
            'in_stock' => $availableOffers->sortByDesc(fn (array $offer) => (int) (data_get($offer, 'ranking.metrics.stock_count', 0) > 0)),
            'trusted_seller' => $availableOffers->sortByDesc(fn (array $offer) => (int) data_get($offer, 'ranking.metrics.seller_completed_90_days', 0)),
            default => $availableOffers->sortByDesc(fn (array $offer) => (int) data_get($offer, 'ranking.score', 0)),
        };

        return $ranked->values()->map(function (array $offer, int $index) use ($intent, $policy) {
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
