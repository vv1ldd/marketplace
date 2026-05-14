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
    ) {}

    public function handle(): void
    {
        $stocks = WarehouseStock::where('warehouse_id', $this->warehouse->id)
            ->with('product')
            ->get();

        if ($stocks->isEmpty()) {
            Log::info('PushStockToYandex: нет остатков для отправки', [
                'warehouse_id' => $this->warehouse->id,
            ]);
            return;
        }

        $service = new YmService($this->shop);

        // Формат для YM API: [['sku' => 'ABC', 'warehouseId' => 123, 'items' => [['type' => 'FIT', 'count' => N]]]]
        $payload = $stocks->filter(fn ($s) => $s->product !== null)
            ->map(fn ($s) => [
                'sku'         => $s->product->sku,
                'warehouseId' => $this->warehouse->ym_id,
                'items'       => [
                    ['type' => 'FIT', 'count' => $s->count],
                ],
            ])
            ->values()
            ->all();

        try {
            $service->updateStocks($payload);

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
