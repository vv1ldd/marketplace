<?php

namespace App\Services;

use App\Models\ProviderProduct;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ProductIntentResolutionService
{
    public const DEFAULT_INTENT = 'best_offer';

    /**
     * @var array<string, string>
     */
    private const INTENT_LABELS = [
        'best_offer' => 'Best offer',
        'lowest_price' => 'Lowest price',
        'in_stock' => 'In stock',
        'trusted_seller' => 'Trusted seller',
    ];

    public function __construct(
        private readonly SellerOfferRankingService $offerRanking,
        private readonly ProviderNetworkCatalogService $network,
        private readonly CanonicalCategoryResolver $categoryResolver,
    ) {}

    /**
     * @return array<int, string>
     */
    public function supportedIntents(): array
    {
        return array_keys(self::INTENT_LABELS);
    }

    public function normalizeIntent(?string $intent): string
    {
        $key = (string) Str::of((string) ($intent ?: self::DEFAULT_INTENT))
            ->lower()
            ->ascii()
            ->replace(['-', ' '], '_')
            ->replaceMatches('/_+/', '_')
            ->trim('_');

        $aliases = [
            'best' => 'best_offer',
            'recommended' => 'best_offer',
            'recommended_offer' => 'best_offer',
            'cheapest' => 'lowest_price',
            'cheap' => 'lowest_price',
            'price' => 'lowest_price',
            'instock' => 'in_stock',
            'stock' => 'in_stock',
            'available' => 'in_stock',
            'reliable' => 'trusted_seller',
            'trusted' => 'trusted_seller',
            'reliable_seller' => 'trusted_seller',
        ];

        $key = $aliases[$key] ?? $key;

        return array_key_exists($key, self::INTENT_LABELS) ? $key : self::DEFAULT_INTENT;
    }

    public function intentLabel(string $intent): string
    {
        return self::INTENT_LABELS[$this->normalizeIntent($intent)] ?? self::INTENT_LABELS[self::DEFAULT_INTENT];
    }

    /**
     * @return array<string, mixed>
     */
    public function resolve(ProviderProduct $product, ?string $intent = null, int $alternativeLimit = 6): array
    {
        return $this->resolveFromRankedOffers(
            $product,
            $intent,
            $this->offerRanking->rankedOffersForProviderProduct($product),
            $alternativeLimit,
        );
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rankedOffers
     * @return array<string, mixed>
     */
    public function resolveFromRankedOffers(ProviderProduct $product, ?string $intent, Collection $rankedOffers, int $alternativeLimit = 6): array
    {
        $intent = $this->normalizeIntent($intent);
        $intentRankedOffers = $this->rankOffersForIntent($rankedOffers, $intent)
            ->values()
            ->map(function (array $offer, int $index) use ($intent) {
                $offer['intent_ranking'] = [
                    'intent' => $intent,
                    'position' => $index + 1,
                    'method' => $this->rankingMethod($intent),
                ];

                return $offer;
            });

        $selectedOffer = $intentRankedOffers->first();
        $alternatives = $intentRankedOffers
            ->when($selectedOffer !== null, fn (Collection $offers) => $offers->reject(
                fn (array $offer) => (int) ($offer['product_id'] ?? 0) === (int) ($selectedOffer['product_id'] ?? 0)
            ))
            ->take($alternativeLimit)
            ->values();

        return [
            'type' => 'ProductIntentResolution',
            'intent' => $intent,
            'intent_label' => $this->intentLabel($intent),
            'url' => $this->intentUrl($product, $intent),
            'machine_readable_at' => $this->intentMachineReadableUrl($product, $intent),
            'seller_offers_machine_readable_at' => route('llms.network.products.offers', $this->network->publicSlug($product)),
            'candidate' => $this->candidateSummary($product, $intent),
            'selected_offer' => $selectedOffer,
            'alternatives' => $alternatives,
            'decision' => $this->decision($intent, $selectedOffer),
            'indexing' => $this->indexing($product, $selectedOffer),
        ];
    }

    /**
     * Resolve buyer intent for a logical canonical product page where offers may
     * come from several provider-network candidates.
     *
     * @param  array<string, mixed>  $pageCandidate
     * @param  Collection<int, array<string, mixed>>  $rankedOffers
     * @return array<string, mixed>
     */
    public function resolveCanonicalProduct(array $pageCandidate, ?string $intent, Collection $rankedOffers, int $alternativeLimit = 8): array
    {
        $intent = $this->normalizeIntent($intent);
        $intentRankedOffers = $this->rankOffersForIntent($rankedOffers, $intent)
            ->values()
            ->map(function (array $offer, int $index) use ($intent) {
                $offer['intent_ranking'] = [
                    'intent' => $intent,
                    'position' => $index + 1,
                    'method' => $this->rankingMethod($intent),
                ];

                return $offer;
            });

        $selectedOffer = $intentRankedOffers->first();
        $alternatives = $intentRankedOffers
            ->when($selectedOffer !== null, fn (Collection $offers) => $offers->reject(
                fn (array $offer) => (int) ($offer['product_id'] ?? 0) === (int) ($selectedOffer['product_id'] ?? 0)
            ))
            ->take($alternativeLimit)
            ->values();

        return [
            'type' => 'CanonicalProductIntentResolution',
            'intent' => $intent,
            'intent_label' => $this->intentLabel($intent),
            'url' => data_get($pageCandidate, 'url'),
            'machine_readable_at' => data_get($pageCandidate, 'machine_readable_at'),
            'seller_offers_machine_readable_at' => data_get($pageCandidate, 'seller_offers_machine_readable_at'),
            'candidate' => $pageCandidate,
            'selected_offer' => $selectedOffer,
            'alternatives' => $alternatives,
            'decision' => $this->decision($intent, $selectedOffer),
            'indexing' => [
                'indexable' => $selectedOffer !== null,
                'reason' => $selectedOffer !== null ? 'canonical_selected_offer' : 'awaiting_seller_availability',
            ],
        ];
    }

    /**
     * @param  array<int, string>|null  $intents
     * @return Collection<int, array<string, mixed>>
     */
    public function indexableIntentResolutions(ProviderProduct $product, ?array $intents = null): Collection
    {
        $rankedOffers = $this->offerRanking->rankedOffersForProviderProduct($product);

        return collect($intents ?: $this->supportedIntents())
            ->map(fn (string $intent) => $this->resolveFromRankedOffers($product, $intent, $rankedOffers, 0))
            ->filter(fn (array $resolution) => (bool) data_get($resolution, 'indexing.indexable'))
            ->values();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function selectedOfferJsonLd(array $resolution): ?array
    {
        $offer = $resolution['selected_offer'] ?? null;
        if (! is_array($offer)) {
            return null;
        }

        return [
            '@type' => 'Offer',
            '@id' => $resolution['machine_readable_at'].'#selected-offer',
            'url' => $offer['url'],
            'priceCurrency' => data_get($offer, 'price.currency', pricing()->displayCurrency),
            'price' => data_get($offer, 'price.amount'),
            'availability' => match ($offer['availability'] ?? null) {
                'in_stock' => 'https://schema.org/InStock',
                'auto_purchase' => 'https://schema.org/PreOrder',
                default => 'https://schema.org/LimitedAvailability',
            },
            'seller' => [
                '@type' => 'Organization',
                'name' => data_get($offer, 'seller.name') ?: 'Meanly seller',
            ],
            'itemOffered' => [
                '@type' => 'Product',
                'name' => data_get($resolution, 'candidate.name'),
                'url' => data_get($resolution, 'candidate.url'),
            ],
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $offers
     * @return Collection<int, array<string, mixed>>
     */
    private function rankOffersForIntent(Collection $offers, string $intent): Collection
    {
        return match ($intent) {
            'lowest_price' => $this->lowestPriceOffers($offers),
            'in_stock' => $offers->sort($this->sortByInStock(...)),
            'trusted_seller' => $offers->sort($this->sortBySellerTrust(...)),
            default => $offers->sortByDesc(fn (array $offer) => (int) data_get($offer, 'ranking.score', 0)),
        };
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $offers
     * @return Collection<int, array<string, mixed>>
     */
    private function lowestPriceOffers(Collection $offers): Collection
    {
        $indexableOffers = $offers->filter(fn (array $offer) => (bool) data_get($offer, 'indexing.indexable')
            && (float) data_get($offer, 'price.amount', 0) > 0);

        return ($indexableOffers->isNotEmpty() ? $indexableOffers : $offers)
            ->sort($this->sortByLowestPrice(...));
    }

    private function sortByLowestPrice(array $left, array $right): int
    {
        $price = ((float) data_get($left, 'price.amount', PHP_FLOAT_MAX))
            <=> ((float) data_get($right, 'price.amount', PHP_FLOAT_MAX));

        return $price !== 0 ? $price : $this->compareScoreDesc($left, $right);
    }

    private function sortByInStock(array $left, array $right): int
    {
        $stock = (int) (data_get($right, 'availability') === 'in_stock') <=> (int) (data_get($left, 'availability') === 'in_stock');

        return $stock !== 0 ? $stock : $this->compareScoreDesc($left, $right);
    }

    private function sortBySellerTrust(array $left, array $right): int
    {
        $leftRate = $this->completionRate($left);
        $rightRate = $this->completionRate($right);

        if ($leftRate !== $rightRate) {
            return $rightRate <=> $leftRate;
        }

        $completed = (int) data_get($right, 'ranking.metrics.seller_completed_90_days', 0)
            <=> (int) data_get($left, 'ranking.metrics.seller_completed_90_days', 0);
        if ($completed !== 0) {
            return $completed;
        }

        $orders = (int) data_get($right, 'ranking.metrics.seller_orders_90_days', 0)
            <=> (int) data_get($left, 'ranking.metrics.seller_orders_90_days', 0);

        return $orders !== 0 ? $orders : $this->compareScoreDesc($left, $right);
    }

    private function compareScoreDesc(array $left, array $right): int
    {
        return (int) data_get($right, 'ranking.score', 0) <=> (int) data_get($left, 'ranking.score', 0);
    }

    private function completionRate(array $offer): float
    {
        $total = (int) data_get($offer, 'ranking.metrics.seller_orders_90_days', 0);
        $completed = (int) data_get($offer, 'ranking.metrics.seller_completed_90_days', 0);

        return $total > 0 ? $completed / $total : 0.0;
    }

    /**
     * @return array<string, mixed>
     */
    private function candidateSummary(ProviderProduct $product, string $intent): array
    {
        $canonicalCategory = $product->canonical_category ?: $this->categoryResolver->forProviderProduct($product);

        return [
            'id' => $product->id,
            'url' => $this->intentUrl($product, $intent),
            'base_url' => route('meanly.network.products.show', $this->network->publicSlug($product)),
            'name' => $product->name,
            'canonical_category' => $canonicalCategory,
            'canonical_category_label' => $this->categoryResolver->label($canonicalCategory),
            'machine_readable_at' => $this->intentMachineReadableUrl($product, $intent),
            'seller_offers_machine_readable_at' => route('llms.network.products.offers', $this->network->publicSlug($product)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decision(string $intent, ?array $selectedOffer): array
    {
        if ($selectedOffer === null) {
            return [
                'has_offer' => false,
                'ranking_method' => $this->rankingMethod($intent),
                'reason' => 'Provider network candidate is awaiting an active compatible seller offer.',
                'signals' => [],
                'badges' => [],
            ];
        }

        return [
            'has_offer' => true,
            'ranking_method' => $this->rankingMethod($intent),
            'reason' => match ($intent) {
                'lowest_price' => 'Selected the cheapest indexable offer, using ranking score as the tie-breaker.',
                'in_stock' => 'Selected an in-stock offer when available, using ranking score as the tie-breaker.',
                'trusted_seller' => 'Selected the offer with the strongest seller completion signals, using score as the tie-breaker.',
                default => 'Selected the highest ranking seller offer.',
            },
            'signals' => data_get($selectedOffer, 'ranking.metrics', []),
            'badges' => data_get($selectedOffer, 'ranking.badges', []),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function indexing(ProviderProduct $product, ?array $selectedOffer): array
    {
        if ($selectedOffer === null) {
            return [
                'indexable' => false,
                'reason' => 'awaiting_seller_availability',
            ];
        }

        if ($this->network->quality($product) === 'noindex_candidate') {
            return [
                'indexable' => false,
                'reason' => 'candidate_quality_noindex',
            ];
        }

        $indexable = (bool) data_get($selectedOffer, 'indexing.indexable');

        return [
            'indexable' => $indexable,
            'reason' => $indexable ? 'selected_offer_indexable' : (string) data_get($selectedOffer, 'indexing.reason', 'thin_offer'),
        ];
    }

    private function rankingMethod(string $intent): string
    {
        return 'intent_'.$intent.'_seller_offer_v1';
    }

    private function intentUrl(ProviderProduct $product, string $intent): string
    {
        return route('meanly.network.products.show', [
            'idSlug' => $this->network->publicSlug($product),
            'intent' => $intent,
        ]);
    }

    private function intentMachineReadableUrl(ProviderProduct $product, string $intent): string
    {
        return route('llms.network.products.intents.show', [
            'idSlug' => $this->network->publicSlug($product),
            'intent' => $intent,
        ]);
    }
}
