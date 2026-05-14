<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

$token = config('app.wildflow_token');
$baseUrl = 'https://api.wildflow.dev/api/v1/';

$client = Http::withHeaders([
    'Accept' => 'application/json',
    'X-Auth-Token' => $token,
])->withoutVerifying()->baseUrl($baseUrl);

$sku = 7202; // Numeric SKU is the one passing preliminary validation
$uuid = (string) Str::uuid();

$payload = [
    'sku' => $sku,
    'price' => 1.0,
    'buying_price' => 1.0,
    'quantity' => 1,
    'preOrder' => true,
    'referenceCode' => $uuid,
    'deliveryType' => 1,
    'destination' => 'diag@example.com',
    'terminal_pin' => '1029',
    'terminal_id' => 9937
];

echo "\n--- Testing Valid UUID + Numeric SKU 7202 ---\n";
echo "Payload UUID: $uuid\n";

try {
    $response = $client->post('codes/create-order', $payload);
    echo "Status Code: " . $response->status() . "\n";
    echo "Body: " . $response->body() . "\n";
} catch (\Exception $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
}
