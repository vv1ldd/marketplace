<?php

namespace App\Http\Responses;

use Filament\Auth\Http\Responses\Contracts\LoginResponse as Responsable;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

class LoginResponse implements Responsable
{
    public function toResponse($request): RedirectResponse | Redirector
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

        return redirect()->intended('/cabinet');
    }
}
