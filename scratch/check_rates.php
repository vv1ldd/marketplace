<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;

echo "--- DIRECT P2P API CHECK ---" . PHP_EOL;

// Bybit Check
echo "Checking Bybit..." . PHP_EOL;
$response = Http::timeout(30)
    ->withHeaders([
        'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    ])
    ->post("https://api2.bybit.com/fiat/otc/item/list", [
    "asset" => "USDT",
    "fiat" => "RUB",
    "page" => "1",
    "rows" => "10",
    "status" => "1"
]);

echo "Bybit Status: " . $response->status() . PHP_EOL;
$jsonData = $response->json();
if (isset($jsonData['result']['items'][0]['price'])) {
    echo "Bybit Price Found: " . $jsonData['result']['items'][0]['price'] . PHP_EOL;
} else {
    echo "Bybit Price NOT Found. Response: " . substr($response->body(), 0, 500) . PHP_EOL;
}

// Binance Check
echo PHP_EOL . "Checking Binance..." . PHP_EOL;
$response = Http::timeout(30)
    ->withHeaders([
        'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    ])
    ->post("https://p2p.binance.com/bapi/c2c/v2/friendly/c2c/adv/search", [
    "asset" => "USDT",
    "fiat" => "RUB",
    "merchantCheck" => false,
    "page" => 1,
    "payTypes" => [],
    "publisherType" => null,
    "rows" => 10,
    "tradeType" => "BUY"
]);

echo "Binance Status: " . $response->status() . PHP_EOL;
$jsonData = $response->json();
if (isset($jsonData['data'][0]['adv']['price'])) {
    echo "Binance Price Found: " . $jsonData['data'][0]['adv']['price'] . PHP_EOL;
} else {
    echo "Binance Price NOT Found. Response: " . substr($response->body(), 0, 500) . PHP_EOL;
}
