<?php

namespace App\Services;

use App\Models\Order\Order;
use App\Models\Order\OrderItems;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MeanlyRetailCheckoutService
{
    public function __construct(
        private readonly MeanlyFirstPartyStorefrontService $storefront,
        private readonly LedgerService $ledger,
        private readonly SellerVoucherStockService $stock,
        private readonly StorefrontFulfillmentService $fulfillment,
    ) {}

    /**
     * @param array{name:string|null,email:string|null,phone:string|null,is_gift?:bool,buyer_user_id?:int|null,buyer_email?:string|null,buyer_l1_address?:string|null,delivery_email?:string|null} $customer
     * @param array<string, mixed> $payment
     * @return array<string, mixed>
     */
    public function checkout(Product $product, array $customer, int $quantity = 1, array $payment = []): array
    {
        $quantity = max(1, min($quantity, 5));
        $shop = $product->shop ?: $this->storefront->shop();
        $legalEntity = $shop->legalEntity;

        if (! $legalEntity) {
            throw ValidationException::withMessages(['product' => 'Meanly shop is not connected to a legal entity.']);
        }

        if (! $this->storefront->marketplaceProductsQuery()->whereKey($product->id)->exists()) {
            throw ValidationException::withMessages(['product' => 'Product is not available on the marketplace storefront.']);
        }

        return DB::transaction(function () use ($product, $shop, $legalEntity, $customer, $quantity, $payment) {
            $unitPriceRub = round(((float) ($product->price_rub ?? 0)) / 100, 2);
            if ($unitPriceRub <= 0) {
                throw ValidationException::withMessages(['product' => 'Product has no retail price.']);
            }

            $orderReference = 'MS-' . strtoupper(Str::random(10));
            $reservationReference = 'meanly-storefront:' . $orderReference;
            $totalRub = round($unitPriceRub * $quantity, 2);
            $paymentMethod = (string) ($payment['method'] ?? 'meanly_storefront_pending');
            $paymentStatus = (string) ($payment['status'] ?? 'pending');
            $paymentProof = $payment['simple_layer_one'] ?? null;
            $txHash = $payment['tx_hash'] ?? data_get($paymentProof, 'tx_hash');
            $txNonce = $payment['tx_nonce'] ?? data_get($paymentProof, 'tx_nonce');
            $captured = $paymentStatus === 'captured';
            $trustedCapture = $captured
                && $paymentMethod === 'buyer_wallet_rubt'
                && ! empty($payment['wallet_ledger_entry_id'])
                && is_array($paymentProof);

            if ($captured && ! $trustedCapture) {
                throw ValidationException::withMessages([
                    'payment' => 'Captured checkout requires a verified settlement proof.',
                ]);
            }

            $fulfillmentMode = (string) ($payment['fulfillment_mode'] ?? StorefrontFulfillmentService::FULFILLMENT_INSTANT);
            $requiresProviderExchange = $this->fulfillment->isProviderBacked($product);
            if ($fulfillmentMode === StorefrontFulfillmentService::FULFILLMENT_PREORDER) {
                throw ValidationException::withMessages([
                    'fulfillment_mode' => __('runtime.checkout.preorder_buy_only'),
                ]);
            }

            $fulfillmentMode = StorefrontFulfillmentService::FULFILLMENT_INSTANT;
            $preorderAcknowledged = (bool) ($payment['preorder_acknowledged'] ?? false);

            $searchLogId = null;
            $searchJourneyLogIds = [];
            try {
                if (session()->has('last_search_log_id')) {
                    $searchLogId = session()->get('last_search_log_id');
                }
                if (session()->has('search_journey_log_ids')) {
                    $searchJourneyLogIds = session()->get('search_journey_log_ids', []);
                }
            } catch (\Throwable $e) {
                // Ignore session exceptions in non-web context
            }

            if (empty($searchJourneyLogIds) && $searchLogId) {
                $searchJourneyLogIds = [$searchLogId];
            }


            $order = Order::create([
                'order_id' => $orderReference,
                'uuid' => Str::uuid()->toString(),
                'status' => $trustedCapture ? 'COMPLETED' : 'NEW',
                'sub_status' => 'DIRECT_STOREFRONT',
                'shop_id' => $shop->id,
                'progress_id' => $trustedCapture ? 4 : 1,
                'user_id' => $customer['buyer_user_id'] ?? null,
                'sales_channel' => $this->storefront->storefrontChannel(),
                'total_amount' => $totalRub,
                'currency' => 'RUB',
                'cost_amount' => $this->stock->reservationCostRub($product) * $quantity,
                'cost_currency' => 'RUB',
                'margin_base' => max(0, $totalRub - ($this->stock->reservationCostRub($product) * $quantity)),
                'search_log_id' => $searchLogId,
                'client_info' => [
                    'name' => $customer['name'] ?? null,
                    'email' => $customer['email'],
                    'phone' => $customer['phone'] ?? null,
                    'is_gift' => (bool) ($customer['is_gift'] ?? false),
                    'buyer_user_id' => $customer['buyer_user_id'] ?? null,
                    'buyer_email' => $customer['buyer_email'] ?? null,
                    'buyer_l1_address' => $customer['buyer_l1_address'] ?? null,
                    'delivery_email' => $customer['delivery_email'] ?? $customer['email'],
                ],
                'info' => [
                    'payment_method' => $paymentMethod,
                    'payment_status' => $paymentStatus,
                    'wallet_ledger_entry_id' => $payment['wallet_ledger_entry_id'] ?? null,
                    'tx_hash' => $txHash,
                    'tx_nonce' => $txNonce,
                    'checkout_payload_hash' => $payment['checkout_payload_hash'] ?? null,
                    'fulfillment_mode' => $fulfillmentMode,
                    'preorder_acknowledged' => $preorderAcknowledged,
                    'checkout_availability' => $payment['availability'] ?? null,
                    'items' => [[
                        'offerId' => $product->sku,
                        'count' => $quantity,
                        'price' => $unitPriceRub,
                    ]],
                ],
                'comment' => $paymentMethod === 'buyer_wallet_rubt'
                    ? 'Retail checkout through Meanly marketplace storefront with passkey-signed RUBT wallet payment.'
                    : 'Retail checkout through Meanly marketplace storefront pending verified payment.',
            ]);

            // Save multi-touch search journey attributions
            if (! empty($searchJourneyLogIds)) {
                $uniqueLogIds = array_values(array_unique($searchJourneyLogIds));
                $count = count($uniqueLogIds);
                foreach ($uniqueLogIds as $index => $logId) {
                    $touchType = 'middle';
                    if ($index === 0 && $count > 1) {
                        $touchType = 'first';
                    } elseif ($index === $count - 1) {
                        $touchType = 'last';
                    } elseif ($count === 1) {
                        $touchType = 'last';
                    }

                    $weight = 1.0 / $count;
                    $attributedGmv = round($totalRub * $weight, 2);

                    \App\Models\Order\OrderSearchAttribution::create([
                        'order_id' => $order->id,
                        'search_log_id' => $logId,
                        'touch_type' => $touchType,
                        'attribution_weight' => $weight,
                        'attributed_gmv' => $attributedGmv,
                    ]);
                }
            }


            $orderItem = OrderItems::create([
                'key' => $trustedCapture
                    ? VoucherEngine::issue($shop->voucher_prefix ?: 'MEAN', $product->sku)
                    : 'PENDING-'.$orderReference,
                'uuid' => Str::uuid()->toString(),
                'order_id' => $order->id,
                'activate_till' => now()->addYear()->format('Y-m-d'),
                'sku' => $product->sku,
                'count' => $quantity,
                'price_rub' => $unitPriceRub * 100,
                'price_try' => 0,
                'type_form_id' => 2,
                'purchase_status' => $trustedCapture ? 'success' : 'payment_pending',
                'client_info' => [
                    'email' => $customer['email'],
                    'delivery_email' => $customer['delivery_email'] ?? $customer['email'],
                    'is_gift' => (bool) ($customer['is_gift'] ?? false),
                    'buyer_user_id' => $customer['buyer_user_id'] ?? null,
                    'channel' => $this->storefront->storefrontChannel(),
                    'fulfillment_mode' => $fulfillmentMode,
                    'preorder_acknowledged' => $preorderAcknowledged,
                ],
            ]);

            $fulfillmentStatus = $trustedCapture
                ? ($requiresProviderExchange ? 'provider_redeem_pending' : 'local_code_ready')
                : 'payment_pending';
            $vouchers = [];
            $providerInventoryCheckout = $requiresProviderExchange
                && data_get($payment, 'availability.source') === 'provider_inventory'
                && data_get($payment, 'availability.status') === 'available';

            if (! $trustedCapture) {
                $vouchers = [];
            } elseif ($this->hasLocalVouchers($product, $shop, $quantity) || ! $requiresProviderExchange) {
                $vouchers = $this->reserveVouchers(
                    product: $product,
                    order: $order,
                    orderItem: $orderItem,
                    quantity: $quantity,
                    reservationReference: $reservationReference,
                    allowGenerate: ! $requiresProviderExchange,
                    finalBuyerCodes: ! $requiresProviderExchange,
                );
            } elseif ($providerInventoryCheckout) {
                $vouchers = [];
            } else {
                throw ValidationException::withMessages([
                    'availability' => __('runtime.checkout.no_seller_stock'),
                ]);
            }

            $firstVoucher = $vouchers[0]['code'] ?? null;
            if ($firstVoucher && ! $requiresProviderExchange) {
                $orderItem->update([
                    'key' => $firstVoucher,
                    'original_code' => $firstVoucher,
                ]);
            }

            if ($trustedCapture && $requiresProviderExchange) {
                $this->fulfillment->markProviderPending($order, $orderItem, $product, $fulfillmentMode);
                $order->refresh();
                $orderItem->refresh();
            }

            $this->ledger->record($shop, 'ORDER_RECEIVE', $order, [
                'source' => ['channel' => $this->storefront->storefrontChannel(), 'order_id' => $orderReference],
                'total_rub' => $totalRub,
                'quantity' => $quantity,
            ], $legalEntity);

            if ($trustedCapture) {
                $this->ledger->record($shop, 'FINANCE_CAPTURE', $order, [
                    'asset' => $paymentMethod === 'buyer_wallet_rubt' ? 'RUBT' : 'RUB',
                    'amount_rub' => $totalRub,
                    'token_amount' => $paymentMethod === 'buyer_wallet_rubt' ? $totalRub : null,
                    'reference' => $orderReference,
                    'payment_method' => $paymentMethod,
                    'wallet_ledger_entry_id' => $payment['wallet_ledger_entry_id'] ?? null,
                    'simple_layer_one' => $paymentProof,
                    'tx_hash' => $txHash,
                    'tx_nonce' => $txNonce,
                    'description' => $paymentMethod === 'buyer_wallet_rubt'
                        ? 'Meanly storefront RUBT wallet checkout captured after Passkey verification.'
                        : 'Meanly storefront retail checkout captured.',
                ], $legalEntity);

                $this->ledger->record($shop, 'VOUCHER_SLIP_ISSUED', $orderItem, [
                    'order_id' => $order->id,
                    'count' => $requiresProviderExchange ? $quantity : count($vouchers),
                    'channel' => $this->storefront->storefrontChannel(),
                    'stock_type' => $requiresProviderExchange ? 'seller_entitlement' : 'local_code',
                ], $legalEntity);

                $this->meterCheckout($legalEntity, $shop, $order, $totalRub, $quantity);
            }

            try {
                session()->forget('last_search_log_id');
                session()->forget('search_journey_log_ids');
            } catch (\Throwable $e) {
                // Ignore session exceptions
            }


            return [
                'order' => $order->refresh(),
                'order_item' => $orderItem->refresh(),
                'vouchers' => $requiresProviderExchange ? [] : $vouchers,
                'total_rub' => $totalRub,
                'fulfillment_status' => $fulfillmentStatus,
            ];
        });
    }

    private function hasLocalVouchers(Product $product, $shop, int $quantity): bool
    {
        $skuBidx = app(VaultTransitService::class)->computeBlindIndex((string) $product->sku);

        return ProductInventory::query()
            ->where('shop_id', $shop->id)
            ->where('sku_bidx', $skuBidx)
            ->where('is_used', false)
            ->where('status', 'available')
            ->whereNull('order_item_id')
            ->count() >= $quantity;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function reserveVouchers(
        Product $product,
        Order $order,
        OrderItems $orderItem,
        int $quantity,
        string $reservationReference,
        bool $allowGenerate = true,
        bool $finalBuyerCodes = true,
    ): array
    {
        $shop = $order->shop;
        $skuBidx = app(VaultTransitService::class)->computeBlindIndex((string) $product->sku);
        $warehouseId = Warehouse::query()->where('shop_id', $shop->id)->where('is_main', true)->value('id')
            ?? Warehouse::query()->where('shop_id', $shop->id)->value('id');

        $reserved = [];
        $inventoryIds = [];
        for ($i = 0; $i < $quantity; $i++) {
            $inventory = ProductInventory::query()
                ->where('shop_id', $shop->id)
                ->where('sku_bidx', $skuBidx)
                ->where('is_used', false)
                ->where('status', 'available')
                ->whereNull('order_item_id')
                ->lockForUpdate()
                ->first();

            if (! $inventory) {
                if (! $allowGenerate) {
                    throw ValidationException::withMessages([
                        'availability' => __('runtime.checkout.no_entitlement'),
                    ]);
                }

                $voucher = VoucherEngine::issueDeterministic(
                    issuerPrefix: $shop->voucher_prefix ?: 'MEAN',
                    sku: (string) $product->sku,
                    reference: "{$reservationReference}:{$i}",
                    issuedAt: $order->created_at ?? now(),
                );

                $inventory = ProductInventory::create([
                    'shop_id' => $shop->id,
                    'warehouse_id' => $warehouseId,
                    'sku' => $product->sku,
                    'nominal_amount' => ((float) ($product->price_rub ?? 0)) / 100,
                    'nominal_currency' => 'RUB',
                    'voucher' => $voucher,
                    'is_used' => false,
                    'status' => 'available',
                    'expires_at' => now()->addYear(),
                ]);
            }

            $inventory->update([
                'is_used' => true,
                'status' => $finalBuyerCodes ? 'sold' : 'reserved',
                'order_item_id' => $orderItem->id,
                'reservation_reference' => "{$reservationReference}:{$i}",
                'reserved_amount' => ((float) ($product->price_rub ?? 0)) / 100,
                'reserve_currency' => 'RUB',
                'reserved_at' => now(),
            ]);

            $this->ledger->record($shop, 'STOCK_RESERVE', $inventory, [
                'order_id' => $order->id,
                'order_item_id' => $orderItem->id,
                'sku' => $product->sku,
                'reservation_reference' => "{$reservationReference}:{$i}",
                'context' => 'meanly_storefront_checkout',
                'stock_type' => $finalBuyerCodes ? 'local_code' : 'seller_entitlement',
            ], $shop->legalEntity);

            $inventoryIds[] = $inventory->id;

            if ($finalBuyerCodes) {
                $reserved[] = [
                    'code' => $inventory->voucher,
                    'redeem_url' => route('redeem.code', ['code' => $inventory->voucher]),
                ];
            }
        }

        if (! $finalBuyerCodes) {
            $clientInfo = $orderItem->client_info ?? [];
            data_set($clientInfo, 'local_entitlement.status', 'reserved');
            data_set($clientInfo, 'local_entitlement.type', 'provider_exchange');
            data_set($clientInfo, 'local_entitlement.inventory_ids', $inventoryIds);
            data_set($clientInfo, 'local_entitlement.reservation_reference', $reservationReference);
            data_set($clientInfo, 'local_entitlement.reserved_at', now()->toJSON());
            $orderItem->forceFill(['client_info' => $clientInfo])->save();
        }

        return $reserved;
    }

    private function meterCheckout($legalEntity, $shop, Order $order, float $totalRub, int $quantity): void
    {
        try {
            $metering = app(TokenMeteringService::class);
            $metering->meter($legalEntity, 'order_fulfillment', $order, $quantity, $shop, [
                'order_id' => $order->order_id,
                'sales_channel' => $this->storefront->storefrontChannel(),
                'gmv_rub' => $totalRub,
                'idempotency_key' => 'meanly-storefront-fulfillment:'.$order->order_id,
            ]);

            $successFeeRub = round($totalRub * 0.005, 2);
            $metering->meter($legalEntity, 'marketplace_success_fee', $order, 1, $shop, [
                'order_id' => $order->order_id,
                'sales_channel' => $this->storefront->storefrontChannel(),
                'gmv_rub' => $totalRub,
                'fee_bps' => 50,
                'fee_rub' => $successFeeRub,
                'sl1_amount' => round($successFeeRub / (float) config('sl1_tokenomics.rub_rate', 100.0), 4),
                'idempotency_key' => 'meanly-storefront-success-fee:'.$order->order_id,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
