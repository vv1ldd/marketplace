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
use App\Support\PricingContext;
use App\Services\Architecture\ArchitectureMetrics;
use App\Services\Architecture\ExecutionRecordServiceInterface;
use App\Services\Architecture\OfferSnapshotServiceInterface;
use App\Services\MeanlyFirstPartyStorefrontService;
use App\Services\StorefrontFulfillmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Support\FakeProviderGateway;
use Tests\TestCase;

class ExecutionCausalityPressureTest extends TestCase
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
        ]);
        app()->instance(PricingContext::class, new PricingContext('global', 'RUB', 'RUB', 'RUB'));
        FakeProviderGateway::boot();
        FakeProviderGateway::expectSandbox();
    }

    public function test_pc1_provider_500_does_not_touch_knowledge(): void
    {
        $seed = $this->seedProviderCheckout();
        FakeProviderGateway::expectSandbox(orderStatus: 500, orderPayload: ['error' => 'internal']);

        $result = app(StorefrontFulfillmentService::class)->fulfillProviderOrder($seed['order']->fresh());

        $this->assertSame('provider_redeem_failed', $result['status']);

        $execution = ExecutionRecord::query()->findOrFail($seed['execution_id']);
        $this->assertSame(ExecutionRecord::STATE_FAILED, $execution->state);
        $this->assertSame('PROVIDER_500', $execution->error_class);

        $identity = CanonicalProductIdentity::query()->findOrFail($seed['identity']->id);
        $this->assertSame($seed['identity_fingerprint'], $identity->fingerprint);

        $catalog = WildflowCatalog::query()->findOrFail($seed['catalog']->id);
        $this->assertTrue((bool) $catalog->is_active);
    }

    public function test_pc2_payment_ok_fulfillment_fail(): void
    {
        $seed = $this->seedProviderCheckout(paymentCaptured: true);
        FakeProviderGateway::expectSandbox(orderStatus: 500, orderPayload: ['error' => 'internal']);

        app(StorefrontFulfillmentService::class)->fulfillProviderOrder($seed['order']->fresh());

        $seed['order']->refresh();
        $seed['item']->refresh();
        $execution = ExecutionRecord::query()->findOrFail($seed['execution_id']);

        $this->assertNotSame(ExecutionRecord::STATE_ISSUED, $execution->state);
        $this->assertNull($execution->vault_reference_id);
        $this->assertSame([], app(StorefrontFulfillmentService::class)->codesFromItem($seed['item']));
        $this->assertTrue((bool) $seed['order']->is_problem || $seed['item']->purchase_status === 'failed');
    }

    public function test_pc4_catalog_rebuild_mid_flight(): void
    {
        $seed = $this->seedProviderCheckout();
        $this->withArchitectureSnapshotFulfillment();

        $seed['catalog']->update([
            'data' => array_merge((array) $seed['catalog']->data, ['service_sku' => 'REBUILT-SKU-999']),
            'service_sku' => 'REBUILT-SKU-999',
        ]);

        FakeProviderGateway::expectSandbox();

        app(StorefrontFulfillmentService::class)->fulfillProviderOrder($seed['order']->fresh());

        $this->assertSame('4401', FakeProviderGateway::dispatchedServiceSku());
        $this->assertSame(0, ArchitectureMetrics::count('architecture.execution.fallback_live_catalog_count'));
    }

    public function test_pc5_sku_deactivated_after_reservation(): void
    {
        $seed = $this->seedProviderCheckout();
        $this->withArchitectureSnapshotFulfillment();

        $seed['catalog']->update(['is_active' => false]);

        FakeProviderGateway::expectSandbox();

        $result = app(StorefrontFulfillmentService::class)->fulfillProviderOrder($seed['order']->fresh());

        $this->assertSame('provider_code_ready', $result['status']);
        $this->assertSame('4401', FakeProviderGateway::dispatchedServiceSku());
        $this->assertSame('FAKE-CODE-001', $seed['item']->fresh()->original_code);
    }

    public function test_select_offer_pins_snapshot_for_entitlement(): void
    {
        $seed = $this->seedProviderCheckout(pinSnapshot: false);
        $offers = app(\App\Services\Architecture\OfferRoutingService::class)
            ->availableOffersForEntitlement($seed['identity']);

        $snapshot = app(\App\Services\Architecture\OfferRoutingService::class)->selectOffer(
            'best_offer',
            $seed['identity'],
            $offers,
        );

        $this->assertNotNull($snapshot);
        $this->assertDatabaseHas('offer_snapshots', ['id' => $snapshot->id]);
    }

    /**
     * @return array{
     *     shop: Shop,
     *     product: Product,
     *     catalog: WildflowCatalog,
     *     provider: Provider,
     *     provider_product: ProviderProduct,
     *     identity: CanonicalProductIdentity,
     *     identity_fingerprint: string,
     *     order: Order,
     *     item: OrderItems,
     *     snapshot_id: string,
     *     execution_id: string
     * }
     */
    private function seedProviderCheckout(bool $paymentCaptured = false, bool $pinSnapshot = true): array
    {
        FakeProviderGateway::expectSandbox();

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
        $legalEntity = $shop->legalEntity;

        $catalogSku = 'WF-PC-'.Str::upper(Str::random(6));
        $catalog = WildflowCatalog::create([
            'sku' => $catalogSku,
            'reward_type' => 'Gift-Card',
            'retail_price' => 10.0,
            'purchase_price' => 8.0,
            'is_active' => true,
            'provider_id' => $provider->id,
            'service_sku' => '4401',
            'type' => 'unified_catalog',
            'data' => [
                'service_sku' => '4401',
                'display_name' => 'Pressure Catalog SKU',
            ],
        ]);

        $providerProduct = ProviderProduct::create([
            'provider_id' => $provider->id,
            'sku' => $catalogSku,
            'market_sku' => $catalogSku,
            'name' => 'Pressure Provider Product',
            'category' => 'Gift-Card',
            'purchase_price' => 8.0,
            'retail_price' => 10.0,
            'currency' => 'USD',
            'is_active' => true,
            'data' => ['service_sku' => '4401'],
        ]);

        $product = Product::create([
            'shop_id' => $shopId,
            'sku' => 'SELLER-'.$catalogSku,
            'name' => 'Pressure Seller Listing',
            'slug' => Str::slug('pressure-'.$catalogSku),
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

        $identity = CanonicalProductIdentity::create([
            'fingerprint' => hash('sha256', 'pressure-identity-'.$catalogSku),
            'identity_slug' => 'pressure-'.$catalogSku,
            'canonical_category' => 'gift_cards',
            'brand' => 'Pressure',
            'product_family' => 'Pressure',
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
            'order_id' => 'PC-'.Str::upper(Str::random(8)),
            'uuid' => (string) Str::uuid(),
            'status' => $paymentCaptured ? 'COMPLETED' : 'PROCESSING',
            'shop_id' => $shopId,
            'progress_id' => $paymentCaptured ? 4 : 2,
            'sales_channel' => $storefront->storefrontChannel(),
            'total_amount' => 1000,
            'currency' => 'RUB',
            'info' => [
                'payment_status' => $paymentCaptured ? 'captured' : 'pending',
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
                'email' => 'buyer@pressure.test',
                'provider_redemption' => ['status' => 'provider_redeem_pending'],
            ],
        ]);

        $snapshotId = '';
        $executionId = '';

        if ($pinSnapshot) {
            config(['architecture.sidecar_enabled' => true]);
            $snapshot = app(OfferSnapshotServiceInterface::class)->createFromProduct($product, 'pressure_test');
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
            'legal_entity' => $legalEntity,
            'product' => $product,
            'catalog' => $catalog,
            'provider' => $provider,
            'provider_product' => $providerProduct,
            'identity' => $identity,
            'identity_fingerprint' => (string) $identity->fingerprint,
            'order' => $order,
            'item' => $item,
            'snapshot_id' => $snapshotId,
            'execution_id' => $executionId,
        ];
    }
}
