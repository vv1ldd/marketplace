<?php

namespace App\Http\Middleware;

use App\Services\MarketContextResolver;
use App\Support\MarketContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class ResolveMarketContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $context = app(MarketContextResolver::class)->resolve($request);

        app()->instance(MarketContext::class, $context);
        $request->attributes->set('market_context', $context);
        View::share('marketContext', $context);

        $response = $next($request);
        $response->headers->set('X-Market', $context->market);
        $response->headers->set('Vary', trim($response->headers->get('Vary').' Host'));

        return $response;
    }
}
