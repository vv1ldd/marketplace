<?php

namespace Tests\Feature;

use App\Models\Order\Order;
use App\Models\Order\OrderItems;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\ProductSalesChannel;
use App\Services\MeanlyFirstPartyStorefrontService;
use App\Services\StorefrontTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StorefrontVaultTransitRevealTest extends TestCase
{
    use RefreshDatabase;

    public function test_vault_reveal_returns_secret_for_owned_entitlement(): void
    {
        $buyer = 'sl1e_vaultrevealbuyer00000000000000000001';
        $product = $this->seedProduct();
        $order = $this->seedPaidOrder($product, $buyer);
        $item = $order->items->first();
        $item->forceFill(['original_code' => 'AAAA-BBBB-CCCC-DDDD'])->save();

        $token = $this->storefrontToken($buyer, ['storefront:vault']);
        $entitlementId = 'ent_'.$item->id;

        $this->withToken($token)
            ->postJson("/api/storefront/v1/vault/items/{$entitlementId}/reveal")
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.secret', 'AAAA-BBBB-CCCC-DDDD')
            ->assertJsonPath('data.entitlement_id', $entitlementId)
            ->assertJsonPath('data.first_reveal', true);

        $this->assertDatabaseHas('sovereign_ledger', [
            'event_type' => 'VAULT_ENTITLEMENT_REVEAL_INTENT',
        ]);
    }

    public function test_vault_reveal_rejects_foreign_entitlement(): void
    {
        $owner = 'sl1e_vaultrevealowner000000000000000000001';
        $intruder = 'sl1e_vaultrevealintruder00000000000000001';
        $product = $this->seedProduct();
        $order = $this->seedPaidOrder($product, $owner);
        $item = $order->items->first();
        $item->forceFill(['original_code' => 'SECRET-FOREIGN'])->save();

        $token = $this->storefrontToken($intruder, ['storefront:vault']);

        $this->withToken($token)
            ->postJson('/api/storefront/v1/vault/items/ent_'.$item->id.'/reveal')
            ->assertForbidden();
    }

    public function test_vault_reveal_rejects_invalid_entitlement_id(): void
    {
        $buyer = 'sl1e_vaultrevealbuyer20000000000000000002';
        $token = $this->storefrontToken($buyer, ['storefront:vault']);

        $this->withToken($token)
            ->postJson('/api/storefront/v1/vault/items/not-an-id/reveal')
            ->assertNotFound();
    }

    private function seedProduct(): Product
    {
        $storefront = app(MeanlyFirstPartyStorefrontService::class);
        $shop = $storefront->shop();

        $product = Product::create([
            'shop_id' => $shop->id,
            'sku' => 'VAULT-REVEAL-001',
            'name' => 'Steam Wallet Gift Card',
            'slug' => 'steam-wallet-reveal-card',
            'price_rub' => 2000,
            'type' => 'giftcard',
            'category' => 'Gift Cards',
            'vendor' => 'Steam',
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
            'nominal_amount' => 20,
            'nominal_currency' => 'USD',
            'voucher' => 'VAULT-REVEAL-VOUCHER',
            'is_used' => false,
            'status' => 'available',
            'expires_at' => now()->addYear(),
        ]);

        return $product;
    }

    private function seedPaidOrder(Product $product, string $buyerL1Address): Order
    {
        $order = Order::create([
            'uuid' => (string) Str::uuid(),
            'order_id' => 'MS-REVEAL-'.Str::upper(Str::random(6)),
            'status' => 'COMPLETED',
            'sub_status' => 'DIRECT_STOREFRONT',
            'progress_id' => 4,
            'shop_id' => $product->shop_id,
            'sales_channel' => 'meanly_storefront',
            'total_amount' => 20,
            'currency' => 'USD',
            'info' => [
                'payment_status' => 'captured',
                'order_safe' => [
                    'source' => 'local',
                    'status' => 'provider_code_ready',
                ],
            ],
            'client_info' => ['buyer_l1_address' => $buyerL1Address],
        ]);

        OrderItems::create([
            'key' => 'VAULT-REVEAL-VOUCHER',
            'uuid' => (string) Str::uuid(),
            'order_id' => $order->id,
            'activate_till' => now()->addYear()->format('Y-m-d'),
            'sku' => $product->sku,
            'count' => 1,
            'price_rub' => 2000,
            'nominal_amount' => 20,
            'nominal_currency' => 'USD',
            'type_form_id' => 2,
            'purchase_status' => 'completed',
        ]);

        return $order->refresh()->load('items');
    }

    /**
     * @param  array<int, string>  $scopes
     */
    private function storefrontToken(string $entityAddress, array $scopes): string
    {
        return app(StorefrontTokenService::class)->issue([
            'entity_l1_address' => $entityAddress,
            'proof_token_hash' => hash('sha256', $entityAddress),
        ], $scopes)['access_token'];
    }
}
