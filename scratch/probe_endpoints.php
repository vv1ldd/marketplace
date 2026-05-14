<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$token = config('app.wildflow_token') ?: env('APP_WILDFLOW_TOKEN');
$baseUrl = 'https://api.wildflow.dev/api/v1/';

$endpoints = [
    'partners/me',
    'partners/profile',
    'partners/terminals',
    'partners/accounts',
    'partners/balance',
    'terminals',
    'profile',
    'me'
];

foreach ($endpoints as $ep) {
    $response = Http::withHeaders([
        'Accept' => 'application/json',
        'X-Auth-Token' => $token,
    ])->timeout(10)
      ->withoutVerifying()
      ->get($baseUrl . $ep);
      
    echo "\nGET /$ep -> Status: " . $response->status() . "\n";
    if ($response->successful()) {
        echo "BODY: " . json_encode($response->json(), JSON_PRETTY_PRINT) . "\n";
    } else {
        echo "BODY: " . $response->body() . "\n";
    }
}
