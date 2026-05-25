<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\LlmProductFactsService;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function show(string $slug, LlmProductFactsService $llmFacts)
    {
        $product = Product::where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();
        $productFacts = $llmFacts->productFacts($product);
        $productJsonLd = $llmFacts->productJsonLd($product);

        return view('products.show', compact('product', 'productFacts', 'productJsonLd'));
    }

    public function search(Request $request)
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
            
        return response()->json([
            'products' => $products->items(),
            'has_more' => $products->hasMorePages(),
            'current_page' => $products->currentPage(),
            'total' => $products->total()
        ]);
    }
}
