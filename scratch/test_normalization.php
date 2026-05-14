<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\MappingService;

$testCases = [
    'Steam UAE',
    'Steam Bahrain',
    'Sephora France',
    'Xbox Game Pass ultimate UAE',
    'iTunes US',
    'Google Play KSA',
];

foreach ($testCases as $case) {
    $master = MappingService::normalizeBrandName($case);
    echo "Case: '$case' -> Master: '" . ($master ?? 'NULL') . "'\n";
}
