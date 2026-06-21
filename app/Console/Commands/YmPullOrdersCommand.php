<?php

namespace App\Console\Commands;

use App\Http\Controllers\OrderController;
use App\Http\Services\YmService;
use App\Models\Order\Order;
use App\Models\Shop;
use Illuminate\Console\Command;
use Throwable;

class YmPullOrdersCommand extends Command
{
    protected $signature = 'ym:pull-orders
        {--shop= : Shop ID with Yandex Market credentials (default: all configured shops)}
        {--order= : Pull and process a specific Yandex Market order ID}
        {--fake : Include sandbox/test orders from Yandex Market (required for cabinet test orders)}
        {--status=PROCESSING : Order status filter when scanning the API}
        {--substatus=STARTED : Order substatus filter when scanning the API}
        {--manual : Do not send digital slips back to Yandex Market (local processing only)}
        {--dry-run : List candidate orders without importing them}';

    protected $description = 'Pull new Yandex Market orders via API and run the local sales cycle (ORDER_CREATED → PROCESSING).';

    public function handle(): int
    {
        $shops = $this->resolveShops();
        if ($shops->isEmpty()) {
            $this->error('No shops with Yandex Market campaign_id and api_key found.');

            return self::FAILURE;
        }

        $specificOrderId = $this->option('order');
        if ($specificOrderId !== null && $specificOrderId !== '') {
            return $this->pullSpecificOrder($shops->first(), (int) $specificOrderId)
                ? self::SUCCESS
                : self::FAILURE;
        }

        $totalImported = 0;
        foreach ($shops as $shop) {
            $totalImported += $this->pullNewOrdersForShop($shop);
        }

        if ($totalImported === 0) {
            $this->warn('No new orders imported.');

            return self::SUCCESS;
        }

        $this->info("Imported {$totalImported} order(s).");

        return self::SUCCESS;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Shop>
     */
    private function resolveShops()
    {
        $shopId = $this->option('shop');

        $query = Shop::query()
            ->where('is_active', true)
            ->whereNotNull('campaign_id')
            ->where('campaign_id', '>', 0)
            ->whereNotNull('api_key')
            ->where('api_key', '!=', '');

        if ($shopId !== null && $shopId !== '') {
            $query->whereKey((int) $shopId);
        }

        return $query->orderBy('id')->get();
    }

    private function pullNewOrdersForShop(Shop $shop): int
    {
        $this->line('');
        $this->info("Shop #{$shop->id} {$shop->name} · campaign {$shop->campaign_id}");

        if (! $shop->legal_entity_id) {
            $this->warn('  Skipped: legal_entity_id is not set (voucher hold requires a legal entity).');

            return 0;
        }

        $service = new YmService($shop);
        $params = array_filter([
            'status' => $this->option('status') ?: null,
            'substatus' => $this->option('substatus') ?: null,
        ]);

        if ($this->option('fake')) {
            $params['fake'] = true;
        } else {
            $params['include_sandbox'] = true;
        }

        try {
            $orders = $service->getOrders($params);
        } catch (Throwable $e) {
            $this->error('  YM API error: '.$e->getMessage());

            return 0;
        }

        if ($orders === []) {
            $this->line('  No matching orders on Yandex Market.');

            return 0;
        }

        $imported = 0;
        foreach ($orders as $orderPayload) {
            $orderId = (int) data_get($orderPayload, 'id');
            if ($orderId <= 0) {
                continue;
            }

            if ($this->option('fake') && ! data_get($orderPayload, 'fake')) {
                continue;
            }

            if (Order::where('order_id', $orderId)->where('shop_id', $shop->id)->exists()) {
                $this->line("  · {$orderId} already imported, skip");

                continue;
            }

            $status = (string) data_get($orderPayload, 'status', '?');
            $substatus = (string) data_get($orderPayload, 'substatus', '-');
            $offers = collect(data_get($orderPayload, 'items', []))->pluck('offerId')->implode(', ');
            $fakeLabel = data_get($orderPayload, 'fake') ? 'test' : 'live';

            $this->line("  · {$orderId} [{$fakeLabel}] {$status}/{$substatus} · {$offers}");

            if ($this->option('dry-run')) {
                continue;
            }

            if ($this->importOrder($shop, $orderId, $orderPayload)) {
                $imported++;
            }
        }

        return $imported;
    }

    private function pullSpecificOrder(Shop $shop, int $orderId): bool
    {
        $this->info("Shop #{$shop->id} {$shop->name} · pulling order {$orderId}");

        if (! $shop->legal_entity_id) {
            $this->error('legal_entity_id is not set on this shop.');

            return false;
        }

        if (Order::where('order_id', $orderId)->where('shop_id', $shop->id)->exists()) {
            $this->warn('Order already exists in marketplace DB.');

            return true;
        }

        $service = new YmService($shop);

        try {
            $orderPayload = $service->getOrder($orderId);
        } catch (Throwable $e) {
            $this->error('YM getOrder failed: '.$e->getMessage());

            return false;
        }

        if ($orderPayload === []) {
            $this->error('Order not found on Yandex Market.');

            return false;
        }

        if ($this->option('fake') && ! data_get($orderPayload, 'fake')) {
            $this->error('Order is not marked as fake/test. Re-run without --fake if this is intentional.');

            return false;
        }

        $status = (string) data_get($orderPayload, 'status', '?');
        $substatus = (string) data_get($orderPayload, 'substatus', '-');
        $fakeLabel = data_get($orderPayload, 'fake') ? 'test' : 'live';
        $offers = collect(data_get($orderPayload, 'items', []))->pluck('offerId')->implode(', ');

        $this->line("  {$orderId} [{$fakeLabel}] {$status}/{$substatus} · {$offers}");

        if ($this->option('dry-run')) {
            return true;
        }

        return $this->importOrder($shop, $orderId, $orderPayload);
    }

    /**
     * @param  array<string, mixed>  $orderPayload
     */
    private function importOrder(Shop $shop, int $orderId, array $orderPayload): bool
    {
        $manual = (bool) $this->option('manual');
        $base = [
            'orderId' => $orderId,
            'campaignId' => (int) $shop->campaign_id,
            'shop_id' => $shop->id,
            'is_manual_sync' => $manual,
        ];

        try {
            $created = (new OrderController('ORDER_CREATED'))->created([
                ...$base,
                'notificationType' => 'ORDER_CREATED',
                'fake' => (bool) data_get($orderPayload, 'fake', false),
            ]);

            if (! ($created['success'] ?? false)) {
                $this->error('  ORDER_CREATED failed: '.($created['error'] ?? 'unknown'));

                return false;
            }

            $this->line('  ORDER_CREATED ok · SL1 '.($created['transaction_ref'] ?? '-'));

            $status = (string) data_get($orderPayload, 'status', '');
            $substatus = (string) data_get($orderPayload, 'substatus', '');

            if ($status !== 'PROCESSING') {
                $this->warn("  Order status is {$status}/{$substatus}; voucher issuance waits for PROCESSING/STARTED.");

                return true;
            }

            $updated = (new OrderController('ORDER_STATUS_UPDATED'))->updated([
                ...$base,
                'notificationType' => 'ORDER_STATUS_UPDATED',
                'status' => $status,
                'substatus' => $substatus !== '' ? $substatus : 'STARTED',
            ]);

            if (! ($updated['success'] ?? false)) {
                $this->error('  ORDER_STATUS_UPDATED failed: '.($updated['error'] ?? 'unknown'));

                return false;
            }

            $localOrder = Order::where('order_id', $orderId)->where('shop_id', $shop->id)->first();
            $item = $localOrder?->items()->first();

            $this->info('  ORDER_STATUS_UPDATED ok · voucher '.($item?->key ? 'issued' : 'missing'));

            return true;
        } catch (Throwable $e) {
            $this->error('  Import failed: '.$e->getMessage());

            return false;
        }
    }
}
