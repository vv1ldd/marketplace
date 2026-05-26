<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$provider = \App\Models\Provider::where('type', 'wildflow')->first();
if ($provider) {
    echo "Provider Found: " . $provider->name . "\n";
    echo "Credentials keys: " . implode(', ', array_keys($provider->credentials ?? [])) . "\n";
    echo "Credentials values: " . json_encode($provider->credentials) . "\n";
} else {
    echo "Provider wildflow not found.\n";
}
