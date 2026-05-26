<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;

$token = "test-token";
$clientId = "1";
$financialSecret = "my-secret-key";

$client = Http::withHeaders([
    'Accept' => 'application/json',
    'X-Auth-Token' => $token,
    'X-Client-Id' => $clientId,
])->withMiddleware(function (callable $handler) use ($financialSecret) {
    return function (\Psr\Http\Message\RequestInterface $request, array $options) use ($handler, $financialSecret) {
        $timestamp = time();
        $body = (string)$request->getBody();
        $signature = hash_hmac('sha256', $timestamp . '.' . $body, $financialSecret);
        
        $request = $request->withHeader('X-Financial-Timestamp', (string)$timestamp)
                           ->withHeader('X-Financial-Signature', $signature);
                           
        return $handler($request, $options);
    };
})
->baseUrl("http://api.wildflow.test/api/v1");

// Test GET request
try {
    $response = $client->get("providers/ezpin/catalog");
    echo "Request Sent successfully. Status: " . $response->status() . "\n";
    echo "Sent Headers:\n";
    // We can inspect request headers using $response->transferStats or similar, but the call will try to connect.
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
