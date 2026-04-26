<?php

namespace App\Services;

use App\Http\Services\BinanceService;

class FinanceService
{
    protected array $rates = [];

    public function __construct()
    {
        $binance = new BinanceService();
        $this->rates['USDTRUB'] = (float)$binance->tickerPrice('USDTRUB');
        $this->rates['USDTTRY'] = (float)$binance->tickerPrice('USDTTRY');
        
        // Add others if needed
        $this->rates['EURRUB'] = $this->rates['USDTRUB'] * 1.08; // Placeholder or fetch actual
        $this->rates['GBPRUB'] = $this->rates['USDTRUB'] * 1.25; // Placeholder
    }

    public function convertToRub(float $amount, string $currency, float $taxPercent = 0): float
    {
        $rate = 1.0;
        
        if ($currency === 'TRY') {
            $rate = $this->rates['USDTRUB'] / $this->rates['USDTTRY'];
        } elseif ($currency === 'USD') {
            $rate = $this->rates['USDTRUB'];
        } elseif ($currency === 'EUR') {
            $rate = $this->rates['EURRUB'];
        } elseif ($currency === 'GBP') {
            $rate = $this->rates['GBPRUB'];
        }

        return round($amount * $rate * (1 + $taxPercent / 100));
    }

    public function getRate(string $currency): float
    {
        if ($currency === 'TRY') return $this->rates['USDTRUB'] / $this->rates['USDTTRY'];
        if ($currency === 'USD') return $this->rates['USDTRUB'];
        if ($currency === 'EUR') return $this->rates['EURRUB'] ?? $this->rates['USDTRUB'] * 1.08;
        return $this->rates['USDTRUB']; // Fallback
    }
}
