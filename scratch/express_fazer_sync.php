<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\FazerService;
use App\Models\Provider;
use App\Services\FinanceService;
use Illuminate\Support\Facades\Http;

$fazer = new FazerService();
$provider = Provider::where('type', 'fazer')->first();
$finance = app(FinanceService::class);
$apiKey = $provider->credentials['api_key'];

echo "--- Express Sync Fazer: Telegram & Roblox ---\n";

$client = Http::withHeaders(['X-API-Key' => $apiKey, 'Accept' => 'application/json'])
    ->baseUrl('https://api.fazercards.com/api/v1');

function syncProducts($client, $endpoint, $provider, $brand, $finance, $dataKey = 'products') {
    echo "Syncing $brand via $endpoint...\n";
    $response = $client->get($endpoint);
    $products = $response->json($dataKey) ?? [];
    echo "  Found " . count($products) . " products.\n";

    foreach ($products as $p) {
        $sku = $p['id'] ?? $p['product_id'] ?? $p['item_id'];
        $name = $p['name'] ?? $p['display_name'] ?? $p['title'] ?? 'Unknown Product';
        
        $brandId = \App\Services\MappingService::resolveBrand($provider->id, $brand, $sku, $name);

        \App\Models\ProviderProduct::updateOrCreate(
            ['provider_id' => $provider->id, 'sku' => $sku],
            [
                'market_sku' => $sku,
                'name' => $name,
                'price' => (int)(($p['price'] ?? 0) * 100),
                'currency' => $p['currency'] ?? 'USD',
                'is_active' => true,
                'category' => $brand,
                'data' => json_encode($p),
                'updated_at' => now(),
            ]
        );
    }
}

syncProducts($client, 'telegram/premium', $provider, 'Telegram', $finance, 'items');
syncProducts($client, 'telegram/stars', $provider, 'Telegram', $finance, 'items');
syncProducts($client, 'roblox/packages/products', $provider, 'Roblox', $finance);

echo "--- Done ---\n";
