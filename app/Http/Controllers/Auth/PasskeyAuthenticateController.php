<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Spatie\LaravelPasskeys\Http\Controllers\AuthenticateUsingPasskeyController as BaseController;
use Spatie\LaravelPasskeys\Support\Config;

class PasskeyAuthenticateController extends BaseController
{
    protected function validPasskeyResponse(Request $request): RedirectResponse
    {
        $user = auth()->user();

        if ($user) {
            // God Mode / Admin Panel
            if ($user->hasRole('super_admin') || $user->hasAnyRole(['manager', 'executor', 'support'])) {
                return redirect()->intended('/ops');
            }

            // B2B Consortium Partner
            if ($user->isB2BPartner()) {
                return redirect('/partner');
            }

            // Treasurer
            if ($user->hasRole('treasurer')) {
                return redirect()->intended('/treasury');
            }

            // Auditor
            if ($user->hasRole('auditor')) {
                return redirect()->intended('/tribunal');
            }

            // System Engineer / Telemetry
            if ($user->hasAnyRole(['telemetry_monitor', 'system_engineer'])) {
                return redirect()->intended('/kernel');
            }
        }

        $url = Session::has('passkeys.redirect')
            ? Session::pull('passkeys.redirect')
            : Config::getRedirectAfterLogin();

        return redirect($url);
    }
}
