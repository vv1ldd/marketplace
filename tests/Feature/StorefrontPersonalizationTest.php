<?php

namespace Tests\Feature;

use App\Models\Order\Order;
use App\Models\Order\OrderItems;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\ProductSalesChannel;
use App\Models\StorefrontFavorite;
use App\Services\MeanlyFirstPartyStorefrontService;
use App\Services\StorefrontTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StorefrontPersonalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_favorite_toggle_and_home_shortcuts_are_storefront_token_projections(): void
    {
        $product = $this->seedProduct();
        $token = $this->storefrontToken('sl1e_personalbuyer0000000000000000000001');

        $this->withToken($token)
            ->postJson('/api/storefront/v1/favorites/toggle', [
                'product_slug' => $product->slug,
                'product_name' => $product->name,
                'category_slug' => 'subscriptions',
                'category_label' => 'Subscriptions',
            ])
            ->assertOk()
            ->assertJsonPath('contract.name', 'storefront-favorite-toggle')
            ->assertJsonPath('favorite', true)
            ->assertJsonPath('actions.next_action', 'TOGGLE_FAVORITE');

        $this->assertDatabaseHas('storefront_favorites', [
            'entity_l1_address' => 'sl1e_personalbuyer0000000000000000000001',
            'product_slug' => $product->slug,
            'category_slug' => 'subscriptions',
        ]);

        $this->withToken($token)
            ->getJson('/api/storefront/v1/personalization/home')
            ->assertOk()
            ->assertJsonPath('contract.name', 'storefront-personalized-home')
            ->assertJsonPath('category_shortcuts.0.slug', 'subscriptions')
            ->assertJsonPath('category_shortcuts.0.href', '/catalog/subscriptions');
    }

    public function test_home_shortcuts_include_purchase_categories(): void
    {
        $product = $this->seedProduct();
        $this->seedOrder($product, 'sl1e_purchasebuyer0000000000000000000001');
        $token = $this->storefrontToken('sl1e_purchasebuyer0000000000000000000001');

        $this->withToken($token)
            ->getJson('/api/storefront/v1/personalization/home')
            ->assertOk()
            ->assertJsonPath('category_shortcuts.0.slug', 'subscriptions')
            ->assertJsonPath('category_shortcuts.0.signals.0', 'purchase');
    }

    private function seedProduct(): Product
    {
        $storefront = app(MeanlyFirstPartyStorefrontService::class);
        $shop = $storefront->shop();

        $product = Product::create([
            'shop_id' => $shop->id,
            'sku' => 'PERSONAL-SUB-001',
            'name' => 'Personal Subscription Card',
            'slug' => 'personal-subscription-card',
            'price_rub' => 9900,
            'type' => 'subscription',
            'category' => 'Subscriptions',
            'canonical_category' => 'subscriptions',
            'is_active' => true,
        ]);

        ProductSalesChannel::create([
            'product_id' => $product->id,
            'shop_id' => $shop->id,
            'channel' => 'meanly_storefront',
            'is_enabled' => true,
        ]);

        ProductInventory::create([
            'shop_id' => $shop->id,
            'sku' => $product->sku,
            'nominal_amount' => 99,
            'nominal_currency' => 'RUB',
            'voucher' => 'PERSONAL-VOUCHER-001',
            'is_used' => false,
            'status' => 'available',
            'expires_at' => now()->addYear(),
        ]);

        return $product;
    }

    private function seedOrder(Product $product, string $buyerL1Address): Order
    {
        $order = Order::create([
            'uuid' => (string) Str::uuid(),
            'order_id' => 'MS-PERSONAL-'.Str::upper(Str::random(6)),
            'status' => 'NEW',
            'sub_status' => 'DIRECT_STOREFRONT',
            'progress_id' => 1,
            'shop_id' => $product->shop_id,
            'sales_channel' => 'meanly_storefront',
            'total_amount' => 99,
            'currency' => 'RUB',
            'info' => ['payment_status' => 'paid'],
            'client_info' => ['buyer_l1_address' => $buyerL1Address],
        ]);

        OrderItems::create([
            'key' => 'PERSONAL-VOUCHER-001',
            'uuid' => (string) Str::uuid(),
            'order_id' => $order->id,
            'activate_till' => now()->addYear()->format('Y-m-d'),
            'sku' => $product->sku,
            'count' => 1,
            'price_rub' => 9900,
            'type_form_id' => 2,
            'purchase_status' => 'completed',
        ]);

        return $order->refresh();
    }

    private function storefrontToken(string $entityAddress): string
    {
        return app(StorefrontTokenService::class)->issue([
            'entity_l1_address' => $entityAddress,
            'proof_token_hash' => hash('sha256', $entityAddress),
        ], ['storefront:read', 'storefront:vault'])['access_token'];
    }
}
