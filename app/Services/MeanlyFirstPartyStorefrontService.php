<?php

namespace App\Services;

use App\Models\LegalEntity;
use App\Models\Product;
use App\Models\ProductSalesChannel;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class MeanlyFirstPartyStorefrontService
{
    public function legalEntity(): LegalEntity
    {
        $inn = (string) config('meanly_storefront.legal_entity.inn');
        $entity = LegalEntity::findByInn($inn);

        if ($entity) {
            return $entity;
        }

        return LegalEntity::create([
            'name' => config('meanly_storefront.legal_entity.name'),
            'short_name' => config('meanly_storefront.legal_entity.short_name'),
            'inn' => $inn,
            'email' => config('meanly_storefront.legal_entity.email'),
            'available_balance' => config('meanly_storefront.legal_entity.available_balance', 1000000),
            'reserved_balance' => 0,
            'currency' => config('meanly_storefront.legal_entity.currency', 'RUB'),
            'tariff_type' => 'first_party',
            'allow_all_brands' => true,
            'is_active' => true,
            'status' => 'active',
        ]);
    }

    public function shop(): Shop
    {
        $entity = $this->legalEntity();
        $domain = (string) config('meanly_storefront.shop.domain');
        $prefix = (string) config('meanly_storefront.shop.voucher_prefix', 'MEAN');

        $shop = Shop::query()
            ->where('domain', $domain)
            ->orWhere(function ($query) use ($entity, $prefix) {
                $query->where('legal_entity_id', $entity->id)
                    ->where('voucher_prefix', $prefix);
            })
            ->first();

        $payload = array_filter([
            'name' => config('meanly_storefront.shop.name', 'Meanly Store'),
            'domain' => $domain,
            'voucher_prefix' => $prefix,
            'business_id' => config('meanly_storefront.shop.business_id'),
            'campaign_id' => config('meanly_storefront.shop.campaign_id'),
            'api_key' => config('meanly_storefront.shop.api_key'),
            'notification_token' => config('meanly_storefront.shop.notification_token'),
            'ym_warehouse_id' => config('meanly_storefront.shop.ym_warehouse_id'),
            'ym_stock' => config('meanly_storefront.shop.ym_stock', 10),
            'is_active' => true,
            'is_global_catalog_enabled' => true,
            'allow_all_brands' => true,
            'auto_purchase_enabled' => true,
            'shop_region' => 'RU',
        ], fn ($value) => $value !== null && $value !== '');
        $payload = array_filter(
            $payload,
            fn (string $column) => Schema::hasColumn('shops', $column),
            ARRAY_FILTER_USE_KEY
        );

        if (! $shop) {
            $shop = new Shop($payload);
            $shop->legal_entity_id = $entity->id;
            $shop->save();

            return $shop;
        }

        $shop->fill($payload);
        $shop->legal_entity_id = $entity->id;
        if ($shop->isDirty()) {
            $shop->save();
        }

        return $shop->refresh();
    }

    public function isMeanlyLegalEntity(LegalEntity $legalEntity): bool
    {
        return $legalEntity->id === $this->legalEntity()->id;
    }

    public function storefrontChannel(): string
    {
        return (string) config('meanly_storefront.channels.storefront', 'meanly_storefront');
    }

    public function yandexChannel(): string
    {
        return (string) config('meanly_storefront.channels.yandex', 'yandex_market');
    }

    /**
     * @return Builder<Product>
     */
    public function visibleProductsQuery(?Shop $shop = null): Builder
    {
        $shop ??= $this->shop();

        return Product::query()
            ->where('shop_id', $shop->id)
            ->where('is_active', true)
            ->whereHas('salesChannels', function ($query) {
                $query->where('channel', $this->storefrontChannel())
                    ->where('is_enabled', true);
            });
    }

    /**
     * Public marketplace catalog across all active seller shops.
     *
     * @return Builder<Product>
     */
    public function marketplaceProductsQuery(): Builder
    {
        return Product::query()
            ->with(['shop.legalEntity'])
            ->where('is_active', true)
            ->whereHas('shop', fn ($query) => $query->where('is_active', true))
            ->whereHas('salesChannels', function ($query) {
                $query->where('channel', $this->storefrontChannel())
                    ->whereColumn('product_sales_channels.shop_id', 'products.shop_id')
                    ->where('is_enabled', true);
            });
    }

    public function exposeProduct(Product $product, ?Shop $shop = null): void
    {
        $shop ??= $product->shop ?: $this->shop();

        ProductSalesChannel::updateOrCreate(
            [
                'product_id' => $product->id,
                'shop_id' => $shop->id,
                'channel' => $this->storefrontChannel(),
            ],
            [
                'is_enabled' => true,
                'last_error' => null,
            ]
        );
    }
}
