<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Filament\Facades\Filament;
use App\Models\Shop;

class SovereignContextMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // 🛡️ Skip verification for authentication routes and registration
        if ($request->is('*/login') || $request->is('*/register') || $request->is('passkeys*') || $request->is('logout*')) {
            return $next($request);
        }

        $user = auth()->user();

        // 🛡️ ZERO-TRUST ENFORCEMENT: The Sovereign Mandate
        // Even if authenticated (e.g. by an admin tool), we require a fresh Passkey anchor in this session.
        if ($user && !app()->runningInConsole()) {
            $mandateId = session('sovereign_mandate_id');
            $mandateHash = session('sovereign_mandate_hash');

            if (!$mandateId || !$mandateHash) {
                // 🛑 UNAUTHORIZED ACCESS: Identity without physical proof
                \Illuminate\Support\Facades\Log::warning("UNAUTHORIZED_ACCESS: Identity without signed intent detected.", [
                    'user_id' => $user->id,
                    'ip' => $request->ip(),
                    'path' => $request->path()
                ]);
                
                auth()->logout();
                session()->flush();
                
                return redirect()->route('filament.partner.auth.login')->withErrors(['error' => 'Ваша сессия не подтверждена подписанным интентом. Вход без криптографического якоря запрещен.']);
            }

            // 💎 Deterministic Verification of the Intent
            $ledgerEntry = \App\Models\SovereignLedger::find($mandateId);
            if (!$ledgerEntry || $ledgerEntry->fingerprint !== $mandateHash) {
                auth()->logout();
                session()->flush();
                return redirect()->route('filament.partner.auth.login')->withErrors(['error' => 'Нарушена целостность мандата. Идеальный хэш не прошел проверку детерминизма.']);
            }
        }

        // Redirect any standard Filament partner panel access directly to the custom themed SPA B2B dashboard
        if (Filament::getCurrentPanel()?->getId() === 'partner') {
            return redirect()->route('partner.dashboard');
        }

        return $next($request);
    }
}
