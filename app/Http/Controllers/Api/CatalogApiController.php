<?php

namespace App\Http\Controllers\Api;

use App\Models\ProviderProduct;
use App\Models\Shop;
use App\Services\StandardizationService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Unified provider catalog API.
 * GET /api/catalog/products?token={import_token}&provider=fazer&brand=Steam&page=1&per_page=100
 */
class CatalogApiController extends Controller
{
    public function __construct(protected StandardizationService $standardizer) {}

    public function products(Request $request)
    {
        // --- Auth: магазин по import_token ---
        $token = $request->query('token') ?? $request->bearerToken();
        $shop  = $token ? Shop::where('import_token', $token)->first() : null;

        // --- Filters ---
        $provider = $request->query('provider');   // fazer | wildflow
        $brand    = $request->query('brand');
        $category = $request->query('category');
        $perPage  = min((int) ($request->query('per_page', 100)), 500);

        $query = ProviderProduct::with(['provider', 'brand.catalogGroup', 'region'])
            ->where('is_active', true);

        if ($provider) {
            $query->whereHas('provider', fn ($q) => $q->where('type', $provider));
        }

        if ($brand) {
            $query->whereHas('brand', fn ($q) => $q->where('name', 'like', "%{$brand}%"));
        }

        if ($category) {
            $query->where('category', 'like', "%{$category}%");
        }

        $paginator = $query->orderBy('provider_id')->orderBy('id')->paginate($perPage);

        $items = $paginator->getCollection()->map(
            fn ($item) => $this->standardizer->standardizeProviderProduct($item, $shop)
        );

        return response()->json([
            'meta' => [
                'total'        => $paginator->total(),
                'per_page'     => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'shop'         => $shop ? ['id' => $shop->id, 'name' => $shop->name, 'tariff' => $shop->tariff_type, 'markup_percent' => $shop->markup_percent] : null,
            ],
            'products' => $items,
        ]);
    }

    public function show(Request $request, string $sku)
    {
        $token = $request->query('token') ?? $request->bearerToken();
        $shop  = $token ? Shop::where('import_token', $token)->first() : null;

        $item = ProviderProduct::with(['provider', 'brand.catalogGroup', 'region'])
            ->where('sku', $sku)
            ->where('is_active', true)
            ->firstOrFail();

        return response()->json(
            $this->standardizer->standardizeProviderProduct($item, $shop)
        );
    }

    /**
     * Summary: count by provider and top brands.
     */
    public function summary(Request $request)
    {
        $byProvider = \Illuminate\Support\Facades\DB::table('provider_products')
            ->join('providers', 'providers.id', '=', 'provider_products.provider_id')
            ->selectRaw('providers.name as provider, providers.type, count(*) as total, sum(provider_products.is_active) as active')
            ->groupBy('providers.id', 'providers.name', 'providers.type')
            ->get();

        $topBrands = \Illuminate\Support\Facades\DB::table('provider_products')
            ->join('brands', 'brands.id', '=', 'provider_products.brand_id')
            ->selectRaw('brands.name as brand, count(*) as total')
            ->whereNotNull('provider_products.brand_id')
            ->groupBy('brands.id', 'brands.name')
            ->orderByDesc('total')
            ->limit(20)
            ->get();

        return response()->json([
            'by_provider' => $byProvider,
            'top_brands'  => $topBrands,
            'total'       => ProviderProduct::count(),
        ]);
    }
}
