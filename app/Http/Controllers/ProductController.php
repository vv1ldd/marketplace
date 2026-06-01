<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\LlmProductFactsService;
use App\Services\PricingProjectionService;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function show(string $slug, LlmProductFactsService $llmFacts, PricingProjectionService $pricingProjection)
    {
        $product = Product::where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();
        $productFacts = $llmFacts->productFacts($product);
        $productJsonLd = $llmFacts->productJsonLd($product);
        $productDisplayPrice = $pricingProjection->publicPriceForProduct($product);
        $productDisplayPriceLabel = $pricingProjection->format($productDisplayPrice);

        return view('products.show', compact('product', 'productFacts', 'productJsonLd', 'productDisplayPrice', 'productDisplayPriceLabel'));
    }

    public function search(Request $request, PricingProjectionService $pricingProjection)
    {
        $search = $request->input('query');
        $platform = $request->input('platform');
        
        $query = Product::where('is_active', true);
        
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('vendor', 'like', "%{$search}%");
            });
        }
        
        if ($platform && $platform !== 'all') {
            $query->where(function($q) use ($platform) {
                $q->where('vendor', 'like', "%{$platform}%")
                  ->orWhere('name', 'like', "%{$platform}%")
                  ->orWhere('category', 'like', "%{$platform}%");
            });
        }
        
        $products = $query->select(['id', 'name', 'slug', 'image', 'price_rub', 'purchase_price', 'purchase_currency', 'category', 'vendor'])
            ->orderBy('id', 'desc')
            ->paginate(12);

        $items = collect($products->items())
            ->map(function (Product $product) use ($pricingProjection): array {
                $price = $pricingProjection->publicPriceForProduct($product);

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'image' => $product->image,
                    'purchase_price' => $product->purchase_price,
                    'purchase_currency' => $product->purchase_currency,
                    'category' => $product->category,
                    'vendor' => $product->vendor,
                    'display_price' => $price,
                ];
            })
            ->values()
            ->all();
            
        return response()->json([
            'products' => $items,
            'has_more' => $products->hasMorePages(),
            'current_page' => $products->currentPage(),
            'total' => $products->total()
        ]);
    }
}
