<?php

require __DIR__ . '/../../../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../../../bootstrap/app.php';

use App\Models\Currency;

$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$currencies = Currency::all();
echo json_encode($currencies, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
