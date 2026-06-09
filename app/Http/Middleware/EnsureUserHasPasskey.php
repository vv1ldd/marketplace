<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureUserHasPasskey
{
    public function handle(Request $request, Closure $next)
    {
        // Registration and identity popup flows establish SL1E identity before protected areas.
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
                $b2bUrl = '/merchant';
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
                    'core_user_has_identity' => $coreUser instanceof \App\Models\User && $coreUser->hasSovereignIdentity(),
                ]);

                if (!$coreUser || !($coreUser instanceof \App\Models\User && $coreUser->hasSovereignIdentity())) {
                    \Illuminate\Support\Facades\Log::warning("EnsureUserHasPasskey: Seller core user has no SL1E identity. Forced logout.", [
                        'seller_id' => $user->id,
                    ]);
                    Auth::logout();
                    return redirect()->route('login');
                }
            } else {
                $hasPasskeys = $user->passkeys()->exists();
                $identityRule = data_get($user->meta, 'simple_l1.identity_rule');
                $hasExternalIdentityProvider = $user instanceof \App\Models\User
                    && $user->sovereignIdentityAddress() !== null
                    && (
                        $user->identity_provider === 'identity_wildflow'
                        || in_array($identityRule, ['external_identity_provider', 'entity_with_registered_keys'], true)
                    );
                \Illuminate\Support\Facades\Log::debug("EnsureUserHasPasskey: Standard User check", [
                    'has_passkeys' => $hasPasskeys,
                    'has_external_identity_provider' => $hasExternalIdentityProvider,
                ]);

                if (!$hasPasskeys && ! $hasExternalIdentityProvider) {
                    \Illuminate\Support\Facades\Log::warning("EnsureUserHasPasskey: Standard User has no local passkey or SL1E identity. Forced logout.", [
                        'user_id' => $user->id,
                        'sl1e' => $user->sovereignIdentityAddress(),
                    ]);
                    Auth::logout();
                    return redirect()->route('login');
                }
            }
        }

        return $next($request);
    }
}
