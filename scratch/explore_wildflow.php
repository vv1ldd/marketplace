<?php

require __DIR__ . '/../../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;

$token = config('app.wildflow_token');
$baseUrl = 'https://api.wildflow.dev/api/v1/';

$client = Http::withHeaders([
    'Accept' => 'application/json',
    'X-Auth-Token' => $token,
])->withoutVerifying()->baseUrl($baseUrl);

$endpoints = [
    'partners/exchange-rates',
    'partners/catalog?type=retailer_catalog',
    'partners/balance',
    'partners/terminals',
    'terminals',
    'account',
    'me',
];

echo "Scanning Wildflow API endpoints...\n";

foreach ($endpoints as $endpoint) {
    try {
        $res = $client->get($endpoint);
        echo "Endpoint GET /{$endpoint} -> Status: {$res->status()}\n";
        if ($res->successful()) {
            echo "Body: " . substr($res->body(), 0, 250) . "...\n\n";
        }
    } catch (\Exception $e) {
        echo "Error on {$endpoint}: {$e->getMessage()}\n";
    }
}
