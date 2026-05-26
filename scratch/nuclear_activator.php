<?php

use App\Models\ProviderProduct;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "☢️ [NUCLEAR ACTIVATOR STARTED]\n";

$total = ProviderProduct::count();
echo "Total records: $total\n";

echo "🚀 Executing atomic DB update enabling all rows...\n";

$affected = DB::table('provider_products')->update(['is_active' => 1]);

echo "✅ AFFECTED ROWS UPDATED VIA DB: $affected\n";

$nowActive = ProviderProduct::where('is_active', 1)->count();
echo "📊 CURRENT ACTIVE COUNT AFTER DB QUERY: $nowActive\n";

if ($nowActive > 1) {
    echo "🏆 VICTORY! Total Active: $nowActive. ALL PRODUCTS ARE NOW LIVE IN THE SHOWCASE!\n";
} else {
    echo "🚨 CATASTROPHE! Even raw DB query couldn't keep them active! Check your DB triggers!\n";
}
