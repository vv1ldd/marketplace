<?php

namespace Tests\Feature;

use App\Models\LegalEntity;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\Shop;
use App\Services\L1StateService;
use App\Services\SellerVoucherStockService;
use App\Services\VoucherEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoucherReservationLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_deterministic_voucher_reservations_are_replayed_from_l1_and_define_stock_capacity(): void
    {
        $entity = LegalEntity::create([
            'name' => 'Meanly Ledger Entity',
            'short_name' => 'Meanly',
            'inn' => '770000000111',
            'available_balance' => 255,
            'reserved_balance' => 0,
            'currency' => 'RUB',
            'is_active' => true,
        ]);

        $shop = new Shop([
            'name' => 'MEANLY',
            'domain' => 'meanly.test',
            'voucher_prefix' => 'MEAN',
            'ym_stock' => 4,
            'is_active' => true,
        ]);
        $shop->legal_entity_id = $entity->id;
        $shop->save();

        $product = Product::create([
            'shop_id' => $shop->id,
            'sku' => 'WF-SBX-LEDGER-001',
            'name' => 'Ledger Test Card',
            'price_rub' => 8500,
            'type' => 'giftcard',
            'is_active' => true,
        ]);

        ProductInventory::create([
            'shop_id' => $shop->id,
            'sku' => $product->sku,
            'voucher' => 'MEAN-PHYSICAL-ONE',
            'is_used' => false,
            'status' => 'available',
        ]);
        ProductInventory::create([
            'shop_id' => $shop->id,
            'sku' => $product->sku,
            'voucher' => 'MEAN-PHYSICAL-TWO',
            'is_used' => false,
            'status' => 'available',
        ]);

        $reference = 'ym:ORDER-1:ITEM-1:'.hash('sha256', $product->sku);
        $issuedAt = now()->setSecond(0);

        $this->assertSame(
            VoucherEngine::issueDeterministic('MEAN', $product->sku, $reference, $issuedAt),
            VoucherEngine::issueDeterministic('MEAN', $product->sku, $reference, $issuedAt)
        );

        $depositEntry = app(\App\Services\LedgerService::class)->record(
            $shop,
            'DEPOSIT_INTENT_CLEARED',
            $entity,
            ['amount' => 255],
            $entity
        );
        $depositRef = $depositEntry->transactionReference();
        $this->assertStringStartsWith('SL1-', $depositRef);
        $this->assertSame(21, strlen($depositRef));

        $trace = app(\App\Services\SimpleLayer1TraceService::class)->trace($depositRef, $entity->id);
        $this->assertSame($depositRef, $trace['canonical_ref']);
        $this->assertSame('Simple Layer One', $trace['network']);
        $this->assertSame('DEPOSIT_INTENT_CLEARED', $trace['target']['event_type']);

        $legacyTrace = app(\App\Services\SimpleLayer1TraceService::class)->trace('L1-'.substr($depositRef, 4), $entity->id);
        $this->assertSame($depositRef, $legacyTrace['canonical_ref']);

        $capacity = app(SellerVoucherStockService::class)->capacityForProduct($product, $shop);
        $this->assertSame(2, $capacity['physical']);
        $this->assertSame(3, $capacity['virtual']);
        $this->assertSame(4, $capacity['total']);

        app(\App\Services\LedgerService::class)->record($shop, 'FINANCE_HOLD', $product, [
            'amount_rub' => 85,
            'reservation_reference' => $reference,
        ], $entity);

        $state = app(L1StateService::class)->reconstructBalance($entity);
        $this->assertSame(170.0, $state['available_balance']);
        $this->assertSame(85.0, $state['reserved_balance']);

        app(\App\Services\LedgerService::class)->record($shop, 'FINANCE_CAPTURE', $product, [
            'amount_rub' => 85,
            'reservation_reference' => $reference,
        ], $entity);

        $state = app(L1StateService::class)->reconstructBalance($entity);
        $this->assertSame(170.0, $state['available_balance']);
        $this->assertSame(0.0, $state['reserved_balance']);

        app(\App\Services\LedgerService::class)->record($shop, 'FINANCE_HOLD', $product, [
            'amount_rub' => 85,
            'reservation_reference' => $reference.'-release',
        ], $entity);
        app(\App\Services\LedgerService::class)->record($shop, 'FINANCE_RELEASE', $product, [
            'amount_rub' => 85,
            'reservation_reference' => $reference.'-release',
        ], $entity);

        $state = app(L1StateService::class)->reconstructBalance($entity);
        $this->assertSame(170.0, $state['available_balance']);
        $this->assertSame(0.0, $state['reserved_balance']);
    }
}
