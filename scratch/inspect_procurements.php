<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Procurement;

$count = Procurement::count();
echo "📦 Total procurements in DB: {$count}\n";

if ($count > 0) {
    $all = Procurement::with(['shop', 'product', 'warehouse'])->get();
    foreach ($all as $p) {
        echo "👉 ID #{$p->id} | Date: " . ($p->created_at ? $p->created_at->format('d.m.Y H:i') : 'NULL') . " | Product: " . ($p->product->name ?? 'None') . " | Warehouse: " . ($p->warehouse->name ?? 'None') . " | Count: {$p->count} | Price: " . ($p->price_per_item / 100) . " | Total: " . ($p->total_price / 100) . " | Status: {$p->status}\n";
    }
} else {
    echo "No procurements found in database.\n";
}
