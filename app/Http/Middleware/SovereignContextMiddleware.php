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
