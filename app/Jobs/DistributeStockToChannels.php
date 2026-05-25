<?php

namespace App\Jobs;

use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Распределяет остатки с мастер-склада на склады каналов продаж
 * пропорционально их channel_quota (%).
 *
 * Использование:
 *   DistributeStockToChannels::dispatch($shop);
 *   DistributeStockToChannels::dispatch($shop, productId: 123); // только один товар
 */
class DistributeStockToChannels implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly \App\Models\Shop $shop,
        public readonly ?int $productId = null,
    ) {}

    public function handle(): void
    {
        $master = Warehouse::where('shop_id', $this->shop->id)->master()->first();

        if (! $master) {
            Log::warning('DistributeStock: мастер-склад не найден', ['shop_id' => $this->shop->id]);
            return;
        }

        // Получаем остатки с мастера
        $stocksQuery = WarehouseStock::where('warehouse_id', $master->id);
        if ($this->productId) {
            $stocksQuery->where('product_id', $this->productId);
        }
        $masterStocks = $stocksQuery->get()->keyBy('product_id');

        if ($masterStocks->isEmpty()) {
            Log::info('DistributeStock: остатков на мастер-складе нет', ['shop_id' => $this->shop->id]);
            return;
        }

        // Склады каналов
        $channelWarehouses = Warehouse::where('shop_id', $this->shop->id)
            ->channelWarehouses()
            ->where('is_active', true)
            ->get();

        foreach ($channelWarehouses as $channelWarehouse) {
            $quota = $channelWarehouse->channel_quota / 100;

            foreach ($masterStocks as $productId => $masterStock) {
                $allocatedCount = (int) floor($masterStock->count * $quota);

                WarehouseStock::updateOrCreate(
                    [
                        'warehouse_id' => $channelWarehouse->id,
                        'product_id'   => $productId,
                    ],
                    [
                        'count'     => $allocatedCount,
                        'synced_at' => now(),
                    ]
                );
            }

            // Диспатчим пуш в маркетплейс
            $this->dispatchChannelPush($channelWarehouse);
        }

        Log::info('DistributeStock: распределение завершено', [
            'shop_id'          => $this->shop->id,
            'master_warehouse' => $master->id,
            'products_count'   => $masterStocks->count(),
            'channels'         => $channelWarehouses->pluck('channel')->all(),
        ]);
    }

    private function dispatchChannelPush(Warehouse $warehouse): void
    {
        match ($warehouse->channel) {
            'yandex_market' => PushStockToYandex::dispatch($this->shop, $warehouse, $this->productId),
            // 'ozon'          => PushStockToOzon::dispatch($this->shop, $warehouse),
            // 'wildberries'   => PushStockToWildberries::dispatch($this->shop, $warehouse),
            default         => null,
        };
    }
}
