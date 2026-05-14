<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\WildflowCatalog;
use App\Services\StandardizationService;

$standardizer = new StandardizationService();

// Let's take a popular product (e.g. Apple US)
$product = WildflowCatalog::first();

if (!$product) {
    $product = WildflowCatalog::first();
}

// Mocked raw response from Wildflow API (retail/cards)
$mockRawCards = [
    [
        'card_number' => 'ABC-123-XYZ-999',
        'pin_code' => '1234',
        'serial_number' => 'SN-888222111',
        'expiry_date' => '2026-12-31',
        'order_id' => 'WF-ORDER-456'
    ]
];

echo "RAW WILDFLOW RETAIL DATA (MOCKED):\n";
echo "----------------------------------\n";
print_r($mockRawCards);
echo "\n\n";

echo "MEANLY GOLDEN SCHEMA (RETAIL):\n";
echo "------------------------------\n";
$standardized = $standardizer->standardizeRetailCode($mockRawCards[0], $product);
echo json_encode($standardized, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
echo "\n";
