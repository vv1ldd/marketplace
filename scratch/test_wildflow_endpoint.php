<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$token = config('app.wildflow_token') ?: env('APP_WILDFLOW_TOKEN');
echo "Token loaded: " . substr($token, 0, 3) . "...\n";

$baseUrl = 'https://api.wildflow.dev/api/v1/';

// Let's find a low-price SKU. 
// From Tinker output: 'wE1ETnnqsGiQPfl4' has product SKU 21, price 1. 
// inner data structure from tinker was:
// data.product.sku = 21
// data.service_sku = 0002100001000
// data.sku = wE1ETnnqsGiQPfl4

$tests = [
    'Case A: Alphanumeric SKU' => [
        'sku' => 'wE1ETnnqsGiQPfl4',
    ],
    'Case B: Inner data.product.sku (Numeric)' => [
        'sku' => 21,
    ],
    'Case C: Inner data.service_sku (Long Numeric String)' => [
        'sku' => '0002100001000',
    ]
];

foreach ($tests as $name => $payloadData) {
    echo "\n----------------------------------------\n";
    echo "Testing: $name\n";
    
    $payload = [
        'sku' => $payloadData['sku'],
        'price' => 1,
        'buying_price' => 0.92,
        'quantity' => 1,
        'preOrder' => true,
        'referenceCode' => (string) \Illuminate\Support\Str::uuid(),
        'deliveryType' => 1,
        'destination' => 'sataniyazow@gmail.com',
        'terminal_pin' => '1029',
        'terminal_id' => 9937
    ];
    
    echo "Sending SKU: " . gettype($payload['sku']) . " (" . $payload['sku'] . ")\n";
    
    $response = Http::withHeaders([
        'Accept' => 'application/json',
        'X-Auth-Token' => $token,
    ])->timeout(10)
      ->withoutVerifying()
      ->post($baseUrl . 'codes/create-order', $payload);
      
    echo "Status: " . $response->status() . "\n";
    echo "Body: " . $response->body() . "\n";
}
