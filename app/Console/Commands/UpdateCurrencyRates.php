<?php

namespace App\Console\Commands;

use App\Models\Currency;
use App\Http\Services\BinanceService;
use Illuminate\Console\Command;

class UpdateCurrencyRates extends Command
{
    protected $signature = 'app:update-currency-rates';
    protected $description = 'Fetch latest exchange rates from Binance and update currencies table';

    public function handle()
    {
        $binance = new BinanceService();
        $this->info("Fetching rates from Binance...");

        $usdtRub = (float)$binance->tickerPrice('USDTRUB');
        $usdtTry = (float)$binance->tickerPrice('USDTTRY');

        if (!$usdtRub) {
            $this->error("Could not fetch USDTRUB rate!");
            return;
        }

        $currencies = Currency::where('is_auto_update', true)->get();

        foreach ($currencies as $currency) {
            $code = $currency->code;
            $rate = 0;
            
            if ($code === 'RUB') {
                $rate = 1.0;
            } elseif ($code === 'USD') {
                $rate = $usdtRub;
            } elseif ($code === 'TRY') {
                $rate = $usdtTry > 0 ? ($usdtRub / $usdtTry) : 0;
            } else {
                // Generic lookup for other currencies (EUR, GBP, AUD, etc.)
                // Fetch {CODE}USDT and multiply by USDTRUB
                $pairPrice = (float)$binance->tickerPrice($code . 'USDT');
                if ($pairPrice > 0) {
                    $rate = $pairPrice * $usdtRub;
                } else {
                    // Try reverse pair if needed? (USDT{CODE}) - rarely needed for fiat
                    // Or try {CODE}BTC -> BTCRUB? (Too complex for now)
                }
            }

            if ($rate > 0) {
                $currency->update(['rate_to_rub' => $rate]);
                $this->info("Updated {$code}: " . round($rate, 4));
            } else {
                $this->warn("Could not update rate for {$code}. Check if Binance has {$code}USDT pair.");
            }
        }

        $this->info("Currency rates update completed!");
    }
}
