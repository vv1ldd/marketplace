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

        if ($user->hasOpsSovereignAccess()) {
            if ($request->is('partner*') && !$request->is('partner/logout')) {
                return redirect('/ops');
            }
            return $next($request);
        }

        // Validate Merchant Center access.
        if ($request->is('partner*')) {
            if ($request->routeIs('partner.onboarding') || $request->routeIs('partner.logout')) {
                return $next($request);
            }

            $legalEntity = $user->legalEntities()->first()
                ?? $user->managedLegalEntities()->first();

            if ($legalEntity?->status === 'pending_signature') {
                if ($request->expectsJson()) {
                    return response()->json(['error' => 'Legal entity must sign the agreement before partner actions.'], 403);
                }

                return redirect()->route('partner.register.offer');
            }

            $status = (string) ($legalEntity?->status ?? '');
            $isExplicitlyInactive = $legalEntity && $status === 'active' && ! (bool) $legalEntity->is_active;

            if ($legalEntity && (in_array($status, ['pending_signature', 'pending_moderation'], true) || $isExplicitlyInactive)) {
                if ($request->expectsJson() || ! $request->isMethod('GET')) {
                    return response()->json(['error' => 'Legal entity must be active before partner actions.'], 403);
                }

                return redirect()->route('partner.onboarding');
            }

            if (!$user->isMerchantNode() && !$user->isSystemUser()) {
                if ($request->expectsJson()) {
                    return response()->json(['error' => 'Forbidden: Access to Merchant Center denied.'], 403);
                }
                abort(403, 'Доступ в Merchant Center ограничен. Требуется authority merchant_node.');
            }

            if ($request->isMethod('POST') && ! $this->canPerformPartnerMutation($user, $legalEntity, (string) $request->route()?->getName())) {
                return response()->json(['error' => 'Forbidden: insufficient partner role for this action.'], 403);
            }
        }

        return $next($request);
    }

    private function canPerformPartnerMutation($user, $legalEntity, string $routeName): bool
    {
        if ($user->isSystemUser() || $user->hasOpsSovereignAccess()) {
            return true;
        }

        if (! $legalEntity) {
            return false;
        }

        if ((int) $legalEntity->user_id === (int) $user->id) {
            return true;
        }

        $managed = $user->managedLegalEntities()->whereKey($legalEntity->id)->first();
        $role = (string) ($managed?->pivot?->role ?? '');

        $financeRoutes = [
            'partner.dashboard.finance.deposit',
            'partner.dashboard.deposit_intent',
            'partner.dashboard.clear_deposit_intent',
            'partner.dashboard.finance.sovereign_request.options',
            'partner.dashboard.finance.sovereign_request.create',
        ];

        if (in_array($routeName, $financeRoutes, true)) {
            return in_array($role, ['owner', 'admin', 'finance'], true);
        }

        $supportRoutes = [
            'partner.dashboard.tickets.create',
            'partner.dashboard.tickets.reply',
        ];

        if (in_array($routeName, $supportRoutes, true)) {
            return in_array($role, ['owner', 'admin', 'support'], true);
        }

        return in_array($role, ['owner', 'admin', 'operator', 'manager'], true);
    }
}
