<?php

namespace Tests\Feature;

use App\Jobs\AddCatalogItemToShop;
use App\Models\Currency;
use App\Models\LegalEntity;
use App\Models\Product;
use App\Models\ProductSalesChannel;
use App\Models\Provider;
use App\Models\ProviderProduct;
use App\Models\Seller;
use App\Models\Shop;
use App\Models\WildflowCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderProductSellerCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_product_is_transferred_to_seller_catalog_with_catalog_link_and_configured_channels(): void
    {
        $this->app->instance(\App\Services\CardImageService::class, new class {
            public function generateForCatalogItem(WildflowCatalog $catalogItem, Shop $shop): array
            {
                return [
                    'images' => ['main' => 'testing/provider-card.png'],
                    'title' => 'Seller Provider Card',
                    'description' => 'Generated provider card.',
                ];
            }
        });

        $this->app->instance(\App\Services\VideoInstructionService::class, new class {
            public function generateForProduct(Product $product): ?string
            {
                return null;
            }
        });

        Currency::create([
            'code' => 'RUB',
            'name' => 'Russian Ruble',
            'rate_to_rub' => 1,
            'manual_rate' => 1,
            'is_auto_update' => false,
        ]);

        $legalEntity = LegalEntity::create([
            'name' => 'Seller Catalog LLC',
            'short_name' => 'Seller Catalog',
            'inn' => '770000010001',
            'available_balance' => 10000,
            'reserved_balance' => 0,
            'currency' => 'RUB',
            'is_active' => true,
        ]);

        $shop = new Shop([
            'name' => 'Seller Catalog Shop',
            'domain' => 'seller-catalog.test',
            'voucher_prefix' => 'SC',
            'is_active' => true,
        ]);
        $shop->legal_entity_id = $legalEntity->id;
        $shop->save();

        $seller = Seller::create([
            'first_name' => 'Seller',
            'email' => 'seller-catalog@example.test',
            'password' => 'unused-password',
            'is_active' => true,
        ]);

        $provider = Provider::updateOrCreate(
            ['type' => 'wildflow'],
            [
                'name' => 'Wildflow',
                'is_active' => true,
                'credentials' => ['api_key' => 'testing-token'],
            ],
        );

        $catalog = WildflowCatalog::create([
            'provider_id' => $provider->id,
            'sku' => 'WF-SELLER-CATALOG-001',
            'service_sku' => 'EZPIN-SELLER-CATALOG-001',
            'retail_price' => 150,
            'purchase_price' => 100,
            'type' => 'giftcard',
            'is_active' => true,
            'data' => [
                'product' => [
                    'title' => 'Seller Catalog Provider Card',
                    'currency' => ['code' => 'RUB'],
                ],
            ],
        ]);

        $providerProduct = ProviderProduct::create([
            'provider_id' => $provider->id,
            'sku' => 'WF-SELLER-CATALOG-001',
            'market_sku' => 'WF-SELLER-CATALOG-001',
            'name' => 'Seller Catalog Provider Card',
            'category' => 'Gift Card',
            'purchase_price' => 100,
            'retail_price' => 150,
            'currency' => 'RUB',
            'is_active' => true,
            'data' => [],
        ]);

        (new AddCatalogItemToShop(
            catalogItemId: $providerProduct->id,
            shopId: $shop->id,
            sellerId: $seller->id,
            salesChannels: ['yandex_market', 'offline_store'],
            count: 0,
        ))->handle();

        $product = Product::queryByOfferSku('WF-SELLER-CATALOG-001')
            ->where('shop_id', $shop->id)
            ->firstOrFail();

        $this->assertSame($catalog->sku, $product->wildflow_catalog_sku);
        $this->assertSame($provider->id, $product->provider_id);
        $this->assertSame(15000, (int) $product->price_rub);
        $this->assertSame(15000, (int) $product->purchase_price_rub);
        $this->assertSame('RUB', $product->purchase_currency);
        $this->assertSame('✅ Ваучер Wildflow 150 RUB ✨ Мгновенная доставка', $product->name);

        $this->assertDatabaseHas('product_sales_channels', [
            'product_id' => $product->id,
            'shop_id' => $shop->id,
            'channel' => 'offline_store',
            'is_enabled' => true,
        ]);

        $this->assertFalse(ProductSalesChannel::query()
            ->where('product_id', $product->id)
            ->where('shop_id', $shop->id)
            ->where('channel', 'yandex_market')
            ->exists());
    }
}
