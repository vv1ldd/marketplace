<?php

namespace App\Services\Taxation;

use Carbon\Carbon;

class RuTaxCalculator
{
    /**
     * Dynamic dynamic thresholds for VAT exemption on USN by calendar year.
     * Reference: FNS Directives 2025-2028.
     */
    private const THRESHOLDS = [
        2024 => 60_000_000, // Foundation base for 2025
        2025 => 60_000_000,
        2026 => 20_000_000, // Aggressive drop in 2026
        2027 => 15_000_000,
        2028 => 10_000_000,
    ];

    /**
     * Maximum allowed USN income before mandatory OSNO transition, accounting for 2026 deflator index.
     */
    private const MAX_USN_LIMIT_2026 = 490_500_000; 

    /**
     * Gets the legal standard VAT rate for a given specific date/year.
     * Before 2026: 20%, Starting 2026: 22%.
     */
    public function getStandardVatRate(?Carbon $date = null): float
    {
        $year = ($date ?? now())->year;
        
        return $year >= 2026 ? 22.0 : 20.0;
    }

    /**
     * Get the statutory income limit for VAT exemption for a given year.
     */
    public function getVatExemptionLimit(int $year): float
    {
        // Fallback to the lowest limit (10M) if future years exceed our current roadmap
        return self::THRESHOLDS[$year] ?? 10_000_000;
    }

    /**
     * Determines if the Legal Entity MUST be a VAT payer based on revenue tracking.
     * 
     * @param float $previousYearRevenue Income from previous calendar year
     * @param float $currentYearRevenue Year-to-date income from January 1st of current year
     * @return array Detailed diagnostic status
     */
    public function analyzeVatObligation(float $previousYearRevenue, float $currentYearRevenue, ?Carbon $date = null): array
    {
        $now = $date ?? now();
        $currentYear = $now->year;
        
        $limitPrevious = $this->getVatExemptionLimit($currentYear - 1);
        $limitCurrent = $this->getVatExemptionLimit($currentYear);

        // Case 1: Previous year limit violation makes you a payer from Jan 1st!
        if ($previousYearRevenue > $limitPrevious) {
            return [
                'must_pay_vat' => true,
                'reason' => "Превышен порог предыдущего ({$limitPrevious} руб.) года.",
                'effective_from' => "01.01.{$currentYear}",
                'recommended_special_vat' => 5.0 // Starter lower special rate
            ];
        }

        // Case 2: Mid-year acceleration violation makes you a payer starting NEXT MONTH!
        if ($currentYearRevenue > $limitCurrent) {
            $nextMonth = $now->copy()->addMonth()->startOfMonth()->format('d.m.Y');
            return [
                'must_pay_vat' => true,
                'reason' => "Превышен лимит текущего года ({$limitCurrent} руб.).",
                'effective_from' => $nextMonth,
                'recommended_special_vat' => 5.0
            ];
        }

        // Case 3: Total Safe Compliance
        return [
            'must_pay_vat' => false,
            'reason' => "Выручка в пределах лимита освобождения.",
            'effective_from' => null
        ];
    }
}
