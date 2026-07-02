<?php

namespace Tests\Feature;

use App\Models\Architecture\ExecutionRecord;
use App\Models\CanonicalProductIdentity;
use App\Models\Order\Order;
use App\Models\Order\OrderItems;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\ProductSalesChannel;
use App\Models\Provider;
use App\Models\ProviderProduct;
use App\Models\Architecture\OfferSnapshot;
use App\Models\WildflowCatalog;
use App\Services\MeanlyFirstPartyStorefrontService;
use App\Services\StorefrontTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StorefrontVaultAggregateTest extends TestCase
{
    use RefreshDatabase;

    public function test_vault_api_exposes_dashboard_inventory_and_execution_arrays(): void
    {
        $buyer = 'sl1e_vaultdashbuyer00000000000000000000001';
        $product = $this->seedProduct();
        $order = $this->seedPaidOrder($product, $buyer);
        $item = $order->items->first();
        $item->forceFill(['original_code' => 'SECRET-CODE-123'])->save();

        $stack = $this->seedExecutionStack($product, $order, $item);

        $info = $order->info ?? [];
        data_set($info, 'order_safe.offer_snapshot_id', $stack['snapshot_id']);
        data_set($info, 'order_safe.execution_record_id', $stack['execution_id']);
        data_set($info, 'order_safe.status', 'provider_code_ready');
        data_set($info, 'payment_status', 'captured');
        $order->forceFill(['info' => $info, 'status' => 'COMPLETED'])->save();

        $token = $this->storefrontToken($buyer, ['storefront:read', 'storefront:vault']);

        $this->withToken($token)
            ->getJson('/api/storefront/v1/vault')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.identity.status', 'durable')
            ->assertJsonPath('data.balances.currency', 'USD')
            ->assertJsonPath('data.inventory.0.brand', 'Steam')
            ->assertJsonPath('data.inventory.0.intent_key', 'discover:play')
            ->assertJsonPath('data.inventory.0.is_revealed', false)
            ->assertJsonPath('data.executions.0.state', ExecutionRecord::STATE_ISSUED);
    }

    /**
     * @return array{snapshot_id: string, execution_id: string}
     */
    private function seedExecutionStack(Product $product, Order $order, OrderItems $item): array
    {
        $provider = Provider::updateOrCreate(
            ['type' => 'wildflow-vault-dash'],
            [
                'name' => 'Vault Dash Provider',
                'is_active' => true,
                'settings' => ['upstream_provider' => 'ezpin-sandbox'],
                'credentials' => ['base_url' => 'https://wildflow.test/api/v1/'],
            ],
        );

        $catalogSku = 'VAULT-DASH-CAT-'.Str::upper(Str::random(4));
        WildflowCatalog::create([
            'sku' => $catalogSku,
            'reward_type' => 'Gift-Card',
            'retail_price' => 20.0,
            'purchase_price' => 16.0,
            'is_active' => true,
            'provider_id' => $provider->id,
            'service_sku' => '4401',
            'type' => 'unified_catalog',
            'data' => ['service_sku' => '4401'],
        ]);

        $providerProduct = ProviderProduct::create([
            'provider_id' => $provider->id,
            'sku' => $catalogSku,
            'market_sku' => $catalogSku,
            'name' => 'Steam Wallet Provider Product',
            'category' => 'Gift-Card',
            'purchase_price' => 16.0,
            'retail_price' => 20.0,
            'currency' => 'USD',
            'is_active' => true,
            'data' => ['service_sku' => '4401'],
        ]);

        $product->forceFill([
            'wildflow_catalog_sku' => $catalogSku,
            'provider_id' => $provider->id,
            'data' => ['provider_product_id' => $providerProduct->id],
        ])->save();

        $identity = CanonicalProductIdentity::create([
            'fingerprint' => hash('sha256', 'vault-dash-steam-tr'),
            'identity_slug' => 'steam-tr-20',
            'canonical_category' => 'play',
            'discovery_intent' => 'play',
            'brand' => 'Steam',
            'product_family' => 'Steam Wallet',
            'face_value' => 20,
            'face_value_currency' => 'USD',
            'region' => 'TR',
            'platform' => 'global',
            'confidence' => 'high',
            'signals' => [],
            'provider_candidates_count' => 1,
            'seller_offers_count' => 1,
            'best_offer_product_id' => $product->id,
            'last_seen_at' => now(),
        ]);

        $snapshotId = (string) Str::uuid();
        OfferSnapshot::create([
            'id' => $snapshotId,
            'snapshot_uuid' => (string) Str::uuid(),
            'canonical_product_identity_id' => $identity->id,
            'entitlement_fingerprint' => $identity->fingerprint,
            'shop_id' => $product->shop_id,
            'product_id' => $product->id,
            'sku' => $product->sku,
            'provider_id' => $provider->id,
            'provider_product_id' => $providerProduct->id,
            'provider_sku' => $catalogSku,
            'offer_kind' => OfferSnapshot::KIND_FIRST_PARTY,
            'buyer_price_cents' => 2000,
            'buyer_currency' => 'USD',
            'purchase_price_cents' => 1600,
            'storage_price_cents' => 0,
            'fulfillment_mode' => 'provider_code',
            'stock_count' => 1,
            'ranking_score' => 1,
            'full_payload_json' => ['source' => 'vault_dashboard_test'],
            'valid_from' => now(),
            'valid_until' => now()->addDay(),
            'created_at' => now(),
        ]);

        $executionId = (string) Str::uuid();
        ExecutionRecord::create([
            'id' => $executionId,
            'intent_id' => (string) Str::uuid(),
            'canonical_product_identity_id' => $identity->id,
            'offer_snapshot_id' => $snapshotId,
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'provider_id' => $provider->id,
            'idempotency_key' => 'exec:vault-dash:'.$item->id,
            'state' => ExecutionRecord::STATE_ISSUED,
            'vault_reference_id' => 'vault-ref-1',
        ]);

        return [
            'snapshot_id' => $snapshotId,
            'execution_id' => $executionId,
        ];
    }

    private function seedProduct(): Product
    {
        $storefront = app(MeanlyFirstPartyStorefrontService::class);
        $shop = $storefront->shop();

        $product = Product::create([
            'shop_id' => $shop->id,
            'sku' => 'VAULT-DASH-001',
            'name' => 'Steam Wallet Gift Card',
            'slug' => 'steam-wallet-gift-card',
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
            'voucher' => 'VAULT-VOUCHER-001',
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
            'order_id' => 'MS-VAULT-'.Str::upper(Str::random(6)),
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
                    'source' => 'provider',
                    'status' => 'provider_code_ready',
                ],
            ],
            'client_info' => ['buyer_l1_address' => $buyerL1Address],
        ]);

        OrderItems::create([
            'key' => 'VAULT-VOUCHER-001',
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
