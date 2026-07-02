<?php

namespace App\Http\Controllers;

use App\Services\CanonicalCategoryResolver;
use App\Services\CanonicalStorefrontHomepageService;
use App\Services\DiscoveryEntityGraphService;
use App\Support\StorefrontFrontendRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MeanlyCatalogCategoryController extends Controller
{
    public function index(Request $request, CanonicalStorefrontHomepageService $catalog, \App\Services\CatalogSearchLogService $logService): RedirectResponse
    {
        if (filled($request->query('q'))) {
            $products = $catalog->catalogPage($request);
            $logService->log(
                (string) $request->query('q'),
                'storefront',
                $products->total()
            );
        }

        return StorefrontFrontendRedirect::fromRequest($request);
    }

    public function show(string $category, Request $request): RedirectResponse
    {
        abort_unless(array_key_exists($category, (array) config('catalog_taxonomy.categories', [])), 404);

        return StorefrontFrontendRedirect::fromRequest($request);
    }

    public function group(string $intent, string $brandSlug, string $kindSlug, Request $request, CanonicalStorefrontHomepageService $catalog): RedirectResponse
    {
        $page = $catalog->productGroupPage($intent, $brandSlug, $kindSlug, $request);
        abort_unless($page !== null, 404);

        $slug = $catalog->groupProductSlug((string) ($page['group']['brand'] ?? $brandSlug), $kindSlug);

        return redirect()->route('products.show', array_merge(['slug' => $slug], $request->query()), 301);
    }

    public function legacyGroup(
        string $category,
        string $brandSlug,
        string $kindSlug,
        Request $request,
        CanonicalCategoryResolver $resolver,
        CanonicalStorefrontHomepageService $catalog,
    ): RedirectResponse {
        $intent = $resolver->isKnownIntentCorridor($category)
            ? $category
            : $resolver->discoveryIntent($category, [str_replace('-', ' ', $brandSlug)]);

        abort_unless($resolver->isKnownIntentCorridor($intent), 404);

        $page = $catalog->productGroupPage($intent, $brandSlug, $kindSlug, $request);
        abort_unless($page !== null, 404);

        $slug = $catalog->groupProductSlug((string) ($page['group']['brand'] ?? $brandSlug), $kindSlug);

        return redirect()->route('products.show', array_merge(['slug' => $slug], $request->query()), 301);
    }

    public function collection(string $slug, Request $request): RedirectResponse
    {
        \App\Models\SeoCollection::where('slug', $slug)->where('is_active', true)->firstOrFail();

        return StorefrontFrontendRedirect::fromRequest($request);
    }

    public function brand(string $brandSlug, Request $request, DiscoveryEntityGraphService $graph): RedirectResponse
    {
        abort_unless($graph->brand($brandSlug) !== null, 404);

        return StorefrontFrontendRedirect::fromRequest($request);
    }

    public function region(string $regionSlug, Request $request, DiscoveryEntityGraphService $graph): RedirectResponse
    {
        abort_unless($graph->region($regionSlug) !== null, 404);

        return StorefrontFrontendRedirect::fromRequest($request);
    }

    public function brandRegion(string $brandSlug, string $regionSlug, Request $request, DiscoveryEntityGraphService $graph): RedirectResponse
    {
        abort_unless($graph->brandRegion($brandSlug, $regionSlug) !== null, 404);

        return StorefrontFrontendRedirect::fromRequest($request);
    }
}
