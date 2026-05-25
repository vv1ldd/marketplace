<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SyncSovereignGuards
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $webCheck = Auth::guard('web')->check();
        $sellersCheck = Auth::guard('sellers')->check();

        \Illuminate\Support\Facades\Log::debug("SyncSovereignGuards: Entering middleware", [
            'host' => $request->getHost(),
            'path' => $request->path(),
            'web_logged_in' => $webCheck,
            'sellers_logged_in' => $sellersCheck,
            'session_id' => session()->getId(),
        ]);

        // 🛡️ CENTRALIZED SSO AUTHORITY: Redirect guests on consortium to main B2C login page
        if (!$webCheck && !$sellersCheck) {
            $shouldRedirect = !($request->is('partner/register*') ||
                                $request->is('business/register*') ||
                                $request->is('legal-entities/register*') ||
                                $request->is('register*') || 
                                $request->is('passkeys*') || 
                                $request->is('livewire*') || 
                                $request->is('filament*'));

            if ($shouldRedirect) {
                session(['redirect_to_b2b' => true]);
                $ssoUrl = 'https://meanly.test/cabinet/login';
                
                \Illuminate\Support\Facades\Log::info("SyncSovereignGuards: Guest detected on B2B console. Redirecting to Central SSO: {$ssoUrl}");
                return redirect($ssoUrl);
            }
        }

        // 🔐 Seamless Guard Synchronization: If logged into B2C web guard but not B2B sellers guard
        if ($webCheck && !$sellersCheck) {
            $user = Auth::guard('web')->user();
            \Illuminate\Support\Facades\Log::debug("SyncSovereignGuards: Web user found", [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            if ($user) {
                $seller = \App\Models\Seller::findByEmail($user->email);
                \Illuminate\Support\Facades\Log::debug("SyncSovereignGuards: Seller lookup", [
                    'seller_found' => !is_null($seller),
                ]);

                if ($seller) {
                    Auth::guard('sellers')->login($seller);
                    \Illuminate\Support\Facades\Log::info("SyncSovereignGuards: Successfully synchronized session to sellers guard", [
                        'seller_id' => $seller->id,
                    ]);
                }
            }
        }

        return $next($request);
    }
}
