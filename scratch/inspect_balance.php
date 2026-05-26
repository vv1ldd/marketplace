<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\LegalEntity;

$le = LegalEntity::where('seller_id', 1)->first() ?? LegalEntity::first();
if ($le) {
    echo "🏛️ Legal Entity: {$le->name} (ID: {$le->id})\n";
    echo "   💰 Balance (raw): {$le->balance}\n";
    echo "   💰 Available Balance (raw): {$le->available_balance}\n";
    echo "   💰 Reserved Balance (raw): {$le->reserved_balance}\n";
} else {
    echo "No legal entity found!\n";
}
