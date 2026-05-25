<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class RestoreFinancialsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'financials:restore {filename} {--shop=10 : Shop ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Restore order financials from an SQL dump';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $filename = $this->argument('filename');
        $shopId = $this->option('shop');

        $fullPath = base_path($filename);
        $handle = @fopen($fullPath, 'r');
        if (!$handle) {
            $this->error("Could not open $fullPath");
            return 1;
        }

        $updated = 0;
        $totalFound = 0;

        $this->info("Starting restoration for Shop ID $shopId from $filename...");

        while (($line = fgets($handle)) !== false) {
            if (str_contains($line, "INSERT INTO `wp_wc_order_stats`")) {
                // Extract everything between VALUES and ;
                preg_match('/VALUES\s*(.*);/s', $line, $matches);
                if (isset($matches[1])) {
                    $valuesString = $matches[1];
                    // Split by ),( but be careful about commas inside strings
                    preg_match_all('/\(([^)]+)\)/', $valuesString, $rows);
                    
                    foreach ($rows[1] as $row) {
                        $parts = str_getcsv($row, ',', "'");
                        if (count($parts) >= 6) {
                            $orderId = trim($parts[0]);
                            $totalAmount = (float)trim($parts[5]);
                            
                            // Update the order in our DB
                            $affected = \App\Models\Order\Order::where('shop_id', $shopId)
                                ->where('order_id', $orderId)
                                ->update([
                                    'total_amount' => $totalAmount,
                                    'total_amount_base' => $totalAmount,
                                    'currency' => 'RUB' // 1Gros is likely RUB
                                ]);
                            
                            if ($affected) {
                                $updated++;
                            }
                            $totalFound++;
                        }
                    }
                }
            }
        }

        fclose($handle);

        $this->info("Restoration complete!");
        $this->line("Total stats found in SQL: $totalFound");
        $this->line("Orders updated in database: $updated");
        return 0;
    }
}
