<?php

namespace App\Services;

use App\Models\LegalEntity;
use App\Models\Shop;
use App\Models\Warehouse;
use Illuminate\Support\Str;

class SellerDistributionCenterService
{
    /**
     * Ensure a seller has exactly one operational master warehouse.
     *
     * Warehouses are still tied to a shop in the current schema, so the
     * distribution shop is the technical home of the seller's master stock.
     *
     * @return array{shop: Shop, warehouse: Warehouse}
     */
    public function ensureForLegalEntity(LegalEntity $legalEntity): array
    {
        $shop = $this->distributionShop($legalEntity);
        $warehouse = $this->masterWarehouse($legalEntity, $shop);

        return ['shop' => $shop, 'warehouse' => $warehouse];
    }

    public function masterWarehouseForShop(Shop $shop): Warehouse
    {
        $legalEntity = $shop->legalEntity;
        if ($legalEntity) {
            return $this->ensureForLegalEntity($legalEntity)['warehouse'];
        }

        return $this->masterWarehouseForSingleShop($shop);
    }

    private function distributionShop(LegalEntity $legalEntity): Shop
    {
        $shop = $legalEntity->shops()
            ->where('is_distribution_center', true)
            ->oldest('id')
            ->first();

        if ($shop) {
            return $shop;
        }

        $shop = $legalEntity->shops()->oldest('id')->first();
        if ($shop) {
            $shop->forceFill([
                'is_distribution_center' => true,
                'is_active' => true,
            ])->save();

            return $shop;
        }

        $region = strtoupper((string) ($legalEntity->country_code ?: 'RU'));
        $prefix = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', (string) ($legalEntity->short_name ?: $legalEntity->name)), 0, 3)) ?: 'DST';

        return Shop::create([
            'legal_entity_id' => $legalEntity->id,
            'name' => 'Центр дистрибуции',
            'type' => Shop::TYPE_BOTH,
            'shop_region' => $region,
            'allowed_regions' => [$region],
            'allowed_categories' => [],
            'is_active' => true,
            'is_sandbox' => false,
            'is_distribution_center' => true,
            'voucher_prefix' => $prefix,
            'notification_token' => Str::random(24),
        ]);
    }

    private function masterWarehouse(LegalEntity $legalEntity, Shop $distributionShop): Warehouse
    {
        $warehouse = Warehouse::query()
            ->master()
            ->whereHas('shop', fn ($query) => $query->where('legal_entity_id', $legalEntity->id))
            ->oldest('id')
            ->first();

        if ($warehouse) {
            return $warehouse;
        }

        return $this->masterWarehouseForSingleShop($distributionShop);
    }

    private function masterWarehouseForSingleShop(Shop $shop): Warehouse
    {
        return Warehouse::query()->firstOrCreate(
            [
                'shop_id' => $shop->id,
                'is_main' => true,
                'channel' => null,
            ],
            [
                'ym_id' => null,
                'name' => 'Мастер-склад',
                'type' => 'master',
                'is_active' => true,
                'channel_quota' => 100,
            ],
        );
    }
}
