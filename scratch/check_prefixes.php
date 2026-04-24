<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

use App\Models\Shop;

$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$shops = Shop::all(['id', 'name', 'domain', 'voucher_prefix']);

echo "ID | Name | Domain | Prefix\n";
echo str_repeat("-", 50) . "\n";
foreach ($shops as $shop) {
    echo "{$shop->id} | {$shop->name} | {$shop->domain} | '{$shop->voucher_prefix}'\n";
}
