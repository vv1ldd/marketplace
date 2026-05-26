<?php

namespace Tests\Feature;

use App\Http\Controllers\OrderController;
use App\Models\LegalEntity;
use App\Models\Order\Order;
use App\Models\Order\OrderItems;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\Shop;
use App\Models\SovereignLedger;
use App\Models\WildflowCatalog;
use App\Services\SimpleLayer1TraceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class YandexMarketOrderIntakeTest extends TestCase
{
    use RefreshDatabase;

    public function test_yandex_market_order_intake_uses_sl1_history_and_issues_voucher_slip(): void
    {
        $legalEntity = LegalEntity::create([
            'name' => 'Meanly Yandex Entity',
            'short_name' => 'Meanly',
            'inn' => '770000009001',
            'available_balance' => 1000,
            'reserved_balance' => 0,
            'currency' => 'RUB',
            'is_active' => true,
        ]);

        $shop = new Shop([
            'name' => 'Meanly YM',
            'domain' => 'meanly-ym.test',
            'voucher_prefix' => 'MEAN',
            'business_id' => 900001,
            'campaign_id' => 900002,
            'notification_token' => 'ym-token',
            'api_key' => 'ym-api-key',
            'is_active' => true,
            'is_sandbox' => true,
        ]);
        $shop->legal_entity_id = $legalEntity->id;
        $shop->save();

        $sku = 'WF-YM-SYN-001';
        WildflowCatalog::create([
            'sku' => $sku,
            'service_sku' => 'EZPIN-YM-SYN-001',
            'retail_price' => 85,
            'type' => 'giftcard',
            'is_active' => true,
            'data' => [
                'product' => [
                    'title' => 'Synthetic YM Card',
                    'currency' => ['code' => 'RUB'],
                ],
            ],
        ]);

        Product::create([
            'shop_id' => $shop->id,
            'sku' => $sku,
            'wildflow_catalog_sku' => $sku,
            'name' => 'Synthetic YM Card',
            'price_rub' => 12000,
            'type' => 'giftcard',
            'is_active' => true,
        ]);

        $sourceOrderId = 9001001;
        $itemId = 777001;
        $orderInfo = [
            'id' => $sourceOrderId,
            'fake' => true,
            'items' => [[
                'id' => $itemId,
                'offerId' => $sku,
                'count' => 1,
                'price' => 120,
                'buyerPrice' => 120,
                'order_item_name' => 'Synthetic YM Card',
            ]],
            'buyerTotal' => 120,
            'currency' => 'RUR',
        ];
        $clientInfo = [
            'id' => 'ym-buyer-9001001',
            'firstName' => 'Yandex',
            'lastName' => 'Client',
            'email' => 'ym-client@example.test',
            'phone' => '+79990000000',
        ];

        $created = (new OrderController('ORDER_CREATED'))->created([
            'notificationType' => 'ORDER_CREATED',
            'orderId' => $sourceOrderId,
            'campaignId' => $shop->campaign_id,
            'shop_id' => $shop->id,
            'fake' => true,
            'order_full_info' => $orderInfo,
            'client_info' => $clientInfo,
            'is_manual_sync' => true,
        ]);

        $this->assertTrue($created['success']);
        $this->assertArrayNotHasKey('order_id', $created);
        $this->assertStringStartsWith('SL1-', $created['transaction_ref']);
        $this->assertSame('yandex_market', $created['source']['channel']);
        $this->assertSame((string) $sourceOrderId, $created['source']['order_id']);

        $order = Order::where('order_id', $sourceOrderId)->firstOrFail();
        $this->assertSame($created['transaction_ref'], $order->transactionReference());

        app(\App\Services\LedgerService::class)->record($shop, 'ORDER_STATUS_SNAPSHOT', $order, [
            'status' => 'PROCESSING',
            'source_order_id' => $sourceOrderId,
        ], $legalEntity);
        $this->assertSame($created['transaction_ref'], $order->fresh()->transactionReference());

        $updated = (new OrderController('ORDER_STATUS_UPDATED'))->updated([
            'notificationType' => 'ORDER_STATUS_UPDATED',
            'orderId' => $sourceOrderId,
            'campaignId' => $shop->campaign_id,
            'status' => 'PROCESSING',
            'substatus' => 'STARTED',
            'is_manual_sync' => true,
        ]);

        $this->assertTrue($updated['success']);
        $this->assertArrayNotHasKey('order_id', $updated);
        $this->assertStringStartsWith('SL1-', $updated['transaction_ref']);

        $orderItem = OrderItems::where('order_id', $order->id)->firstOrFail();
        $this->assertStringStartsWith('SL1-', $orderItem->transactionReference());
        $this->assertSame($sku, $orderItem->sku);
        $this->assertNotEmpty($orderItem->key);

        $inventory = ProductInventory::where('order_item_id', $orderItem->id)->firstOrFail();
        $this->assertSame('reserved', $inventory->status);
        $this->assertTrue($inventory->is_used);
        $this->assertSame(85.0, (float) $inventory->reserved_amount);
        $this->assertSame('RUB', $inventory->reserve_currency);
        $this->assertNotEmpty($inventory->reservation_reference);

        $legalEntity->refresh();
        $this->assertSame(915.0, (float) $legalEntity->available_balance);
        $this->assertSame(85.0, (float) $legalEntity->reserved_balance);

        $this->assertDatabaseHas('sovereign_ledger', [
            'event_type' => 'ORDER_RECEIVE',
            'entity_type' => Order::class,
            'entity_id' => $order->id,
        ]);
        $this->assertDatabaseHas('sovereign_ledger', [
            'event_type' => 'FINANCE_HOLD',
            'entity_type' => ProductInventory::class,
            'entity_id' => $inventory->id,
        ]);
        $this->assertDatabaseHas('sovereign_ledger', [
            'event_type' => 'VOUCHER_SLIP_ISSUED',
            'entity_type' => OrderItems::class,
            'entity_id' => $orderItem->id,
        ]);

        $slipEntry = SovereignLedger::where('event_type', 'VOUCHER_SLIP_ISSUED')
            ->where('entity_type', OrderItems::class)
            ->where('entity_id', $orderItem->id)
            ->firstOrFail();

        $trace = app(SimpleLayer1TraceService::class)->trace($slipEntry->transactionReference(), $legalEntity->id);
        $this->assertSame('Simple Layer One', $trace['network']);
        $this->assertSame($slipEntry->transactionReference(), $trace['canonical_ref']);
        $this->assertContains('ORDER_RECEIVE', collect($trace['entity_timeline'])->pluck('event_type'));
        $this->assertContains('FINANCE_HOLD', collect($trace['entity_timeline'])->pluck('event_type'));
        $this->assertContains('VOUCHER_SLIP_ISSUED', collect($trace['entity_timeline'])->pluck('event_type'));
    }
}
