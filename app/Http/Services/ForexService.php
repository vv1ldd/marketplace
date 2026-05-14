<?php

namespace App\Http\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class ForexService
{
    /**
     * Fetch institutional spot rates from global forex aggregators.
     * Uses Frankfurter (free, open source) or similar.
     */
    public function getSpotRates(string $base = 'USD'): array
    {
        return Cache::remember('forex_spot_rates_' . $base, 3600, function () use ($base) {
            try {
                $response = Http::timeout(10)->get("https://api.frankfurter.app/latest", [
                    'from' => $base,
                ]);

                if ($response->successful()) {
                    return $response->json()['rates'] ?? [];
                }
            } catch (\Exception $e) {
                \Log::error("Forex Spot Fetch Error: " . $e->getMessage());
            }

            return [];
        });
    }

    /**
     * Get a specific spot rate for a currency.
     */
    public function getRate(string $code, string $base = 'USD'): float
    {
        $rates = $this->getSpotRates($base);
        return (float) ($rates[$code] ?? 0);
    }
}
