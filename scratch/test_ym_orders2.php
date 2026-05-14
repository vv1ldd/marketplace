<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$shop = App\Models\Shop::first();
$service = new App\Http\Services\YmService($shop);
$orders = $service->getOrders([]);
$counts = [];
foreach ($orders as $order) {
    $key = $order['status'].' / '.($order['substatus'] ?? 'null');
    $counts[$key] = ($counts[$key] ?? 0) + 1;
}
print_r($counts);
