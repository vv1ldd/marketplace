<?php

namespace App\Services;

use App\Http\Services\BybitService;

class FinanceService
{
    protected array $cachedRates = [];

    public function convertToRub(float $amount, string $currency, float $taxPercent = 0): float
    {
        $rate = $this->getRate($currency);
        return $amount * $rate * (1 + $taxPercent / 100);
    }

    /**
     * Convert any amount from one currency to another using backend rates.
     */
    public function convert(float $amount, string $from, string $to): float
    {
        if ($from === $to) {
            return $amount;
        }

        // Strategy: Convert FROM to RUB, then RUB to TO.
        $fromRate = $this->getRate($from);
        $toRate   = $this->getRate($to);

        if ($toRate <= 0) return 0;

        $amountInRub = $amount * $fromRate;
        return $amountInRub / $toRate;
    }

    public function getRate(string $currencyCode): float
    {
        if ($currencyCode === 'RUB') {
            return 1.0;
        }

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

        // --- LAZY UPDATE TRIGGER ---
        // If auto-update is enabled, manual_rate is not set, and the rate is older than 60 minutes
        if ($currency->is_auto_update && !$currency->manual_rate) {
            $isStale = !$currency->updated_at || $currency->updated_at->diffInMinutes(now()) > 60;
            
            if ($isStale) {
                $this->forceUpdateCurrencyRate($currency);
            }
        }

        $rate = (float)($currency->manual_rate ?? $currency->rate_to_rub ?? 1.0);
        $this->cachedRates[$currencyCode] = $rate;

        return $rate;
    }

    /**
     * Fetches the latest rate via Binance and updates the model.
     * Includes a robust fallback to Global Fiat API if P2P is unavailable.
     */
    public function forceUpdateCurrencyRate(\App\Models\Currency $currency): void
    {
        try {
            $bybit = new BybitService();
            
            // 1. Try to get RUB rate (Base for cross-rates)
            $rubForUsdt = $bybit->getP2PRate('RUB');
            
            // FALLBACK: If Binance blocked RUB (or API down), use Fiat API
            if ($rubForUsdt <= 0) {
                $rubForUsdt = $this->getFallbackRate('RUB');
            }

            // Special handling if the requested currency is USDT
            if ($currency->code === 'USDT') {
                if ($rubForUsdt > 0) {
                    $currency->update(['rate_to_rub' => $rubForUsdt]);
                }
                return;
            }

            // 2. Get rate for Target Currency
            $targetForUsdt = $bybit->getP2PRate($currency->code);
            
            if ($targetForUsdt <= 0) {
                $targetForUsdt = $this->getFallbackRate($currency->code, false);
            }
            
            // 3. Calculate Cross-Rate
            if ($rubForUsdt > 0 && $targetForUsdt > 0) {
                // How many RUB is 1 unit of target currency?
                $newRate = $rubForUsdt / $targetForUsdt;
                $currency->update(['rate_to_rub' => round($newRate, 4)]);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("Failed to auto-update rate for {$currency->code}: " . $e->getMessage());
        }
    }

    /**
     * Fallback to Official Fiat API with Market Spread.
     * Returns the cost of 1 USD in the requested Fiat.
     */
    protected function getFallbackRate(string $fiat, bool $applySpread = true): float
    {
        try {
            $response = \Illuminate\Support\Facades\Http::timeout(10)->get('https://open.er-api.com/v6/latest/USD');
            if ($response->failed()) return 0.0;
            
            $rate = (float)$response->json("rates.{$fiat}", 0.0);
            
            // Add market spread for RUB to simulate P2P reality (+6%)
            if ($rate > 0 && $fiat === 'RUB' && $applySpread) {
                $rate = $rate * 1.06;
            }
            
            return $rate;
        } catch (\Exception $e) {
            return 0.0;
        }
    }

    /**
     * Calculate final price for a specific shop.
     * Step 1: Use pre-calculated Product Ruble Price (price_rub).
     * Step 2: Apply Shop Markup (ym_tax or markup_percent).
     * Step 3: Enforce Minimum Price.
     *
     * Result in KOPEKS (integer).
     */
    public function getShopFinalPrice(\App\Models\Product $product, \App\Models\Shop $shop, ?float $overridePrice = null): int
    {
        // 1. Get base master price in RUB
        // If overridePrice is provided (for ranges), we use it. Otherwise use product's RUB price.
        $platformRetailRub = $overridePrice !== null 
            ? $overridePrice 
            : (float)($product->price_rub ?? 0) / 100;

        // 2. Apply Shop Markup percentage (Additive logic)
        // 2. Determine Base Price for markup
        // For Wildflow, we apply markup to our COST (purchase_price_rub).
        // For others, we use the default platform retail price.
        $basePriceRub = $platformRetailRub;
        
        $isWildflow = $product->provider_id && $product->provider?->type === 'wildflow';
        if ($isWildflow) {
            // Fetch dynamic purchase price in RUB using current backend rates
            $currency = \App\Models\Currency::where('code', $product->purchase_currency)->first();
            $rate = $currency?->effective_rate ?? 0;
            if ($rate <= 0 && $product->purchase_price > 0) {
                $rate = $product->price_rub / ($product->purchase_price * 100);
            }
            $basePriceRub = $product->getSellerPurchasePrice($shop) * $rate;
        }

        // 3. Apply Shop Markup percentage
        $baseMarkup = (float)($shop->markup_percent ?? 0);
        $extraMarkup = (float)($shop->ym_tax ?? 0);
        $boostMarkup = (float)($shop->ym_boost_percent ?? 0);
        
        $totalMarkup = $baseMarkup + $extraMarkup + $boostMarkup;
        
        $finalPriceRub = $basePriceRub * (1 + $totalMarkup / 100);

        // 3. Enforce Floor (ym_min_selling_price is in Rubles)
        if ($shop->ym_min_selling_price && $finalPriceRub < $shop->ym_min_selling_price) {
            $finalPriceRub = (float)$shop->ym_min_selling_price;
        }

        // Return in Kopeks
        return (int)round($finalPriceRub * 100);
    }
}
