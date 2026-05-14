<?php

use App\Models\Seller;
use App\Models\LegalEntity;
use Filament\Facades\Filament;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$seller = Seller::first(); // Assuming first seller for test
if (!$seller) {
    echo "No seller found\n";
    exit;
}

echo "Seller: " . $seller->email . " (ID: " . $seller->id . ")\n";

$panel = Filament::getPanel('partner');
$tenants = $seller->getTenants($panel);

echo "Tenants count: " . $tenants->count() . "\n";
foreach ($tenants as $tenant) {
    echo " - Tenant ID: " . $tenant->id . ", Name: " . $tenant->name . "\n";
}
