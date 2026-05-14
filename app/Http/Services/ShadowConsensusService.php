<?php

namespace App\Http\Services;

use App\Models\Currency;
use App\Models\CurrencyTelemetry;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;

class ShadowConsensusService
{
    public function updateConsensus(string $code)
    {
        $currency = Currency::where('code', $code)->first();
        if (!$currency || !$currency->is_shadow) return;

        $telemetries = CurrencyTelemetry::where('currency_code', $code)
            ->where('observed_at', '>=', now()->subHours(48))
            ->get();

        $count = $telemetries->count();
        if ($count === 0) {
            $currency->update(['liquidity_stress_index' => 100, 'telemetry_count_48h' => 0]);
            return;
        }

        // 1. ANOMALY REJECTION (Filter outliers if enough data)
        $rates = $telemetries->pluck('rate')->sort()->values();
        if ($count >= 5) {
            $q1 = $rates[(int)($count * 0.25)];
            $q3 = $rates[(int)($count * 0.75)];
            $iqr = $q3 - $q1;
            $lowerBound = $q1 - 1.5 * $iqr;
            $upperBound = $q3 + 1.5 * $iqr;
            
            $telemetries = $telemetries->filter(fn($t) => $t->rate >= $lowerBound && $t->rate <= $upperBound);
        }

        // 2. WEIGHTED CONSENSUS
        $totalWeight = 0;
        $weightedSum = 0;

        foreach ($telemetries as $item) {
            $hoursOld = Carbon::parse($item->observed_at)->diffInHours(now());
            $timeWeight = max(0.1, 1 - ($hoursOld / 48));
            
            // Weight = Source Confidence * Time Decay * Reporter Reputation
            $finalWeight = $item->confidence * $timeWeight * ($item->reporter_reputation ?? 0.5);
            
            $weightedSum += ($item->rate * $finalWeight);
            $totalWeight += $finalWeight;
        }

        if ($totalWeight > 0) {
            $consensusRate = $weightedSum / $totalWeight;
            
            // 3. STRESS INDEX CALCULATION
            $variance = $this->calculateVariance($telemetries->pluck('rate')->toArray(), $consensusRate);
            $stress = (sqrt($variance) / $consensusRate) * 100;
            if ($count < 3) $stress += 50; // Penalty for low source density

            $currency->update([
                'manual_rate' => round($consensusRate, 4),
                'shadow_source' => 'Sovereign Index (' . $telemetries->count() . ' obs)',
                'telemetry_count_48h' => $telemetries->count(),
                'liquidity_stress_index' => min(100, round($stress, 2))
            ]);

            Artisan::call('app:update-currency-rates');
        }
    }

    protected function calculateVariance(array $data, float $mean): float
    {
        if (count($data) <= 1) return 0;
        $sum = 0;
        foreach ($data as $val) {
            $sum += pow($val - $mean, 2);
        }
        return $sum / count($data);
    }
}
