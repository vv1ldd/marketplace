<?php

namespace App\Http\Controllers;

use App\Models\ProviderProduct;
use App\Services\ProductIntentResolutionService;
use App\Services\ProviderNetworkCatalogService;
use App\Support\StorefrontFrontendRedirect;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProviderNetworkCatalogController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        return StorefrontFrontendRedirect::fromRequest($request);
    }

    public function category(string $category, Request $request): RedirectResponse
    {
        abort_unless(array_key_exists($category, (array) config('catalog_taxonomy.categories', [])), 404);

        return StorefrontFrontendRedirect::fromRequest($request);
    }

    public function show(string $idSlug, ProviderNetworkCatalogService $network, ProductIntentResolutionService $intentResolver, Request $request): RedirectResponse
    {
        abort_unless($network->findByPublicSlug($idSlug) instanceof ProviderProduct, 404);

        return StorefrontFrontendRedirect::fromRequest($request);
    }

    public function categoryJson(string $category, ProviderNetworkCatalogService $network): JsonResponse
    {
        abort_unless(array_key_exists($category, (array) config('catalog_taxonomy.categories', [])), 404);
        $products = $network->candidatesQuery($category)
            ->orderByDesc('brand_id')
            ->orderBy('name')
            ->limit(100)
            ->get();

        return response()->json([
            'type' => 'MeanlyProviderNetworkCategory',
            'canonical_category' => $category,
            'meta' => config("catalog_taxonomy.categories.{$category}"),
            'candidates' => $products->map(fn (ProviderProduct $product) => $network->facts($product))->values(),
            'identity_groups' => $network->groupCandidatesByIdentity($products),
        ], 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function categoryIdentitiesJson(string $category, ProviderNetworkCatalogService $network): JsonResponse
    {
        abort_unless(array_key_exists($category, (array) config('catalog_taxonomy.categories', [])), 404);
        $products = $network->candidatesQuery($category)
            ->orderByDesc('brand_id')
            ->orderBy('name')
            ->limit(200)
            ->get();
        $groups = $network->groupCandidatesByIdentity($products);

        return response()->json([
            'type' => 'MeanlyProviderNetworkIdentityGroups',
            'version' => 1,
            'canonical_category' => $category,
            'meta' => config("catalog_taxonomy.categories.{$category}"),
            'candidate_limit' => 200,
            'candidate_count' => $products->count(),
            'identity_group_count' => $groups->count(),
            'groups' => $groups,
        ], 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function productJson(string $idSlug, ProviderNetworkCatalogService $network): JsonResponse
    {
        $product = $network->findByPublicSlug($idSlug);
        abort_unless($product instanceof ProviderProduct, 404);
        $facts = $network->facts($product);

        return response()->json([
            'type' => 'MeanlyProviderNetworkCandidate',
            'candidate' => $facts,
            'indexing_policy' => $facts['indexing_policy'],
            'json_ld' => $network->jsonLd($product),
        ], 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function productOffersJson(string $idSlug, ProviderNetworkCatalogService $network): JsonResponse
    {
        $product = $network->findByPublicSlug($idSlug);
        abort_unless($product instanceof ProviderProduct, 404);

        $facts = $network->facts($product);
        $offers = collect($facts['seller_offers']['offers'])
            ->filter(fn (array $offer) => (bool) data_get($offer, 'indexing.indexable'))
            ->values();

        return response()->json([
            'type' => 'MeanlySellerOfferIndex',
            'candidate_url' => $facts['url'],
            'canonical_category' => $facts['canonical_category'],
            'ranking_method' => $facts['seller_offers']['ranking_method'],
            'indexable_offer_count' => $offers->count(),
            'offers' => $offers,
        ], 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function productIntentJson(string $idSlug, string $intent, ProviderNetworkCatalogService $network, ProductIntentResolutionService $intentResolver): JsonResponse
    {
        $product = $network->findByPublicSlug($idSlug);
        abort_unless($product instanceof ProviderProduct, 404);

        $facts = $network->facts($product);
        $resolution = $intentResolver->resolve($product, $intent);
        $resolution['candidate']['indexing_policy'] = $facts['indexing_policy'];

        return response()->json([
            'type' => 'MeanlyProductIntentDecision',
            'version' => 1,
            'resolution' => $resolution,
            'indexing_policy' => $facts['indexing_policy'],
            'candidate' => [
                'url' => $facts['url'],
                'machine_readable_at' => $facts['machine_readable_at'],
                'canonical_identity' => $facts['canonical_identity'],
                'indexing_policy' => $facts['indexing_policy'],
            ],
            'json_ld' => $intentResolver->selectedOfferJsonLd($resolution),
        ], 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
