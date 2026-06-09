<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\CatalogGroup;
use App\Models\LegalEntity;
use App\Models\Provider;
use App\Models\ProviderBrandMapping;
use App\Models\ProviderCategoryMapping;
use App\Models\ProviderProduct;
use App\Models\Shop;
use App\Models\User;
use App\Http\Controllers\PartnerDashboardController;
use App\Services\MappingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StorefrontCatalogGroupTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_brand_mapping_hydrates_catalog_group_for_future_catalog_syncs(): void
    {
        config(['app.domain' => 'localhost', 'session.domain' => null]);

        $provider = Provider::updateOrCreate(
            ['type' => 'wildflow'],
            ['name' => 'Wildflow', 'is_active' => true],
        );

        $subscriptions = CatalogGroup::create([
            'name' => 'Подписки',
            'slug' => 'podpiski',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $brand = Brand::create([
            'name' => 'Netflix',
            'is_active' => true,
        ]);

        ProviderBrandMapping::create([
            'provider_id' => $provider->id,
            'external_name' => 'NETFLIX US',
            'brand_id' => $brand->id,
        ]);

        ProviderCategoryMapping::create([
            'provider_id' => $provider->id,
            'provider_category_name' => 'Subscriptions',
            'catalog_group_id' => $subscriptions->id,
        ]);

        $brandId = MappingService::resolveBrand(
            providerId: $provider->id,
            externalName: 'NETFLIX US',
            sku: 'NF-US-10',
            title: 'Netflix US 10 USD',
            providerCategoryName: 'Subscriptions',
        );

        $this->assertSame($brand->id, $brandId);
        $this->assertSame($subscriptions->id, $brand->fresh()->catalog_group_id);
    }

    public function test_storefront_products_are_filtered_by_catalog_group(): void
    {
        config(['app.domain' => 'localhost', 'session.domain' => null]);

        $role = Role::firstOrCreate(['name' => User::ROLE_MERCHANT_NODE, 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        $legalEntity = LegalEntity::create([
            'user_id' => $user->id,
            'name' => 'Category Filter LLC',
            'inn' => '7700000000',
            'available_balance' => 10000,
            'currency' => 'RUB',
            'is_active' => true,
        ]);
        $user->managedLegalEntities()->attach($legalEntity->id, ['role' => 'owner']);

        $shop = new Shop([
            'name' => 'Category Filter Shop',
            'domain' => 'category-filter.test',
            'voucher_prefix' => 'CF',
            'is_active' => true,
        ]);
        $shop->legal_entity_id = $legalEntity->id;
        $shop->save();

        $provider = Provider::updateOrCreate(
            ['type' => 'wildflow'],
            ['name' => 'Wildflow', 'is_active' => true],
        );

        $games = CatalogGroup::create([
            'name' => 'Игры',
            'slug' => 'igry',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $retail = CatalogGroup::create([
            'name' => 'Ритейл',
            'slug' => 'riteil',
            'sort_order' => 5,
            'is_active' => true,
        ]);

        $steam = Brand::create(['name' => 'Steam', 'catalog_group_id' => $games->id, 'is_active' => true]);
        $tjMaxx = Brand::create(['name' => 'TJ Maxx', 'catalog_group_id' => $retail->id, 'is_active' => true]);

        ProviderProduct::create([
            'provider_id' => $provider->id,
            'brand_id' => $steam->id,
            'sku' => 'STEAM-10',
            'market_sku' => 'WFC-STEAM-10',
            'name' => 'Steam Wallet 10 USD',
            'category' => 'Gift Card',
            'purchase_price' => 9,
            'retail_price' => 10,
            'min_price' => 10,
            'max_price' => 10,
            'currency' => 'USD',
            'is_active' => true,
            'data' => [],
        ]);

        ProviderProduct::create([
            'provider_id' => $provider->id,
            'brand_id' => $tjMaxx->id,
            'sku' => 'TJMAXX-10',
            'market_sku' => 'WFC-TJMAXX-10',
            'name' => 'TJ Maxx 10 USD',
            'category' => 'Gift Card',
            'purchase_price' => 9,
            'retail_price' => 10,
            'min_price' => 10,
            'max_price' => 10,
            'currency' => 'USD',
            'is_active' => true,
            'data' => [],
        ]);

        ProviderProduct::create([
            'provider_id' => $provider->id,
            'sku' => 'UNMAPPED-10',
            'market_sku' => 'WFC-UNMAPPED-10',
            'name' => 'Unmapped Gift Card 10 USD',
            'category' => 'Gift Card',
            'purchase_price' => 9,
            'retail_price' => 10,
            'min_price' => 10,
            'max_price' => 10,
            'currency' => 'USD',
            'is_active' => true,
            'data' => [],
        ]);

        $this->actingAs($user);
        $controller = app(PartnerDashboardController::class);

        $gamesResponse = $controller->getStorefrontProducts(Request::create(
            '/merchant/dashboard/storefront/products',
            'GET',
            ['catalog_group_id' => $games->id],
        ));
        $gamesPayload = $gamesResponse->getData(true);

        $this->assertSame(200, $gamesResponse->getStatusCode());
        $this->assertCount(1, $gamesPayload['products']);
        $this->assertSame('Steam Wallet 10 USD', $gamesPayload['products'][0]['name']);
        $this->assertSame('game_wallet_topups', $gamesPayload['products'][0]['catalog_group_id']);

        $retailResponse = $controller->getStorefrontProducts(Request::create(
            '/merchant/dashboard/storefront/products',
            'GET',
            ['catalog_group_id' => $retail->id],
        ));
        $retailPayload = $retailResponse->getData(true);

        $this->assertSame(200, $retailResponse->getStatusCode());
        $this->assertCount(1, $retailPayload['products']);
        $this->assertSame('TJ Maxx 10 USD', $retailPayload['products'][0]['name']);
        $this->assertSame('gift_cards', $retailPayload['products'][0]['catalog_group_id']);

        $unmappedResponse = $controller->getStorefrontProducts(Request::create(
            '/merchant/dashboard/storefront/products',
            'GET',
            ['catalog_group_id' => 'unmapped'],
        ));
        $unmappedPayload = $unmappedResponse->getData(true);

        $this->assertSame(200, $unmappedResponse->getStatusCode());
        $this->assertCount(0, $unmappedPayload['products']);
    }
}
