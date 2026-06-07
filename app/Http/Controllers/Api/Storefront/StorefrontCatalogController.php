<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Resources\Storefront\StorefrontCatalogResource;
use App\Http\Resources\Storefront\StorefrontProductResource;
use App\Models\Product;
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
        abort_unless(array_key_exists($category, (array) config('catalog_taxonomy.categories', [])), 404);

        $meta = (array) config("catalog_taxonomy.categories.{$category}", []);
        $products = $homepage->categoryPage($category, $request);

        return StorefrontCatalogResource::make([
            'surface' => [
                'type' => 'storefront_catalog_category',
                'category' => $category,
                'title' => $meta['label_en'] ?? $meta['label_ru'] ?? $category,
                'description' => $meta['description_ru'] ?? null,
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
    ): JsonResponse {
        $compact = $request->boolean('compact');
        $data = $compact
            ? $homepage->productGroupPage($category, $brandSlug, $kindSlug, $request, 4)
            : $homepage->productGroupPage($category, $brandSlug, $kindSlug, $request);

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
        MeanlyFirstPartyStorefrontService $storefront,
        CanonicalStorefrontHomepageService $homepage,
    ): JsonResponse {
        $product = $storefront->marketplaceProductsQuery()
            ->where('slug', $slug)
            ->first();

        $card = $product instanceof Product
            ? $this->productCard($product)
            : $homepage->storefrontReadyCards($slug)->firstWhere('slug', $slug);

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
