<?php

namespace App\Http\Middleware;

use App\Services\ThemeResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class SetTheme
{
    public function handle(Request $request, Closure $next): Response
    {
        $resolver = app(ThemeResolver::class);
        $resolved = $resolver->resolve($request);

        session(['theme' => $resolved['theme']]);
        $request->cookies->set('theme', $resolved['theme']);

        View::share('currentTheme', $resolved['theme']);
        View::share('currentThemeSource', $resolved['source']);
        View::share('supportedThemes', $resolver->themeLabels());

        $response = $next($request);
        $response->cookie('theme', $resolved['theme'], 525600, '/', config('session.domain'), false, false);
        $response->headers->set('X-Theme', $resolved['theme']);

        return $response;
    }
}
