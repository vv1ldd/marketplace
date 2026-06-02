<?php

namespace App\Http\Controllers;

use App\Services\CanonicalProductPageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CanonicalProductPageController extends Controller
{
    public function show(string $identitySlug, CanonicalProductPageService $pages, Request $request): View
    {
        $facts = $pages->resolveBySlug(
            $identitySlug,
            $request->query('intent'),
            $request->integer('offer') ?: null,
        );
        abort_unless($facts !== null, 404);

        return view('catalog.product', [
            'facts' => $facts,
            'intentResolution' => $facts['intent_resolution'],
            'jsonLd' => $this->jsonLd($facts),
        ]);
    }

    public function productJson(string $identitySlug, CanonicalProductPageService $pages): JsonResponse
    {
        $facts = $pages->resolveBySlug($identitySlug);
        abort_unless($facts !== null, 404);

        return response()->json([
            'type' => 'MeanlyCanonicalProductPage',
            'version' => 1,
            'market_context' => $facts['market_context'] ?? null,
            'locale' => $facts['locale'] ?? app()->getLocale(),
            'canonical_url' => $facts['canonical_url'] ?? $facts['url'],
            'alternate_urls' => $facts['alternate_urls'] ?? [],
            'indexing_policy' => $facts['indexing_policy'],
            'product' => $facts,
            'json_ld' => $this->jsonLd($facts),
        ], 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function productIntentJson(string $identitySlug, string $intent, CanonicalProductPageService $pages): JsonResponse
    {
        $facts = $pages->resolveBySlug($identitySlug, $intent);
        abort_unless($facts !== null, 404);

        return response()->json([
            'type' => 'MeanlyCanonicalProductIntentDecision',
            'version' => 1,
            'resolution' => $facts['intent_resolution'],
            'market_context' => $facts['market_context'] ?? null,
            'locale' => $facts['locale'] ?? app()->getLocale(),
            'indexing_policy' => $facts['indexing_policy'],
            'product' => [
                'url' => $facts['canonical_url'] ?? $facts['url'],
                'canonical_url' => $facts['canonical_url'] ?? $facts['url'],
                'alternate_urls' => $facts['alternate_urls'] ?? [],
                'machine_readable_at' => $facts['machine_readable_at'],
                'canonical_identity' => $facts['canonical_identity'],
                'provider_candidate_count' => data_get($facts, 'provider_candidates.count'),
                'seller_offer_count' => data_get($facts, 'seller_offers.count'),
                'indexing_policy' => $facts['indexing_policy'],
                'indexing' => $facts['indexing'],
            ],
            'json_ld' => $this->jsonLd($facts),
        ], 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param  array<string, mixed>  $facts
     * @return array<string, mixed>
     */
    private function jsonLd(array $facts): array
    {
        $offers = collect(data_get($facts, 'seller_offers.offers', []));
        $prices = $offers
            ->pluck('price.amount')
            ->filter(fn ($value) => is_numeric($value))
            ->map(fn ($value) => (float) $value);
        $priceCurrency = (string) data_get($offers->first(), 'price.currency', pricing()->displayCurrency);

        return [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            '@id' => ($facts['canonical_url'] ?? $facts['url']).'#canonical-product',
            'name' => $facts['name'],
            'description' => $facts['description'],
            'url' => $facts['canonical_url'] ?? $facts['url'],
            'inLanguage' => $facts['locale'] ?? app()->getLocale(),
            'mainEntityOfPage' => $facts['canonical_url'] ?? $facts['url'],
            'brand' => [
                '@type' => 'Brand',
                'name' => $facts['brand'] ?: 'Digital',
            ],
            'category' => $facts['canonical_category_label'],
            'offers' => $offers->isNotEmpty()
                ? [
                    '@type' => 'AggregateOffer',
                    'offerCount' => $offers->count(),
                    'lowPrice' => $prices->min(),
                    'highPrice' => $prices->max(),
                    'priceCurrency' => $priceCurrency,
                    'offers' => $offers
                        ->take(10)
                        ->map(fn (array $offer) => [
                            '@type' => 'Offer',
                            'url' => $offer['url'],
                            'priceCurrency' => data_get($offer, 'price.currency', 'RUB'),
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
                        ])
                        ->values()
                        ->all(),
                ]
                : [
                    '@type' => 'Offer',
                    'url' => $facts['canonical_url'] ?? $facts['url'],
                    'availability' => 'https://schema.org/LimitedAvailability',
                    'description' => 'This product is available for sellers to connect and will show checkout when an offer is live.',
                ],
            'additionalProperty' => collect([
                'region' => $facts['region'] ?? null,
                'face_value' => $facts['face_value'] ?? null,
                'face_value_currency' => $facts['face_value_currency'] ?? null,
                'market' => data_get($facts, 'market_context.market'),
                'market_currency' => data_get($facts, 'market_context.currency'),
            ])
                ->filter(fn ($value) => $value !== null && $value !== '')
                ->map(fn ($value, string $name) => [
                    '@type' => 'PropertyValue',
                    'name' => $name,
                    'value' => $value,
                ])
                ->values()
                ->all(),
        ];
    }
}
