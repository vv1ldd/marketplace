<?php

namespace App\Jobs;

use App\Http\Services\YmService;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Отправляет остатки YM-склада в API Яндекс Маркета.
 */
class PushStockToYandex implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly \App\Models\Shop $shop,
        public readonly Warehouse $warehouse,
        public readonly ?int $productId = null,
    ) {}

    public function handle(): void
    {
        if (! $this->shop->isYandexMarketActive()) {
            Log::warning('PushStockToYandex: Yandex Market is not active for shop', [
                'shop_id' => $this->shop->id,
                'warehouse_id' => $this->warehouse->id,
            ]);

            return;
        }

        $ymWarehouseId = $this->warehouse->ym_id ?: $this->shop->ym_warehouse_id;
        if (! $ymWarehouseId) {
            Log::warning('PushStockToYandex: YM warehouse id is not configured', [
                'shop_id' => $this->shop->id,
                'warehouse_id' => $this->warehouse->id,
            ]);

            return;
        }

        $stocksQuery = WarehouseStock::where('warehouse_id', $this->warehouse->id)
            ->with('product');

        if ($this->productId) {
            $stocksQuery->where('product_id', $this->productId);
        }

        $stocks = $stocksQuery->get();

        if ($stocks->isEmpty()) {
            Log::info('PushStockToYandex: нет остатков для отправки', [
                'warehouse_id' => $this->warehouse->id,
            ]);
            return;
        }

        $service = new YmService($this->shop);

        // Формат для YM API: [['sku' => 'ABC', 'warehouseId' => 123, 'items' => [['type' => 'FIT', 'count' => N]]]]
        $payload = $stocks->filter(fn ($s) => $s->product !== null && filled($s->product->sku))
            ->map(fn ($s) => [
                'sku'         => $s->product->sku,
                'warehouseId' => (int) $ymWarehouseId,
                'items'       => [
                    ['type' => 'FIT', 'count' => $s->count],
                ],
            ])
            ->values()
            ->all();

        if (empty($payload)) {
            Log::info('PushStockToYandex: нет валидных SKU для отправки', [
                'shop_id' => $this->shop->id,
                'warehouse_id' => $this->warehouse->id,
                'product_id' => $this->productId,
                'stock_count' => $stocks->count(),
            ]);

            return;
        }

        try {
            $service->updateStocks(['skus' => $payload]);

            WarehouseStock::where('warehouse_id', $this->warehouse->id)
                ->update(['synced_at' => now()]);

            Log::info('PushStockToYandex: остатки отправлены', [
                'shop_id'      => $this->shop->id,
                'warehouse_id' => $this->warehouse->id,
                'sku_count'    => count($payload),
            ]);
        } catch (\Exception $e) {
            Log::error('PushStockToYandex: ошибка', [
                'message' => $e->getMessage(),
                'shop_id' => $this->shop->id,
            ]);
            throw $e; // retry
        }
    }
}
