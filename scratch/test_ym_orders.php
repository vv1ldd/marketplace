<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$shop = App\Models\Shop::first();
echo 'Shop ID: '.$shop->id.', Campaign ID: '.$shop->campaign_id."\n";
$service = new App\Http\Services\YmService($shop);
$orders = $service->getOrders(['status' => 'PROCESSING']);
echo 'Found PROCESSING: '.count($orders)."\n";
$orders = $service->getOrders([]);
echo 'Found ALL: '.count($orders)."\n";
if (count($orders) > 0) {
    echo 'First order status: '.$orders[0]['status'].' substatus: '.($orders[0]['substatus'] ?? 'null')."\n";
}
