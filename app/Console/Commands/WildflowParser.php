<?php

namespace App\Console\Commands;

use App\Models\WildflowCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class WildflowParser extends Command
{
    protected $signature = 'app:wildflow-parser';

    protected $description = 'Wildflow catalog parser';

    public function handle()
    {
        set_time_limit(480);

        $provider = \App\Models\Provider::where('type', 'wildflow')->first();
        $token = $provider->credentials['api_key'] ?? config('app.wildflow_token');

        $client = Http::withHeaders([
            'Accept' => 'application/json',
            'X-Auth-Token' => $token,
        ])
            ->timeout(60)
            ->withoutVerifying()
            ->baseUrl("https://api.wildflow.dev/api/v1");

        $this->parseCatalog($client, 'retailer_catalog');
        $this->parseCatalog($client, 'catalog');
    }

    private function parseCatalog($client, string $type): bool
    {
        $response = $client->get('partners/catalog', [
            'type' => $type
        ]);

        $items = $response->json('items') ?? [];

        $rows = [];

        foreach ($items as $item) {

            $data = $item['data'] ?? [];

            $rows[] = [
                'service_sku' => $item['sku'],
                'sku' => $this->skuGenerator($data, $type),
                'data' => json_encode($item),
                'type' => $type,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        WildflowCatalog::upsert(
            $rows,
            ['service_sku'], // уникальный ключ
            ['sku','data','type','updated_at'] // обновляемые поля
        );

        // Get exchange rates
        $binance_service = new \App\Http\Services\BinanceService();
        $tax = $provider->settings['tax'] ?? 30;
        $manual_rate = $provider->settings['currency_rate'] ?? null;

        $usdt_rub = $binance_service->tickerPrice('USDTRUB');
        // Wildflow is usually USD based
        $effective_rate = $manual_rate ?? $usdt_rub;

        $ym = new \App\Http\Controllers\Ym\MainController($tax);

        // Синхронизируем с универсальной таблицей products
        $products = [];
        foreach ($rows as $row) {
            $item = json_decode($row['data'], true);
            $data = $item['data'] ?? [];
            $productData = $data['product'] ?? $item;

            $name = '';
            if (($productData['reward_type_text'] ?? '') === 'Gift-Card') {
                $name .= 'Подарочная карта ';
            }

            $title = $productData['title'] ?? ($row['sku']);
            $retailPrice = (float)($data['price'] ?? $item['max_price'] ?? 0);
            $purchasePrice = (float)($item['price'] ?? $retailPrice);
            
            $currencyCode = ($productData['currency']['code'] ?? $item['currency']['code'] ?? 'USD');
            $name .= $title . ' ' . $retailPrice . $currencyCode;

            // Calculate price_rub based on base_price (retailPrice)
            // Simulating an item object for pricesCalc
            $tempItem = (object)['price_with_discount' => $retailPrice * 100, 'base_price' => $retailPrice * 100];
            [$priceRub, $basePriceRub] = $ym->pricesCalc($tempItem, 1, $effective_rate); 
            // Wait, pricesCalc: round((($item->price_with_discount / $usdt_try) * $usdt_rub) * (1 + $this->ps_tax / 100))
            // If item->price_with_discount is USD (cent), usdt_try = 1, usdt_rub = 100 
            // Result is cents in RUB. OK.

            $category = ($productData['reward_type_text'] ?? '') === 'Gift-Card' ? 'gift-card' : 'game';

            $products[] = [
                'sku' => $row['sku'],
                'name' => $name,
                'type' => 'wildflow',
                'category' => $category,
                'price_rub' => $priceRub, 
                'purchase_price' => $purchasePrice * 100,
                'purchase_currency' => $currencyCode,
                'base_price' => $retailPrice * 100,
                'data' => $row['data'],
                'is_active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ];
        }

        \App\Models\Product::upsert(
            $products,
            ['sku'],
            ['name', 'category', 'price_rub', 'purchase_price', 'purchase_currency', 'base_price', 'data', 'updated_at']
        );

        return true;
    }

    private function skuGenerator(array $data, string $type): ?string
    {
        if ($type === 'retailer_catalog') {

            $title = $this->normalizeTitle($data['product']['title'] ?? '');

            return strtoupper(
                'VOUCHER-GC-' .
                $title . '-' .
                (data_get($data, 'product.regions.0.code')) . '-' .
                ($data['price'] ?? '') .
                ($data['product']['currency']['code'] ?? '') . '-' .
                'RTL' . '-' .
                ($data['product']['sku'])
            );
        }

        if ($type === 'catalog') {

            $title = $this->normalizeTitle($data['title'] ?? '');

            return strtoupper(
                'VOUCHER-GC-' .
                $title . '-' .
                (data_get($data, 'regions.0.code')) . '-' .
                ($data['max_price'] ?? '') .
                ($data['currency']['code'] ?? '')  . '-' .
                'CTLG' . '-' .
                ($data['sku'])
            );
        }

        return null;
    }

    private function normalizeTitle(string $title): string
    {
        return str_replace(' ', '-', trim($title));
    }

}
