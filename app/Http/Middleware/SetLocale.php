<?php

namespace App\Http\Middleware;

use App\Services\LocaleResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $resolved = app(LocaleResolver::class)->resolve($request);

        App::setLocale($resolved['locale']);
        session(['locale' => $resolved['locale']]);

        View::share('currentLocale', $resolved['locale']);
        View::share('currentLocaleSource', $resolved['source']);
        View::share('supportedLocales', app(LocaleResolver::class)->localeLabels());

        $response = $next($request);
        $response->headers->set('Content-Language', $resolved['locale']);
        $response->headers->set('Vary', trim($response->headers->get('Vary').' Accept-Language'));

        return $response;
    }
}
