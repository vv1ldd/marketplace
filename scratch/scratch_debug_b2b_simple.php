<?php

require __DIR__ . '/vendor/autoload.php';

// Mock config if needed
if (!function_exists('config')) {
    function config($key) { return null; }
}
if (!function_exists('env')) {
    function env($key) { 
        if ($key === 'DADATA_TOKEN') return 'dafe6dfb2c8752056b1c85a1b4714db3d5f8602e';
        return null; 
    }
}

use Meanly\SimpleL1\B2B\BusinessRegistrationManager;

$manager = new BusinessRegistrationManager();
$inn = '526216895584'; // ИНН со скриншота
$result = $manager->searchAndAnchor($inn, 'test_address');

print_r($result);
