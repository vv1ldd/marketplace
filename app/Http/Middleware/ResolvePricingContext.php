<?php

namespace App\Http\Middleware;

use App\Services\PricingContextResolver;
use App\Support\MarketContext;
use App\Support\PricingContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class ResolvePricingContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $context = app(PricingContextResolver::class)->resolve(app(MarketContext::class));

        app()->instance(PricingContext::class, $context);
        $request->attributes->set('pricing_context', $context);
        View::share('pricingContext', $context);

        $response = $next($request);
        $response->headers->set('X-Pricing-Scope', $context->pricingScope);
        $response->headers->set('X-Display-Currency', $context->displayCurrency);

        return $response;
    }
}
