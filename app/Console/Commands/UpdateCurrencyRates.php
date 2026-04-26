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
            $rate = 0;
            
            if ($currency->code === 'USD') {
                $rate = $usdtRub;
            } elseif ($currency->code === 'TRY') {
                $rate = $usdtTry > 0 ? ($usdtRub / $usdtTry) : 0;
            } elseif ($currency->code === 'EUR') {
                // Binance doesn't always have direct EURRUB or EURUSDT with RUB 
                // We'll use a standard EURUSD cross rate from Binance if available or proxy
                $eurUsdt = (float)$binance->tickerPrice('EURUSDT');
                $rate = $eurUsdt * $usdtRub;
            } elseif ($currency->code === 'GBP') {
                $gbpUsdt = (float)$binance->tickerPrice('GBPUSDT');
                $rate = $gbpUsdt * $usdtRub;
            }

            if ($rate > 0) {
                $currency->update(['rate_to_rub' => $rate]);
                $this->info("Updated {$currency->code}: " . round($rate, 4));
            } else {
                $this->warn("Could not update rate for {$currency->code}");
            }
        }

        $this->info("Currency rates update completed!");
    }
}
