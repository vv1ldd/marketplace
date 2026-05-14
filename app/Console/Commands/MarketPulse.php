<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class MarketPulse extends Command
{
    protected $signature = 'app:market-pulse {symbol=BTCUSDT} {--seconds=15}';
    protected $description = 'Demonstrate real-time market power (pseudo-stream)';

    public function handle()
    {
        $symbol = strtoupper($this->argument('symbol'));
        $seconds = (int)$this->option('seconds');
        $this->info("--- MARKET PULSE ACTIVATED: {$symbol} ---");
        $this->warn("Streaming for {$seconds} seconds... Watch the pulse!");
        
        $lastPrice = 0;
        $startTime = time();

        while (time() - $startTime < $seconds) {
            try {
                $response = Http::timeout(2)
                    ->withoutVerifying()
                    ->get("https://api.bybit.com/v5/market/tickers", [
                        'category' => 'spot',
                        'symbol' => $symbol
                    ]);

                $price = (float)($response->json('result.list.0.lastPrice') ?? 0);
                
                if ($price !== $lastPrice) {
                    $diff = $price - $lastPrice;
                    $color = $diff >= 0 ? 'info' : 'error';
                    $sign = $diff >= 0 ? '▲' : '▼';
                    
                    $this->line(sprintf(
                        "[%s] %s %-10s | Price: <$color>%12.4f</$color> | Change: %+.4f",
                        now()->format('H:i:s.v'),
                        $sign,
                        $symbol,
                        $price,
                        $diff
                    ));
                    
                    $lastPrice = $price;
                }
                
                // Sleep for 500ms (0.5 seconds) to simulate High-Frequency
                usleep(500000);
                
            } catch (\Exception $e) {
                // $this->error("Connection heartbeat lost: " . $e->getMessage());
                usleep(1000000);
            }
        }
    }
}
