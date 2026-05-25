<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ApplyRedeemThemeFromQuery
{
    public function handle(Request $request, Closure $next)
    {
        session()->forget('redeem_theme');

        $theme = app(\App\Services\ThemeResolver::class)->normalize((string) $request->query('theme'));
        if ($theme) {
            session(['theme' => $theme]);
            $request->cookies->set('theme', $theme);
        }

        $response = $next($request);

        if ($theme) {
            $response->cookie('theme', $theme, 525600, '/', config('session.domain'), false, false);
        }

        return $response;
    }
}
