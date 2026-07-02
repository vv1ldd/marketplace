<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Resources\Storefront\StorefrontCatalogResource;
use App\Http\Resources\Storefront\StorefrontProductResource;
use App\Models\Product;
use App\Services\CanonicalCategoryResolver;
use App\Services\CanonicalProductSearchSuggestService;
use App\Services\CanonicalStorefrontHomepageService;
use App\Services\MeanlyFirstPartyStorefrontService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StorefrontCatalogController extends Controller
{
    public function index(Request $request, CanonicalStorefrontHomepageService $homepage): StorefrontCatalogResource
    {
        return StorefrontCatalogResource::make($homepage->homepage($request));
    }

    public function search(Request $request, CanonicalStorefrontHomepageService $homepage): StorefrontCatalogResource
    {
        $data = $homepage->searchPage($request);

        return StorefrontCatalogResource::make([
            'query' => $data['query'],
            'quick_chips' => [],
            'featured_products' => [],
            'provider_network_products' => [],
            'product_groups' => [],
            'categories' => [],
            'brands' => [],
            'browse_products' => $data['browse_products'],
            'stats' => [],
        ]);
    }

    public function category(string $category, Request $request, CanonicalStorefrontHomepageService $homepage): StorefrontCatalogResource
    {
        abort_unless(app(CanonicalCategoryResolver::class)->isKnownIntentCorridor($category), 404);

        $meta = (array) config("catalog_taxonomy.intent_corridors.{$category}", []);
        $resolver = app(CanonicalCategoryResolver::class);
        $categoryDescription = app()->getLocale() === 'ru'
            ? ($meta['description_ru'] ?? $meta['description_en'] ?? null)
            : ($meta['description_en'] ?? $meta['description_ru'] ?? null);
        $products = $homepage->categoryPage($category, $request);

        return StorefrontCatalogResource::make([
            'surface' => [
                'type' => 'storefront_catalog_category',
                'category' => $category,
                'discovery_intent' => $category,
                'discovery_intent_key' => $resolver->discoveryIntentKey($category),
                'title' => $resolver->discoveryLabel($category),
                'description' => $categoryDescription,
                'cross_links' => collect($resolver->crossLinksForCorridor($category))
                    ->map(fn (array $link): array => [
                        'target_slug' => (string) ($link['target_corridor'] ?? ''),
                        'anchor_text' => app()->getLocale() === 'ru'
                            ? ($link['label_ru'] ?? $link['label_en'] ?? '')
                            : ($link['label_en'] ?? $link['label_ru'] ?? ''),
                        'brand_focus' => (string) (((array) ($link['brand_filter'] ?? []))[0] ?? ''),
                    ])
                    ->values()
                    ->all(),
            ],
            'query' => '',
            'quick_chips' => [],
            'featured_products' => [],
            'provider_network_products' => [],
            'product_groups' => [],
            'categories' => [],
            'brands' => [],
            'browse_products' => $products,
            'stats' => [
                'products_total' => $products->total(),
            ],
        ]);
    }

    public function group(
        string $category,
        string $brandSlug,
        string $kindSlug,
        Request $request,
        CanonicalStorefrontHomepageService $homepage,
        CanonicalCategoryResolver $resolver,
    ): JsonResponse {
        $intent = $resolver->isKnownIntentCorridor($category)
            ? $category
            : $resolver->discoveryIntent($category, [str_replace('-', ' ', $brandSlug)]);

        abort_unless($resolver->isKnownIntentCorridor($intent), 404);

        $compact = $request->boolean('compact');
        $data = $compact
            ? $homepage->productGroupPage($intent, $brandSlug, $kindSlug, $request, 4)
            : $homepage->productGroupPage($intent, $brandSlug, $kindSlug, $request);

        abort_unless($data !== null, 404);

        $products = $data['products'];

        return response()->json([
            'contract' => [
                'name' => 'storefront-catalog-group',
                'version' => 'v1',
                'authority' => 'marketplace-commerce',
                'dto_boundary' => 'transitions_not_conditions',
            ],
            'group' => $data['group'],
            'meta' => $data['meta'],
            'facets' => $compact ? [
                'regions' => $data['facets']['regions'] ?? [],
                'nominals' => $data['facets']['nominals'] ?? [],
                'selected' => $data['facets']['selected'] ?? [],
            ] : $data['facets'],
            'products' => StorefrontProductResource::collection(collect($products->items())),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'last_page' => $products->lastPage(),
            ],
        ]);
    }

    public function suggest(Request $request, CanonicalProductSearchSuggestService $suggest): JsonResponse
    {
        return response()->json([
            'contract' => [
                'name' => 'storefront-suggest',
                'version' => 'v1',
                'authority' => 'marketplace-commerce',
                'dto_boundary' => 'transitions_not_conditions',
            ],
            'data' => $suggest->suggestions($request),
        ]);
    }

    public function product(
        string $slug,
        Request $request,
        MeanlyFirstPartyStorefrontService $storefront,
        CanonicalStorefrontHomepageService $homepage,
    ): JsonResponse {
        if ($homepage->parseGroupProductSlug($slug) !== null) {
            $groupPage = $homepage->productGroupPageBySlug($slug, $request);
            if ($groupPage !== null) {
                return $this->groupProductResponse($slug, $groupPage);
            }
        }

        $product = $storefront->marketplaceProductsQuery()
            ->where('slug', $slug)
            ->first();

        if ($product instanceof Product) {
            return response()->json([
                'contract' => [
                    'name' => 'storefront-product',
                    'version' => 'v1',
                    'authority' => 'marketplace-commerce',
                    'dto_boundary' => 'transitions_not_conditions',
                ],
                'data' => StorefrontProductResource::make($this->productCard($product)),
            ]);
        }

        $card = $homepage->storefrontReadyCards($slug)->firstWhere('slug', $slug);
        abort_if($card === null, 404);

        return response()->json([
            'contract' => [
                'name' => 'storefront-product',
                'version' => 'v1',
                'authority' => 'marketplace-commerce',
                'dto_boundary' => 'transitions_not_conditions',
            ],
            'data' => StorefrontProductResource::make($card),
        ]);
    }

    /**
     * @param  array<string, mixed>  $groupPage
     */
    private function groupProductResponse(string $slug, array $groupPage): JsonResponse
    {
        $group = (array) ($groupPage['group'] ?? []);
        $products = $groupPage['products'];

        return response()->json([
            'contract' => [
                'name' => 'storefront-product-group',
                'version' => 'v1',
                'authority' => 'marketplace-commerce',
                'dto_boundary' => 'transitions_not_conditions',
            ],
            'data' => [
                'type' => 'storefront_product_group',
                'id' => $slug,
                'slug' => $slug,
                'name' => (string) ($group['title'] ?? $slug),
                'brand' => $group['brand'] ?? null,
                'category' => [
                    'slug' => (string) ($group['discovery_intent'] ?? $group['category'] ?? ''),
                    'label' => (string) ($group['category_label'] ?? ''),
                ],
                'variant_group' => [
                    'is_grouped' => true,
                    'variant_count' => (int) ($group['variant_count'] ?? 0),
                    'region_count' => (int) ($group['region_count'] ?? 0),
                    'nominal_count' => (int) ($group['nominal_count'] ?? 0),
                ],
                'links' => [
                    'self' => route('products.show', $slug),
                ],
                'actions' => [
                    'allowed_actions' => ['VIEW'],
                    'blocked_actions' => ['ADD_TO_CART', 'CHECKOUT'],
                    'next_action' => 'VIEW',
                    'blocking_reason' => 'group_requires_variant_selection',
                ],
            ],
            'group_page' => [
                'group' => $group,
                'meta' => $groupPage['meta'] ?? [],
                'facets' => $groupPage['facets'] ?? [],
                'products' => StorefrontProductResource::collection(collect($products instanceof \Illuminate\Pagination\LengthAwarePaginator ? $products->items() : [])),
                'pagination' => $products instanceof \Illuminate\Pagination\LengthAwarePaginator ? [
                    'current_page' => $products->currentPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                    'last_page' => $products->lastPage(),
                ] : null,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function productCard(Product $product): array
    {
        $price = [
            'amount' => round(((float) ($product->price_rub ?? 0)) / 100, 2),
            'currency' => 'RUB',
        ];

        return [
            'id' => $product->id,
            'slug' => $product->slug,
            'url' => route('meanly.storefront.products.show', $product->slug),
            'machine_readable_at' => route('llms.catalog.canonical-products.show', $product->slug),
            'name' => $product->name,
            'category' => $product->canonical_category ?? $product->category,
            'category_label' => (string) ($product->canonical_category ?? $product->category ?? 'Catalog'),
            'brand' => $product->vendor,
            'product_family' => $product->type,
            'face_value' => null,
            'face_value_currency' => null,
            'region' => $product->shop?->shop_region ?: 'global',
            'status_label' => 'Storefront product',
            'selected_offer' => [
                'product_id' => $product->id,
                'name' => $product->name,
                'seller' => ['name' => $product->shop?->name ?? 'Meanly seller'],
                'price' => $price,
                'availability' => 'available_to_order',
            ],
        ];
    }
}
