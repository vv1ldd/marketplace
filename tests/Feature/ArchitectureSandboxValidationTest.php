<?php

namespace Tests\Feature;

use App\Models\Architecture\ExecutionRecord;
use App\Models\Architecture\OfferSnapshot;
use App\Models\CanonicalProductIdentity;
use App\Models\LegalEntity;
use App\Models\Order\Order;
use App\Models\Order\OrderItems;
use App\Models\Product;
use App\Models\ProductSalesChannel;
use App\Models\Provider;
use App\Models\ProviderProduct;
use App\Models\Shop;
use App\Models\WildflowCatalog;
use App\Services\Architecture\ArchitectureMetrics;
use App\Services\Architecture\ExecutionRecordServiceInterface;
use App\Services\Architecture\OfferSnapshotServiceInterface;
use App\Services\MeanlyFirstPartyStorefrontService;
use App\Services\StorefrontFulfillmentService;
use App\Support\PricingContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\Support\FakeProviderGateway;
use Tests\TestCase;

/**
 * Sandbox rollout validation: sidecar + snapshot_fulfillment_mode enabled.
 *
 * Verifies:
 * - New orders pin snapshot and fulfill from frozen SKU (no live catalog read).
 * - Legacy orders without snapshot increment fallback_live_catalog_count.
 */
class ArchitectureSandboxValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config([
            'services.wildflow.kernel_url' => 'https://wildflow.test/api/v1',
            'services.wildflow.verify_tls' => false,
            'services.wildflow.kernel_mode' => 'http',
            'services.dgs.fulfillment_mode' => 'http',
            'meanly_storefront.provider_fulfillment.allow_live_redemption' => true,
            'architecture.sidecar_enabled' => true,
            'architecture.snapshot_fulfillment_mode' => true,
        ]);
        app()->instance(PricingContext::class, new PricingContext('global', 'RUB', 'RUB', 'RUB'));
        FakeProviderGateway::boot();
        FakeProviderGateway::expectSandbox();
    }

    public function test_sandbox_new_order_fulfills_from_pinned_snapshot_only(): void
    {
        $seed = $this->seedProviderOrder(pinSnapshot: true, pinnedServiceSku: '4401');

        $seed['catalog']->update([
            'data' => array_merge((array) $seed['catalog']->data, ['service_sku' => 'REBUILT-9999']),
            'service_sku' => 'REBUILT-9999',
            'is_active' => false,
        ]);

        FakeProviderGateway::expectSandbox();

        $result = app(StorefrontFulfillmentService::class)->fulfillProviderOrder($seed['order']->fresh());

        $this->assertSame('provider_code_ready', $result['status']);
        $this->assertSame('4401', FakeProviderGateway::dispatchedServiceSku());
        $this->assertSame(0, ArchitectureMetrics::count('architecture.execution.fallback_live_catalog_count'));
        $this->assertSame('FAKE-CODE-001', $seed['item']->fresh()->original_code);

        $execution = ExecutionRecord::query()->findOrFail($seed['execution_id']);
        $this->assertSame(ExecutionRecord::STATE_ISSUED, $execution->state);
        $this->assertNotNull($execution->vault_reference_id);

        $this->assertDatabaseHas('offer_snapshots', [
            'id' => $seed['snapshot_id'],
            'provider_sku' => '4401',
        ]);
    }

    public function test_sandbox_legacy_order_without_snapshot_uses_live_catalog_fallback(): void
    {
        $seed = $this->seedProviderOrder(pinSnapshot: false);

        $seed['catalog']->update([
            'data' => array_merge((array) $seed['catalog']->data, ['service_sku' => 'LIVE-CATALOG-777']),
            'service_sku' => 'LIVE-CATALOG-777',
        ]);

        FakeProviderGateway::expectSandbox();

        $beforeFallback = ArchitectureMetrics::count('architecture.execution.fallback_live_catalog_count');

        $result = app(StorefrontFulfillmentService::class)->fulfillProviderOrder($seed['order']->fresh());

        $this->assertSame('provider_code_ready', $result['status']);
        $this->assertSame('LIVE-CATALOG-777', FakeProviderGateway::dispatchedServiceSku());
        $this->assertSame($beforeFallback + 1, ArchitectureMetrics::count('architecture.execution.fallback_live_catalog_count'));
        $this->assertNull(data_get($seed['order']->fresh()->info, 'order_safe.offer_snapshot_id'));
        $this->assertSame(0, OfferSnapshot::query()->count());
    }

    public function test_sandbox_sidecar_writes_snapshot_and_execution_on_pin(): void
    {
        $seed = $this->seedProviderOrder(pinSnapshot: true, pinnedServiceSku: '4401');

        $this->assertSame(1, OfferSnapshot::query()->count());
        $this->assertSame(1, ExecutionRecord::query()->count());
        $this->assertNotEmpty($seed['snapshot_id']);
        $this->assertNotEmpty($seed['execution_id']);

        $snapshot = OfferSnapshot::query()->findOrFail($seed['snapshot_id']);
        $this->assertSame('4401', $snapshot->provider_sku);
        $this->assertNull($snapshot->valid_until);

        $execution = ExecutionRecord::query()->findOrFail($seed['execution_id']);
        $this->assertSame(ExecutionRecord::STATE_RESERVED, $execution->state);
        $this->assertSame($seed['snapshot_id'], $execution->offer_snapshot_id);
    }

    /**
     * @return array{
     *     shop: Shop,
     *     product: Product,
     *     catalog: WildflowCatalog,
     *     provider: Provider,
     *     order: Order,
     *     item: OrderItems,
     *     snapshot_id: string,
     *     execution_id: string
     * }
     */
    private function seedProviderOrder(bool $pinSnapshot, string $pinnedServiceSku = '4401'): array
    {
        $provider = Provider::updateOrCreate(
            ['type' => 'wildflow-sandbox'],
            [
                'name' => 'Wildflow Sandbox',
                'is_active' => true,
                'settings' => ['upstream_provider' => 'ezpin-sandbox'],
                'credentials' => [
                    'base_url' => 'https://wildflow.test/api/v1/',
                    'api_key' => 'wf-token',
                    'client_id' => 'wf-client',
                    'financial_secret' => 'wf-secret',
                ],
            ],
        );

        $storefront = app(MeanlyFirstPartyStorefrontService::class);
        $shop = $storefront->shop();
        $shopId = $shop->id;

        $catalogSku = 'WF-SBX-'.Str::upper(Str::random(6));
        $catalog = WildflowCatalog::create([
            'sku' => $catalogSku,
            'reward_type' => 'Gift-Card',
            'retail_price' => 10.0,
            'purchase_price' => 8.0,
            'is_active' => true,
            'provider_id' => $provider->id,
            'service_sku' => $pinnedServiceSku,
            'type' => 'unified_catalog',
            'data' => ['service_sku' => $pinnedServiceSku],
        ]);

        $providerProduct = ProviderProduct::create([
            'provider_id' => $provider->id,
            'sku' => $catalogSku,
            'market_sku' => $catalogSku,
            'name' => 'Sandbox Provider Product',
            'category' => 'Gift-Card',
            'purchase_price' => 8.0,
            'retail_price' => 10.0,
            'currency' => 'USD',
            'is_active' => true,
            'data' => ['service_sku' => $pinnedServiceSku],
        ]);

        $product = Product::create([
            'shop_id' => $shopId,
            'sku' => 'SELLER-'.$catalogSku,
            'name' => 'Sandbox Seller Listing',
            'slug' => Str::slug('sandbox-'.$catalogSku),
            'price_rub' => 100000,
            'wildflow_catalog_sku' => $catalogSku,
            'provider_id' => $provider->id,
            'type' => 'giftcard',
            'category' => 'Gift Cards',
            'is_active' => true,
            'data' => ['provider_product_id' => $providerProduct->id],
        ]);

        ProductSalesChannel::create([
            'product_id' => $product->id,
            'shop_id' => $shopId,
            'channel' => $storefront->storefrontChannel(),
            'is_enabled' => true,
        ]);

        CanonicalProductIdentity::create([
            'fingerprint' => hash('sha256', 'sandbox-identity-'.$catalogSku),
            'identity_slug' => 'sandbox-'.$catalogSku,
            'canonical_category' => 'gift_cards',
            'brand' => 'Sandbox',
            'product_family' => 'Sandbox',
            'face_value' => 10,
            'face_value_currency' => 'USD',
            'region' => 'global',
            'platform' => 'global',
            'confidence' => 'high',
            'signals' => [],
            'provider_candidates_count' => 1,
            'seller_offers_count' => 1,
            'best_offer_product_id' => $product->id,
            'last_seen_at' => now(),
        ]);

        $order = Order::create([
            'order_id' => 'SBX-'.Str::upper(Str::random(8)),
            'uuid' => (string) Str::uuid(),
            'status' => 'PROCESSING',
            'shop_id' => $shopId,
            'progress_id' => 2,
            'sales_channel' => $storefront->storefrontChannel(),
            'total_amount' => 1000,
            'currency' => 'RUB',
            'info' => [
                'order_safe' => [
                    'source' => 'provider',
                    'status' => 'provider_redeem_pending',
                ],
            ],
        ]);

        $item = OrderItems::create([
            'key' => 'PENDING-'.$order->order_id,
            'uuid' => (string) Str::uuid(),
            'order_id' => $order->id,
            'activate_till' => now()->addYear()->format('Y-m-d'),
            'sku' => $product->sku,
            'count' => 1,
            'price_rub' => 100000,
            'type_form_id' => 2,
            'purchase_status' => 'pending',
            'client_info' => [
                'email' => 'sandbox@example.test',
                'provider_redemption' => ['status' => 'provider_redeem_pending'],
            ],
        ]);

        $snapshotId = '';
        $executionId = '';

        if ($pinSnapshot) {
            $snapshot = app(OfferSnapshotServiceInterface::class)->createFromProduct($product, 'sandbox_validation');
            $executionId = app(ExecutionRecordServiceInterface::class)->startExecution(
                $snapshot->id,
                $order->id,
                $item->id,
            );
            $snapshotId = $snapshot->id;

            $info = $order->info ?? [];
            data_set($info, 'order_safe.offer_snapshot_id', $snapshotId);
            data_set($info, 'order_safe.execution_record_id', $executionId);
            $order->forceFill(['info' => $info])->save();

            $clientInfo = $item->client_info ?? [];
            data_set($clientInfo, 'provider_redemption.offer_snapshot_id', $snapshotId);
            data_set($clientInfo, 'provider_redemption.execution_record_id', $executionId);
            $item->forceFill(['client_info' => $clientInfo])->save();
        }

        return [
            'shop' => $shop,
            'product' => $product,
            'catalog' => $catalog,
            'provider' => $provider,
            'order' => $order,
            'item' => $item,
            'snapshot_id' => $snapshotId,
            'execution_id' => $executionId,
        ];
    }
}
