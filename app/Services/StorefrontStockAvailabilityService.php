<?php

namespace App\Services;

use App\Models\WildflowCatalog;
use Illuminate\Support\Facades\DB;

class StorefrontStockAvailabilityService
{
    public function check(
        WildflowCatalog $catalogItem,
        int $quantity = 1,
        ?float $price = null,
        ?string $terminalId = null
    ): array {
        $quantity = max(1, $quantity);
        $serviceSku = $this->serviceSku($catalogItem);
        $localAvailable = $this->localVoucherCount($serviceSku);

        if ($localAvailable >= $quantity) {
            return [
                'available' => true,
                'source' => 'local_vouchers',
                'service_sku' => $serviceSku,
                'local_available' => $localAvailable,
            ];
        }

        $syncedProduct = $this->syncedProviderProduct($serviceSku);
        if ($syncedProduct?->is_active) {
            return [
                'available' => true,
                'source' => 'provider_product_sync',
                'service_sku' => $serviceSku,
                'local_available' => $localAvailable,
                'provider_product_id' => $syncedProduct->id,
            ];
        }

        return [
            'available' => false,
            'source' => 'provider_product_sync',
            'service_sku' => $serviceSku,
            'local_available' => $localAvailable,
            'error' => 'Товар не активен в синхронизированном каталоге поставщика.',
        ];
    }

    private function serviceSku(WildflowCatalog $catalogItem): string
    {
        return (string) app(VaultTransitService::class)->decrypt($catalogItem->service_sku);
    }

    private function localVoucherCount(string $serviceSku): int
    {
        try {
            return (int) DB::table('api_wildflow_dev.local_vouchers')
                ->where('service_sku', $serviceSku)
                ->where('is_used', false)
                ->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function syncedProviderProduct(string $serviceSku): ?\App\Models\ProviderProduct
    {
        $vault = app(VaultTransitService::class);
        $skuBidx = $vault->computeBlindIndex($serviceSku);
        $marketSkuBidx = $vault->computeBlindIndex('WFC-'.substr(hash('sha256', $serviceSku), 0, 16));

        return \App\Models\ProviderProduct::query()
            ->where(function ($query) use ($skuBidx, $marketSkuBidx): void {
                $query->where('sku_bidx', $skuBidx)
                    ->orWhere('market_sku_bidx', $skuBidx)
                    ->orWhere('market_sku_bidx', $marketSkuBidx);
            })
            ->latest('updated_at')
            ->first();
    }
}
