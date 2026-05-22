<?php

namespace Tests\Feature;

use App\Models\LegalEntity;
use App\Models\Order\Order;
use App\Models\Order\OrderItems;
use App\Models\Provider;
use App\Models\Shop;
use App\Models\WildflowCatalog;
use App\Services\Provider\ProviderDriverInterface;
use App\Services\Provider\ProviderHub;
use App\Services\RedeemFallbackPurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class RedeemFallbackPurchaseServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_fallback_purchase_uses_provider_service_sku_and_persists_external_order_id(): void
    {
        $legalEntity = LegalEntity::create([
            'name' => 'Fallback Entity',
            'inn' => '770000000123',
            'available_balance' => 1000,
            'reserved_balance' => 100,
            'currency' => 'RUB',
            'is_active' => true,
        ]);

        $shop = new Shop([
            'name' => 'Fallback Shop',
            'domain' => 'fallback.test',
            'voucher_prefix' => 'FB',
            'is_active' => true,
        ]);
        $shop->legal_entity_id = $legalEntity->id;
        $shop->save();

        $order = Order::create([
            'order_id' => 'SL-ORDER-FALLBACK',
            'uuid' => 'order-fallback-uuid',
            'status' => 'PROCESSING',
            'shop_id' => $shop->id,
            'progress_id' => 2,
        ]);

        $item = OrderItems::create([
            'key' => 'FB-VOUCHER-1',
            'uuid' => 'item-fallback-uuid',
            'order_id' => $order->id,
            'activate_till' => now()->addYear()->format('Y-m-d'),
            'sku' => 'MARKET-SKU-1',
            'count' => 1,
            'purchase_status' => 'pending',
        ]);

        $catalog = WildflowCatalog::withoutEvents(fn () => WildflowCatalog::create([
            'sku' => 'MARKET-SKU-1',
            'service_sku' => 'PROVIDER-SERVICE-SKU-1',
            'retail_price' => 99.0,
            'type' => 'gift_card',
            'is_active' => true,
            'data' => [
                'data' => [
                    'sku' => 'PROVIDER-SERVICE-SKU-1',
                    'price' => 44.5,
                ],
            ],
        ]));

        $fallbackProvider = Provider::create([
            'name' => 'Fallback Wildflow',
            'type' => 'wildflow-sandbox',
            'is_active' => true,
            'credentials' => ['api_key' => 'fallback-token'],
        ]);
        config(['redeem.fallback_wildflow_provider_id' => $fallbackProvider->id]);

        $driver = Mockery::mock(ProviderDriverInterface::class);
        $driver->shouldReceive('createOrder')
            ->once()
            ->withArgs(function (string $sku, string $reference, float $price, int $quantity, array $meta) {
                return $sku === 'PROVIDER-SERVICE-SKU-1'
                    && str_ends_with($reference, '-fb1')
                    && $price === 44.5
                    && $quantity === 1
                    && $meta['is_fallback'] === true;
            })
            ->andReturn('FALLBACK-EXT-ORDER-1');
        $driver->shouldReceive('getCodes')
            ->once()
            ->with('FALLBACK-EXT-ORDER-1')
            ->andReturn(['FALLBACK-CODE-1']);

        $hub = Mockery::mock(ProviderHub::class);
        $hub->shouldReceive('forProvider')
            ->once()
            ->with(Mockery::on(fn (Provider $provider) => $provider->is($fallbackProvider)))
            ->andReturn($driver);
        app()->instance(ProviderHub::class, $hub);

        $code = app(RedeemFallbackPurchaseService::class)
            ->tryAlternateWildflowAfterPrimaryFailure($item, $catalog, 'primary failed');

        $this->assertSame('FALLBACK-CODE-1', $code);
        $this->assertSame('FALLBACK-EXT-ORDER-1', $item->fresh()->provider_order_id);
    }
}
