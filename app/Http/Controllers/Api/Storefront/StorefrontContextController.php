<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Services\MarketContextResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StorefrontContextController extends Controller
{
    public function show(Request $request, MarketContextResolver $markets): JsonResponse
    {
        $market = $markets->resolve($request);

        return response()->json([
            'contract' => [
                'name' => 'storefront-context',
                'version' => 'v1',
                'authority' => 'marketplace-commerce',
            ],
            'market' => [
                'key' => $market->market,
                'name' => $market->name,
                'host' => $market->host,
                'locale' => $market->locale,
                'currency' => $market->currency,
                'catalog_scope' => $market->catalogScope,
                'pricing_scope' => $market->pricingScope,
                'demand_region' => $market->demandRegion,
                'preferred_product_regions' => $market->preferredProductRegions,
                'sales_channels' => $market->salesChannels,
                'matched_domain' => $market->matchedDomain,
            ],
            'region' => config('mutation.region', env('MARKETPLACE_REGION', 'local')),
            'simple_l1' => [
                'identity_provider_url' => config('simple_l1.identity_provider_url'),
                'client_id' => config('simple_l1.client_id'),
            ],
        ]);
    }
}
