<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AllowIframeForRoute
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $response->headers->remove('X-Frame-Options');
        $response->headers->set('Content-Security-Policy', "frame-ancestors *");

        // Альтернатива (современнее): CSP
        // $response->headers->set('Content-Security-Policy', "frame-ancestors 'self' https://example.com");

        return $response;
    }
}

