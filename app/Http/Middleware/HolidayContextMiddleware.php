<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\SovereignCalendar;
use Carbon\Carbon;

/**
 * 🗓️ HolidayContextMiddleware
 *
 * Resolves the current holiday from SovereignCalendar and sets
 * a cookie `holiday` on every response.
 *
 * Blade templates read: request()->cookie('holiday') ?? null
 * JS reads: the data-holiday attribute set by the blade, no client-side computation.
 */
class HolidayContextMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        // Resolve active holiday for today (supports manual ?date=YYYY-MM-DD for testing)
        $date = Carbon::now();
        if ($request->has('date')) {
            try {
                $date = Carbon::parse($request->query('date'));
            } catch (\Exception $e) {
                // Ignore invalid date format and keep now
            }
        }
        $holiday = SovereignCalendar::resolve($date);
        $manualCookie = strtolower(trim((string) $request->cookie('holiday', '')));

        // Allow manual override via query param for testing: ?holiday=valentine
        if ($request->has('holiday')) {
            $override = $request->query('holiday');
            $holiday = ($override && $override !== 'none') ? strtolower((string) $override) : null;
        } elseif (! $holiday && $manualCookie !== '' && ! in_array($manualCookie, ['auto', 'none'], true)) {
            $holiday = $manualCookie;
        }

        // Inject resolved holiday into current request's cookies so that blade/controllers see it instantly
        if ($holiday) {
            $request->cookies->set('holiday', $holiday);
        } else {
            $request->cookies->remove('holiday');
        }

        $response = $next($request);

        // Set cookie for 1 day (1440 minutes), path=/, no encryption needed (it's not sensitive)
        if ($holiday) {
            $response->cookie('holiday', $holiday, 1440, '/', config('session.domain'), false, false);
        } elseif (in_array($manualCookie, ['auto', 'none', ''], true)) {
            // Clear only when there is no manual storefront preview cookie to preserve.
            $response->cookie('holiday', '', -1, '/', config('session.domain'), false, false);
        }

        return $response;
    }
}
