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

        $client = Http::withHeaders([
            'Accept' => 'application/json',
            'X-Auth-Token' => config('app.wildflow_token'),
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
