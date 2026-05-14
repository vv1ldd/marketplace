<?php

namespace App\Services;

use App\Models\Currency;

class SovereignCrossRateService
{
    /**
     * Get cross-rate between two currencies.
     * Result: How much of $to is needed for 1 unit of $from.
     */
    public function getRate(string $from, string $to, string $type = 'sovereign'): float
    {
        if ($from === $to) return 1.0;

        $fromCurrency = Currency::where('code', $from)->first();
        $toCurrency   = Currency::where('code', $to)->first();

        if (!$fromCurrency || !$toCurrency) return 0.0;

        if ($type === 'official') {
            $fromUsdt = (float)$fromCurrency->official_rate_usdt;
            $toUsdt   = (float)$toCurrency->official_rate_usdt;
        } else {
            // Sovereign (Shadow) rate using our best telemetry
            $fromUsdt = (float)$fromCurrency->rate_to_usdt;
            $toUsdt   = (float)$toCurrency->rate_to_usdt;
        }

        if ($fromUsdt <= 0 || $toUsdt <= 0) return 0.0;

        // Formula: Price of FROM in units of TO
        // (USDT per 1 To) / (USDT per 1 From) = How many TO for 1 FROM
        return $toUsdt / $fromUsdt;
    }

    /**
     * Get the Shadow Spread for a specific pair.
     * Shows how much more expensive the shadow cross-rate is compared to the official one.
     */
    public function getPairSpread(string $from, string $to): float
    {
        $official = $this->getRate($from, $to, 'official');
        $sovereign = $this->getRate($from, $to, 'sovereign');

        if ($official <= 0) return 0.0;

        return (($sovereign / $official) - 1) * 100;
    }

    /**
     * Detailed audit of a pair for the Dashboard.
     */
    public function auditPair(string $from, string $to): array
    {
        return [
            'from' => $from,
            'to' => $to,
            'official_rate' => round($this->getRate($from, $to, 'official'), 6),
            'sovereign_rate' => round($this->getRate($from, $to, 'sovereign'), 6),
            'spread_percent' => round($this->getPairSpread($from, $to), 2),
            'timestamp' => now()->toDateTimeString(),
        ];
    }
}
