<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;
use App\Models\Provider;

$provider = Provider::where('type', 'fazer')->first();
$apiKey = $provider->credentials['api_key'];
$baseUrl = 'https://api.fazercards.com/api/v1';

function callFazer($path, $params = [], $method = 'GET') {
    global $apiKey, $baseUrl;
    $client = Http::withHeaders(['X-API-Key' => $apiKey, 'Accept' => 'application/json']);
    $url = "$baseUrl/$path";
    
    try {
        $response = ($method === 'POST') ? $client->post($url, $params) : $client->get($url, $params);
        return $response->json();
    } catch (\Exception $e) {
        return null;
    }
}

echo "--- Fazer Deep Discovery Started ---" . PHP_EOL;

// 1. Get all base games/categories
$catalog = callFazer('games');
$games = $catalog['games'] ?? [];
echo "Found " . count($games) . " base entries in /games" . PHP_EOL;

$stats = [
    'gift_cards' => 0,
    'topups' => 0,
    'game_keys' => 0,
    'total_products' => 0,
    'found_playstation' => false
];

foreach ($games as $index => $game) {
    $id = $game['id'];
    $name = $game['name'];
    
    if ($index % 50 == 0) echo "Processing ($index/" . count($games) . "): $name..." . PHP_EOL;

    // Try Gift Cards
    $res = callFazer('giftcards/products', ['category' => $id]);
    if (!empty($res['products'])) {
        $stats['gift_cards'] += count($res['products']);
        $stats['total_products'] += count($res['products']);
        if (str_contains(strtolower($name), 'playstation')) $stats['found_playstation'] = true;
    }

    // Try Topups
    $res = callFazer('topup/products', ['game_id' => $id]);
    if (!empty($res['products'])) {
        $stats['topups'] += count($res['products']);
        $stats['total_products'] += count($res['products']);
    }

    // Try Game Keys (POST)
    $res = callFazer('gamekeys/products', ['game_id' => $id], 'POST');
    if (!empty($res['products'])) {
        $stats['game_keys'] += count($res['products']);
        $stats['total_products'] += count($res['products']);
    }
}

// 2. Extra Endpoints
echo "Checking Telegram/Roblox/Steam..." . PHP_EOL;
$res = callFazer('telegram/premium');
if (!empty($res['items'])) $stats['total_products'] += count($res['items']);

$res = callFazer('roblox/packages/products');
if (!empty($res['products'])) $stats['total_products'] += count($res['products']);

echo "--- Discovery Summary ---" . PHP_EOL;
print_r($stats);
echo "--------------------------" . PHP_EOL;
