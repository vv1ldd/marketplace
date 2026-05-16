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

        // Only target the Partner panel for automatic context switching
        if (Filament::getCurrentPanel()?->getId() === 'partner') {
            $user = auth()->user();
            
            // Assuming the seller has at least one shop linked
            // We use the first active shop to determine the region
            $shop = $user?->shops()->where('is_active', true)->first();
            
            if ($shop) {
                $region = strtoupper(trim($shop->shop_region ?? 'RU'));
                
                $currencyBase = match ($region) {
                    'TR', 'TK' => 'TRY',
                    'ES'       => 'EUR',
                    'GE'       => 'GEL',
                    'US'       => 'USD',
                    default    => 'RUB',
                };
                
                // Set the default base if it's not already manually overridden in this session
                if (!session()->has('currency_base')) {
                    session(['currency_base' => $currencyBase]);
                    
                    // Also set a default pair for the region to make it "live" immediately
                    $defaultTarget = ($currencyBase === 'RUB') ? 'USD' : 'RUB';
                    session(['currency_target' => $defaultTarget]);
                }
            }
        }

        return $next($request);
    }
}
