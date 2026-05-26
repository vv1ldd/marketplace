<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use ParagonIE\ConstantTime\Base64;

try {
    $res = Base64::decode('20260');
    echo 'Base64::decode 20260 success: ' . var_export($res, true) . PHP_EOL;
} catch (\Throwable $e) {
    echo 'Base64::decode 20260 failed: ' . $e->getMessage() . PHP_EOL;
}
