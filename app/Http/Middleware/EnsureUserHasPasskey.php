<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureUserHasPasskey
{
    public function handle(Request $request, Closure $next)
    {
        // 🛡️ Bypass check during the Passkey enrollment ceremony
        if (
            $request->routeIs('partner.register.*')
            || $request->routeIs('business.register*')
            || $request->routeIs('legal-entities.register*')
            || $request->is('partner/register/*')
            || $request->is('business/register*')
            || $request->is('legal-entities/register*')
            || $request->routeIs('register.verify')
            || $request->routeIs('lang.switch')
            || $request->routeIs('theme.switch')
            || $request->is('lang/*')
            || $request->is('theme/*')
            || $request->is('register/verify-intent')
        ) {
            return $next($request);
        }

        // 🎟️ Bypass for staff invite accept flow (public onboarding)
        if ($request->routeIs('invite.accept') || $request->routeIs('invite.accept.options') || $request->routeIs('invite.accept.submit') || $request->is('invite/*')) {
            return $next($request);
        }

        $user = Auth::user();

        \Illuminate\Support\Facades\Log::debug("EnsureUserHasPasskey: Checking user", [
            'has_user' => !is_null($user),
            'user_type' => $user ? get_class($user) : null,
            'sl1e' => $user instanceof \App\Models\User ? $user->sovereignIdentityAddress() : null,
        ]);

        if (session('redirect_to_b2b') && $user) {
            $seller = $user instanceof \App\Models\User ? $user->primarySellerAccount() : null;
            session()->forget('redirect_to_b2b');
            if ($seller) {
                $b2bUrl = '/partner';
                \Illuminate\Support\Facades\Log::info("EnsureUserHasPasskey: B2C Session contains redirect_to_b2b flag. Redirecting B2B Seller to console: {$b2bUrl}");
                return redirect($b2bUrl);
            }
        }

        if ($user) {
            if ($user instanceof \App\Models\Seller) {
                $entity = $user->managedLegalEntities()->wherePivotNotNull('user_id')->first();
                $coreUser = $entity?->pivot?->user_id ? \App\Models\User::find($entity->pivot->user_id) : null;
                
                \Illuminate\Support\Facades\Log::debug("EnsureUserHasPasskey: Resolved core user for Seller", [
                    'core_user_found' => !is_null($coreUser),
                    'core_user_has_passkeys' => $coreUser ? $coreUser->passkeys()->exists() : false,
                ]);

                if (!$coreUser || !$coreUser->passkeys()->exists()) {
                    \Illuminate\Support\Facades\Log::warning("EnsureUserHasPasskey: Seller core user has NO passkeys. Forced logout.", [
                        'seller_id' => $user->id,
                    ]);
                    Auth::logout();
                    return redirect('/cabinet/login');
                }
            } else {
                $hasPasskeys = $user->passkeys()->exists();
                \Illuminate\Support\Facades\Log::debug("EnsureUserHasPasskey: Standard User check", [
                    'has_passkeys' => $hasPasskeys,
                ]);

                if (!$hasPasskeys) {
                    \Illuminate\Support\Facades\Log::warning("EnsureUserHasPasskey: Standard User has NO passkeys. Deleting ghost record.", [
                        'user_id' => $user->id,
                        'sl1e' => $user->sovereignIdentityAddress(),
                    ]);
                    Auth::logout();
                    // 🧼 Clean up the ghost B2C user record so it doesn't pollute our database
                    $user->delete();
                    return redirect('/cabinet/login');
                }
            }
        }

        return $next($request);
    }
}
