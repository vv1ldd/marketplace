<?php

use App\Models\Order\Order;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$filename = '1gros-prod-2.sql';
$handle = fopen($filename, 'r');
if (!$handle) {
    die("Could not open $filename\n");
}

$shopId = 10;
$updated = 0;
$totalFound = 0;

echo "Starting restoration for Shop ID $shopId...\n";

while (($line = fgets($handle)) !== false) {
    if (str_contains($line, "INSERT INTO `wp_wc_order_stats`")) {
        // Extract everything between VALUES and ;
        preg_match('/VALUES\s*(.*);/s', $line, $matches);
        if (isset($matches[1])) {
            $valuesString = $matches[1];
            // Split by ),( but be careful about commas inside strings
            // For wc_order_stats, we don't have many strings, mostly numbers and dates
            // Pattern for (id, parent, date, date, items, total, ...)
            preg_match_all('/\(([^)]+)\)/', $valuesString, $rows);
            
            foreach ($rows[1] as $row) {
                $parts = str_getcsv($row, ',', "'");
                if (count($parts) >= 6) {
                    $orderId = trim($parts[0]);
                    $totalAmount = (float)trim($parts[5]);
                    
                    // Update the order in our DB
                    $affected = Order::where('shop_id', $shopId)
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

echo "Restoration complete!\n";
echo "Total stats found in SQL: $totalFound\n";
echo "Orders updated in database: $updated\n";
