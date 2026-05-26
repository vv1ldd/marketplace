<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Warehouse;

$count = Warehouse::count();
echo "📦 Total warehouses in DB: {$count}\n";

if ($count > 0) {
    $all = Warehouse::with('shop')->get();
    foreach ($all as $w) {
        echo "👉 ID #{$w->id} | Name: {$w->name} | Shop: " . ($w->shop->name ?? 'None') . " | Is Main: " . ($w->is_main ? 'YES' : 'NO') . " | Channel: " . ($w->channel ?? 'NULL') . " | Active: " . ($w->is_active ? 'YES' : 'NO') . " | YM ID: " . ($w->ym_id ?? 'NULL') . "\n";
    }
} else {
    echo "No warehouses found in database.\n";
}
