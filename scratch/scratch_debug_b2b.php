<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(Illuminate\Http\Request::capture());

use Meanly\SimpleL1\B2B\BusinessRegistrationManager;

$manager = new BusinessRegistrationManager();
$inn = '526216895584'; // ИНН со скриншота
$result = $manager->searchAndAnchor($inn, 'test_address');

print_r($result);
