<?php
/**
 * restore_ps_store_v4.php
 *
 * Targeted fix for PS Store (shop_id=9) zero-sum orders.
 *
 * Sources:
 *  - wp_wc_orders  → for IDs 7371–34411 (HPOS legacy)
 *  - wp_postmeta   → for IDs 7371–334026 (postmeta legacy)
 *
 * Strategy: build a [order_id => [total, currency]] map from BOTH sources,
 * then batch-update using raw SQL CASE WHEN for speed.
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== PS Store Financial Restoration (v4) ===\n\n";

$map = []; // [order_id (string) => ['total' => float, 'currency' => string]]

// -------------------------------------------------------
// SOURCE 1: wp_wc_orders (HPOS)
// Schema cols: 0=id, 1=status, 2=currency, 3=type, 4=tax_amount, 5=total_amount
// -------------------------------------------------------
echo "Parsing wp_wc_orders from ps-store-prod-2.sql...\n";
$handle = fopen('ps-store-prod-2.sql', 'r');
$wc_count = 0;
while (($line = fgets($handle)) !== false) {
    if (!str_contains($line, "INSERT INTO `wp_wc_orders` VALUES")) continue;

    // Quick extract with regex: (id,'status','currency','type',tax,total,...)
    preg_match_all(
        "/\((\d+),'([^']*?)','([^']*?)','([^']*?)',([\d.]+|NULL),([\d.]+|NULL)/",
        $line,
        $matches,
        PREG_SET_ORDER
    );
    foreach ($matches as $m) {
        $id       = $m[1];
        $status   = $m[2];
        $currency = $m[3];
        $type     = $m[4];
        $total    = (float) $m[6];

        if ($type !== 'shop_order') continue;
        if ($total <= 0) continue;

        $map[$id] = ['total' => $total, 'currency' => $currency ?: 'RUB'];
        $wc_count++;
    }
}
fclose($handle);
echo "  wp_wc_orders: $wc_count usable records.\n";

// -------------------------------------------------------
// SOURCE 2: wp_postmeta (_order_total / _order_currency)
// -------------------------------------------------------
echo "Parsing wp_postmeta from ps-store-prod-2.sql...\n";
$handle = fopen('ps-store-prod-2.sql', 'r');
$pm_count = 0;
while (($line = fgets($handle)) !== false) {
    if (!str_contains($line, "INSERT INTO `wp_postmeta` VALUES") &&
        !str_contains($line, "INSERT INTO `wp_postmeta_copy` VALUES")) continue;

    preg_match_all(
        "/\(\d+,(\d+),'(_order_total|_order_currency)','([^']*)'\)/",
        $line,
        $matches,
        PREG_SET_ORDER
    );
    foreach ($matches as $m) {
        $postId = $m[1];
        $key    = $m[2];
        $val    = $m[3];

        if ($key === '_order_total' && (float)$val > 0) {
            if (!isset($map[$postId])) {
                $map[$postId] = ['total' => (float)$val, 'currency' => 'RUB'];
                $pm_count++;
            }
            // Don't override HPOS data if already set
        } elseif ($key === '_order_currency' && isset($map[$postId])) {
            $map[$postId]['currency'] = $val;
        }
    }
}
fclose($handle);
echo "  wp_postmeta: $pm_count additional records.\n";
echo "  Total map size: " . count($map) . " entries.\n\n";

// -------------------------------------------------------
// Now batch-update zero-sum PS Store orders
// -------------------------------------------------------
echo "Updating PS Store (shop_id=9) zero-sum orders...\n";

// Get all zero-sum order_ids from DB
$zeroIds = DB::table('orders')
    ->where('shop_id', 9)
    ->where('total_amount_base', 0)
    ->pluck('order_id')
    ->toArray();

echo "  Zero-sum in DB: " . count($zeroIds) . "\n";

$updated    = 0;
$notInMap   = 0;
$batchSize  = 500;
$batches    = array_chunk($zeroIds, $batchSize);

foreach ($batches as $batch) {
    foreach ($batch as $orderId) {
        $orderId = (string) $orderId;
        if (!isset($map[$orderId])) {
            $notInMap++;
            continue;
        }
        $data = $map[$orderId];
        $affected = DB::table('orders')
            ->where('shop_id', 9)
            ->where('order_id', $orderId)
            ->where('total_amount_base', 0)
            ->update([
                'total_amount'      => $data['total'],
                'total_amount_base' => $data['total'],
                'currency'          => $data['currency'],
            ]);
        if ($affected) $updated++;
    }
}

echo "  Updated: $updated\n";
echo "  Not in map (no source data): $notInMap\n\n";

// -------------------------------------------------------
// Final Audit
// -------------------------------------------------------
echo "=== Audit ===\n";
$ok   = DB::table('orders')->where('shop_id', 9)->where('total_amount_base', '>', 0)->count();
$zero = DB::table('orders')->where('shop_id', 9)->where('total_amount_base', 0)->count();
$sum  = DB::table('orders')->where('shop_id', 9)->sum('total_amount_base');
echo sprintf(
    "PS Store (9):  OK=%-5d | Zero=%-5d | Revenue = %s RUB\n",
    $ok, $zero, number_format($sum, 2, '.', ' ')
);
echo "\nDone!\n";
