<?php

namespace App\Services;

use App\Http\Services\BinanceService;

class FinanceService
{
    protected array $cachedRates = [];

    public function convertToRub(float $amount, string $currency, float $taxPercent = 0): float
    {
        $rate = $this->getRate($currency);
        return round($amount * $rate * (1 + $taxPercent / 100));
    }

    public function getRate(string $currencyCode): float
    {
        if (isset($this->cachedRates[$currencyCode])) {
            return $this->cachedRates[$currencyCode];
        }

        $currency = \App\Models\Currency::where('code', $currencyCode)->first();

        if (!$currency) {
            // Auto-create missing currency with default 1.0 rate
            $currency = \App\Models\Currency::create([
                'code' => $currencyCode,
                'name' => $currencyCode,
                'rate_to_rub' => 1.0,
                'is_auto_update' => true
            ]);
        }

        $rate = (float)($currency->manual_rate ?? $currency->rate_to_rub ?? 1.0);
        $this->cachedRates[$currencyCode] = $rate;

        return $rate;
    }
}
