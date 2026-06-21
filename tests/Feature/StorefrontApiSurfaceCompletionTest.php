<?php

namespace Tests\Feature;

use App\Models\Order\Order;
use App\Models\Order\OrderItems;
use App\Models\LegalEntity;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\ProductSalesChannel;
use App\Models\User;
use App\Services\MeanlyFirstPartyStorefrontService;
use App\Services\StorefrontTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StorefrontApiSurfaceCompletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.domain' => 'localhost',
            'session.domain' => null,
            'meanly_storefront.legal_entity.inn' => '770000099001',
            'meanly_storefront.legal_entity.name' => 'Meanly First Party LLC',
            'meanly_storefront.legal_entity.short_name' => 'Meanly',
            'meanly_storefront.shop.name' => 'Meanly Test Store',
            'meanly_storefront.shop.domain' => 'meanly.test',
            'meanly_storefront.shop.voucher_prefix' => 'MEAN',
        ]);
    }

    public function test_context_product_partner_and_vault_surfaces_are_dto_only(): void
    {
        $product = $this->seedProduct();
        $order = $this->seedOrder($product, 'sl1e_surfacebuyer00000000000000000000000001');
        $token = $this->storefrontToken('sl1e_surfacebuyer00000000000000000000000001', ['storefront:read', 'storefront:vault']);
        $partnerToken = $this->storefrontToken('sl1e_surfacepartner0000000000000000000001', ['storefront:read', 'storefront:partner-registration']);

        $this->getJson('/api/storefront/v1/context')
            ->assertOk()
            ->assertJsonPath('contract.name', 'storefront-context')
            ->assertJsonPath('market.key', 'global')
            ->assertJsonPath('settlement_networks.default', 'simple-layer-1');

        $this->getJson('/api/storefront/v1/catalog/products/'.$product->slug)
            ->assertOk()
            ->assertJsonPath('contract.name', 'storefront-product')
            ->assertJsonPath('data.type', 'storefront_product')
            ->assertJsonMissing(['provider_payload']);

        $this->getJson('/api/storefront/v1/partner-registration/state')
            ->assertOk()
            ->assertJsonPath('state.next_action', 'CONNECT_SIMPLE_L1');

        $this->withToken($partnerToken)
            ->getJson('/api/storefront/v1/partner-registration/state')
            ->assertOk()
            ->assertJsonPath('state.registration_step_status', 'identity_verified')
            ->assertJsonPath('state.next_action', 'SUBMIT_BUSINESS_PROFILE')
            ->assertJsonPath('state.blocking_reason', null);

        $this->withToken($token)
            ->getJson('/api/storefront/v1/vault')
            ->assertOk()
            ->assertJsonPath('contract.name', 'storefront-vault')
            ->assertJsonPath('items.0.order_uuid', $order->uuid)
            ->assertJsonPath('items.0.decision.transition_id', 'PAYMENT_PENDING');
    }

    public function test_checkout_create_and_order_safe_actions_use_storefront_token_boundary(): void
    {
        $product = $this->seedProduct();
        $token = $this->storefrontToken('sl1e_checkoutbuyer0000000000000000000000001', ['storefront:read', 'storefront:checkout']);

        $create = $this->withToken($token)
            ->postJson('/api/storefront/v1/checkout/create', [
                'product_id' => $product->id,
                'quantity' => 1,
                'email' => 'buyer@example.test',
                'name' => 'Buyer',
            ])
            ->assertOk()
            ->assertJsonPath('contract.name', 'storefront-checkout-create')
            ->assertJsonPath('order.decision.transition_id', 'PAYMENT_PENDING');

        $uuid = (string) $create->json('order.order_uuid');

        $this->withToken($token)
            ->getJson('/api/storefront/v1/orders/'.$uuid.'/safe/status')
            ->assertOk()
            ->assertJsonPath('decision.transition_id', 'PAYMENT_PENDING');

        $this->withToken($token)
            ->postJson('/api/storefront/v1/orders/'.$uuid.'/safe/open')
            ->assertStatus(202)
            ->assertJsonPath('decision.transition_id', 'PAYMENT_PENDING');

        $this->withToken($token)
            ->postJson('/api/storefront/v1/orders/'.$uuid.'/safe/scratch', ['scratch_proof' => 'proof-1'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->withToken($token)
            ->getJson('/api/storefront/v1/orders/'.$uuid.'/safe/support')
            ->assertOk()
            ->assertJsonPath('contract.name', 'storefront-order-safe-support');
    }

    public function test_vault_exposes_authority_surfaces_only_from_identity_grants(): void
    {
        $partnerAddress = 'sl1e_'.str_repeat('a', 39);
        $opsAddress = 'sl1e_'.str_repeat('b', 39);

        $partnerUser = User::factory()->create(['entity_l1_address' => $partnerAddress]);
        LegalEntity::create([
            'user_id' => $partnerUser->id,
            'name' => 'Granted Partner Entity',
            'short_name' => 'Granted Partner',
            'inn' => '770000000333',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->withToken($this->storefrontToken($partnerAddress, ['storefront:read', 'storefront:vault']))
            ->getJson('/api/storefront/v1/vault')
            ->assertOk()
            ->assertJsonPath('authority_surfaces.0.key', 'merchant')
            ->assertJsonPath('authority_surfaces.0.grant', 'meanly.partner.workspace')
            ->assertJsonMissing(['grant' => 'meanly.ops']);

        Role::firstOrCreate(['name' => User::ROLE_SOVEREIGN_VALIDATOR, 'guard_name' => 'web']);
        $opsUser = User::factory()->create(['entity_l1_address' => $opsAddress]);
        $opsUser->assignRole(User::ROLE_SOVEREIGN_VALIDATOR);

        $this->withToken($this->storefrontToken($opsAddress, ['storefront:read', 'storefront:vault']))
            ->getJson('/api/storefront/v1/vault')
            ->assertOk()
            ->assertJsonPath('authority_surfaces.0.key', 'ops')
            ->assertJsonPath('authority_surfaces.0.grant', 'meanly.ops');
    }

    public function test_premium_wallet_assets_are_protected_preview_contract(): void
    {
        $entityAddress = 'sl1e_walletpreview000000000000000000000001';

        $this->getJson('/api/storefront/v1/wallet/assets')
            ->assertUnauthorized();

        $this->withToken($this->storefrontToken($entityAddress, ['storefront:read']))
            ->getJson('/api/storefront/v1/wallet/assets')
            ->assertForbidden();

        $this->withToken($this->storefrontToken($entityAddress, ['storefront:read', 'storefront:vault']))
            ->getJson('/api/storefront/v1/wallet/assets')
            ->assertOk()
            ->assertJsonPath('contract.name', 'storefront-vault-wallet-coins')
            ->assertJsonPath('contract.network', 'simple-layer-1')
            ->assertJsonPath('settlement_network.key', 'simple-layer-1')
            ->assertJsonPath('wallet.network_key', 'simple-layer-1')
            ->assertJsonPath('contract.mode', 'preview')
            ->assertJsonPath('wallet.tier', 'premium-preview')
            ->assertJsonPath('wallet.label', 'Vault Wallet')
            ->assertJsonPath('coins.0.symbol', 'SL1')
            ->assertJsonPath('coins.1.symbol', 'MCR')
            ->assertJsonPath('coins.2.symbol', 'MLP')
            ->assertJsonPath('coins.0.transferable', false)
            ->assertJsonPath('capabilities.can_transfer_coins', false);

        $this->withToken($this->storefrontToken($entityAddress, ['storefront:read', 'storefront:vault']))
            ->getJson('/api/storefront/v1/wallet')
            ->assertOk()
            ->assertJsonPath('settlement_networks.default', 'simple-layer-1');
    }

    private function seedProduct(): Product
    {
        $storefront = app(MeanlyFirstPartyStorefrontService::class);
        $shop = $storefront->shop();

        $product = Product::create([
            'shop_id' => $shop->id,
            'sku' => 'SURFACE-PRODUCT-001',
            'name' => 'Surface Product Gift Card',
            'slug' => 'surface-product-gift-card',
            'price_rub' => 15000,
            'type' => 'giftcard',
            'category' => 'Gift Cards',
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
            'nominal_amount' => 150,
            'nominal_currency' => 'RUB',
            'voucher' => 'SURFACE-VOUCHER-001',
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
            'order_id' => 'MS-SURFACE-'.Str::upper(Str::random(6)),
            'status' => 'NEW',
            'sub_status' => 'DIRECT_STOREFRONT',
            'progress_id' => 1,
            'shop_id' => $product->shop_id,
            'sales_channel' => 'meanly_storefront',
            'total_amount' => 150,
            'currency' => 'RUB',
            'info' => ['payment_status' => 'pending'],
            'client_info' => ['buyer_l1_address' => $buyerL1Address],
        ]);

        OrderItems::create([
            'key' => 'SURFACE-VOUCHER-001',
            'uuid' => (string) Str::uuid(),
            'order_id' => $order->id,
            'activate_till' => now()->addYear()->format('Y-m-d'),
            'sku' => $product->sku,
            'count' => 1,
            'price_rub' => 15000,
            'type_form_id' => 2,
            'purchase_status' => 'pending',
        ]);

        return $order->refresh();
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
