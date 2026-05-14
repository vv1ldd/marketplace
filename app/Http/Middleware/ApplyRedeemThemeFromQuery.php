<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Сохраняет тему redeem (?theme=light|dark) в сессии для всех шагов /redeem.
 */
class ApplyRedeemThemeFromQuery
{
    public function handle(Request $request, Closure $next)
    {
        if (in_array($request->query('theme'), ['light', 'dark'], true)) {
            session(['redeem_theme' => $request->query('theme')]);
        }

        return $next($request);
    }
}
