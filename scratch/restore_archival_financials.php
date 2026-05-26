<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Archival Financial Restoration (v3) ===\n\n";

// ============================================================
// STRATEGY:
//
// PS Store (shop_id=9):
//   - Zero-sum orders have small IDs (< ~10000), imported from
//     wp_postmeta (legacy WC storage). order_id = wp_post_id.
//   - Parse wp_postmeta for '_order_total' and '_order_currency'.
//   - Match: orders.order_id = postmeta.post_id
//
// 1Gros (shop_id=10):
//   - Parse gros_stats_clean.tmp (wp_wc_order_stats rows).
//   - 431 records → match on order_id.
//   - Previous runs may have caught some; we re-run safely
//     (WHERE total_amount_base = 0 guard prevents double-update).
// ============================================================

// ---------------------------------------------------------------
// STEP 1: Build PS Store financial map from wp_postmeta
// ---------------------------------------------------------------
echo "Step 1: Parsing PS Store postmeta (_order_total / _order_currency)...\n";

$psMap = []; // [post_id => ['total' => float, 'currency' => string]]

$file = 'ps-store-prod-2.sql';
$handle = fopen($file, 'r');
if (!$handle) die("Cannot open $file\n");

$lineNo = 0;
while (($line = fgets($handle)) !== false) {
    $lineNo++;
    if (!str_contains($line, "INSERT INTO `wp_postmeta` VALUES")) continue;

    // Use regex to extract all (meta_id, post_id, 'key', 'value') tuples
    // Pattern: (digits,digits,'key','value') — value may be numeric string
    preg_match_all(
        '/\(\d+,(\d+),\'(_order_total|_order_currency)\',\'([^\']*)\'\)/',
        $line,
        $matches,
        PREG_SET_ORDER
    );

    foreach ($matches as $m) {
        $postId = $m[1];
        $key    = $m[2];
        $val    = $m[3];

        if ($key === '_order_total') {
            $psMap[$postId]['total'] = (float) $val;
        } elseif ($key === '_order_currency') {
            $psMap[$postId]['currency'] = $val;
        }
    }
}
fclose($handle);

$validPs = array_filter($psMap, fn($d) => isset($d['total']) && $d['total'] > 0);
echo "  Found " . count($psMap) . " postmeta entries, " . count($validPs) . " with positive totals.\n";

// ---------------------------------------------------------------
// STEP 2: Update PS Store zero-sum orders
// ---------------------------------------------------------------
echo "Step 2: Updating PS Store (shop_id=9)...\n";

$psUpdated = 0;
foreach ($validPs as $postId => $data) {
    $currency = $data['currency'] ?? 'RUB';
    $affected = DB::table('orders')
        ->where('shop_id', 9)
        ->where('order_id', $postId)
        ->where('total_amount_base', 0)
        ->update([
            'total_amount'      => $data['total'],
            'total_amount_base' => $data['total'],
            'currency'          => $currency,
        ]);
    if ($affected) $psUpdated++;
}
echo "  Updated: $psUpdated orders.\n\n";

// ---------------------------------------------------------------
// STEP 3: Parse 1Gros from gros_stats_clean.tmp
// ---------------------------------------------------------------
// Schema for wp_wc_order_stats:
// (order_id, parent_id, date_created, date_created_gmt,
//  num_items_sold, total_sales, tax_total, shipping_total,
//  net_total, returning_customer, status, customer_id, date_paid, date_completed)
echo "Step 3: Parsing 1Gros stats (wp_wc_order_stats)...\n";

$grosMap = []; // [order_id => float]

$statsFile = 'gros_stats_clean.tmp';
$sh = fopen($statsFile, 'r');
if (!$sh) die("Cannot open $statsFile\n");

while (($line = fgets($sh)) !== false) {
    $line = trim($line);
    if (empty($line)) continue;

    // Each line is either the full INSERT or just a values row like (id,0,'date',...)\n
    // Strip INSERT header if present
    if (str_contains($line, 'INSERT INTO')) {
        $pos = strpos($line, 'VALUES');
        if ($pos !== false) $line = substr($line, $pos + 6);
    }

    // Remove trailing semicolon
    $line = rtrim($line, ';');

    // Match individual rows: (digits, ...)
    preg_match_all('/\((\d+),\d+,\'[^\']+\',\'[^\']+\',\d+,([\d.]+),/', $line, $m, PREG_SET_ORDER);
    foreach ($m as $match) {
        $orderId    = $match[1];
        $totalSales = (float) $match[2];
        $grosMap[$orderId] = $totalSales;
    }
}
fclose($sh);
echo "  Parsed " . count($grosMap) . " records.\n";

// ---------------------------------------------------------------
// STEP 4: Update 1Gros zero-sum orders
// ---------------------------------------------------------------
echo "Step 4: Updating 1Gros (shop_id=10)...\n";

$grosUpdated = 0;
foreach ($grosMap as $orderId => $total) {
    if ($total <= 0) continue;
    $affected = DB::table('orders')
        ->where('shop_id', 10)
        ->where('order_id', $orderId)
        ->where('total_amount_base', 0)
        ->update([
            'total_amount'      => $total,
            'total_amount_base' => $total,
            'currency'          => 'RUB',
        ]);
    if ($affected) $grosUpdated++;
}
echo "  Updated: $grosUpdated orders.\n\n";

// ---------------------------------------------------------------
// FINAL AUDIT
// ---------------------------------------------------------------
echo "=== Final Audit ===\n";

foreach ([9 => 'PS Store', 10 => '1Gros'] as $shopId => $name) {
    $ok   = DB::table('orders')->where('shop_id', $shopId)->where('total_amount_base', '>', 0)->count();
    $zero = DB::table('orders')->where('shop_id', $shopId)->where('total_amount_base', 0)->count();
    $sum  = DB::table('orders')->where('shop_id', $shopId)->sum('total_amount_base');
    echo sprintf(
        "%s (%d):  OK=%-5d | Zero=%-5d | Total Revenue = %s RUB\n",
        $name, $shopId, $ok, $zero, number_format($sum, 2, '.', ' ')
    );
}

echo "\nDone!\n";
