<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\Shop;
use App\Models\WildflowCatalog;

class SellerVoucherStockService
{
    public function capacityForProduct(Product $product, Shop $shop): array
    {
        $skuBidx = app(VaultTransitService::class)->computeBlindIndex($product->sku);

        $physical = ProductInventory::query()
            ->where('shop_id', $shop->id)
            ->where('sku_bidx', $skuBidx)
            ->where('is_used', false)
            ->where('status', 'available')
            ->count();

        $costRub = $this->reservationCostRub($product);
        $availableRub = $this->availableL1Balance($shop);
        $virtual = $costRub > 0 ? (int) floor($availableRub / $costRub) : 0;
        $cap = (int) ($shop->ym_stock ?: 0);
        $rawTotal = $physical + $virtual;

        return [
            'physical' => $physical,
            'virtual' => $virtual,
            'total' => $cap > 0 ? min($cap, $rawTotal) : $rawTotal,
            'cost_rub' => round($costRub, 2),
            'available_l1_rub' => round($availableRub, 2),
        ];
    }

    public function reservationCostRub(Product $product): float
    {
        $priceRub = (float) ($product->price_rub ?? $product->purchase_price_rub ?? 0) / 100;
        if ($priceRub > 0) {
            return $priceRub;
        }

        $catalogSku = $product->wildflow_catalog_sku ?: $product->sku;
        $catalog = WildflowCatalog::where('sku', $catalogSku)->first();
        if (! $catalog) {
            return 0.0;
        }

        return (float) $catalog->retail_price * app(FinanceService::class)->getRate($catalog->currency_code);
    }

    private function availableL1Balance(Shop $shop): float
    {
        $legalEntity = $shop->legalEntity;
        if (! $legalEntity) {
            return 0.0;
        }

        $state = app(L1StateService::class)->reconstructBalance($legalEntity);

        if (($state['blocks_processed'] ?? 0) === 0) {
            return (float) ($legalEntity->available_balance ?? 0.0);
        }

        return (float) ($state['available_balance'] ?? 0.0);
    }
}
