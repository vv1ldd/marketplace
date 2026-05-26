<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class SovereignPlaneGuard
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (!$user) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Unauthenticated'], 401);
            }
            return redirect()->route('login');
        }

        // God Mode - Redirect Super Admin to the Operations Console (/ops)
        if ($user->hasRole('super_admin')) {
            if ($request->is('partner*') && !$request->is('partner/logout')) {
                return redirect('/ops');
            }
            return $next($request);
        }

        // Validate B2B Consortium Plane Access
        if ($request->is('partner*')) {
            if (!$user->isB2BPartner() && !$user->isSystemUser()) {
                $legalEntity = $user->legalEntities()->first()
                    ?? $user->managedLegalEntities()->first();

                if ($legalEntity?->status === 'pending_signature') {
                    return redirect()->route('partner.register.offer');
                }

                if ($legalEntity && ($legalEntity->status === 'pending_moderation' || $legalEntity->agreement_signed_at)) {
                    return redirect()->route('partner.onboarding');
                }

                if ($request->expectsJson()) {
                    return response()->json(['error' => 'Forbidden: Access to B2B Consortium Plane denied.'], 403);
                }
                abort(403, 'Доступ в Consortium B2B Plane ограничен. Требуется статус B2B партнера.');
            }
        }

        return $next($request);
    }
}
