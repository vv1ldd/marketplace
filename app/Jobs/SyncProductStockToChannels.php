<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\Shop;
use App\Http\Services\YmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncProductStockToChannels implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;
    protected $productId;
    protected $shopId;

    public function __construct(int $productId, int $shopId)
    {
        $this->productId = $productId;
        $this->shopId = $shopId;
    }

    public function handle(): void
    {
        $product = Product::find($this->productId);
        $shop = Shop::find($this->shopId);

        if (!$product || !$shop) {
            return;
        }

        // 1. Get the Master stock (Source of Truth)
        $masterWarehouse = \App\Models\Warehouse::where('shop_id', $shop->id)->where('is_main', true)->first();
        if (!$masterWarehouse) {
            Log::warning("Dynamic Sync: No Master warehouse for shop {$shop->id}");
            return;
        }

        $capacity = app(\App\Services\SellerVoucherStockService::class)->capacityForProduct($product, $shop);
        $stockCount = $capacity['total'];

        // 2. Sync to Yandex Market if enabled
        $yandexChannel = $product->salesChannels()
            ->where('shop_id', $shop->id)
            ->where('channel', 'yandex_market')
            ->where('is_enabled', true)
            ->first();

        if ($yandexChannel) {
            $ymWarehouseId = $shop->ym_warehouse_id;
            
            if (!$ymWarehouseId) {
                $fallbackWh = \App\Models\Warehouse::where('shop_id', $shop->id)->whereNotNull('ym_id')->first();
                $ymWarehouseId = $fallbackWh?->ym_id;
            }

            if ($ymWarehouseId) {
                try {
                    $service = new YmService($shop);
                    $payload = [[
                        'sku' => $product->sku,
                        'warehouseId' => (int) $ymWarehouseId,
                        'items' => [['type' => 'FIT', 'count' => (int) $stockCount]]
                    ]];
                    
                    $service->updateStocks(['skus' => $payload]);
                    Log::info("Dynamic Sync: Pushed Voucher Capacity ({$stockCount}) to Yandex (Warehouse {$ymWarehouseId}) for SKU {$product->sku}", $capacity);
                } catch (\Exception $e) {
                    Log::error("Dynamic Sync Error (Yandex): " . $e->getMessage());
                }
            }
        }
    }
}
