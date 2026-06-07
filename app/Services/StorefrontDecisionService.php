<?php

namespace App\Services;

use App\Models\Order\Order;
use App\Models\Product;
use Illuminate\Support\Facades\URL;

class StorefrontDecisionService
{
    /**
     * @param  array<string, mixed>  $availability
     * @return array<string, mixed>
     */
    public function checkoutDecision(Product $product, array $availability): array
    {
        $checkoutAllowed = (string) ($availability['status'] ?? 'unavailable') === 'available';

        return [
            'ctg_version' => StorefrontTransitionRegistry::VERSION,
            'transition_id' => $checkoutAllowed
                ? StorefrontTransitionRegistry::CHECKOUT_ALLOWED
                : StorefrontTransitionRegistry::CHECKOUT_BLOCKED,
            'product_id' => (string) $product->id,
            'availability_status' => (string) ($availability['status'] ?? 'unavailable'),
            'checkout_allowed' => $checkoutAllowed,
            'fulfillment_mode' => $checkoutAllowed ? StorefrontFulfillmentService::FULFILLMENT_INSTANT : null,
            'allowed_actions' => $checkoutAllowed ? ['VIEW', 'ADD_TO_CART', 'CHECKOUT'] : ['VIEW'],
            'blocked_actions' => $checkoutAllowed ? [] : ['ADD_TO_CART', 'CHECKOUT'],
            'next_action' => $checkoutAllowed ? 'CHECKOUT' : 'VIEW',
            'blocking_reason' => $checkoutAllowed ? null : (string) ($availability['reason'] ?? 'unavailable'),
            'source' => (string) ($availability['source'] ?? 'unknown'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function orderSafeDecision(Order $order): array
    {
        $order->loadMissing('items');
        $paymentStatus = (string) data_get($order->info, 'payment_status', '');
        $paid = $paymentStatus === 'captured'
            || in_array((string) $order->status, ['COMPLETED', 'PROCESSING'], true)
            || (bool) data_get($order->info, 'wallet_ledger_entry_id');
        $safeStatus = (string) data_get($order->info, 'order_safe.status', '');
        $safeSource = (string) data_get($order->info, 'order_safe.source', 'local');
        $failed = in_array((string) $order->status, ['FAILED', 'CANCELLED'], true)
            || $order->items->contains(fn ($item): bool => (string) $item->purchase_status === 'failed')
            || $safeStatus === 'provider_redeem_failed';
        $hasCodes = $order->items->contains(fn ($item): bool => filled($item->original_code));
        $ready = $paid && $hasCodes && ! $failed;
        $status = $this->safeStatus($paid, $ready, $failed, $safeSource, $safeStatus);

        return [
            'ctg_version' => StorefrontTransitionRegistry::VERSION,
            'transition_id' => $this->orderSafeTransitionId($status, $ready),
            'order_id' => $order->order_id,
            'status' => $status,
            'paid' => $paid,
            'ready' => $ready,
            'failed' => $failed,
            'total_rub' => (float) $order->total_amount,
            'allowed_actions' => $ready ? ['VIEW', 'OPEN_SAFE'] : ['VIEW'],
            'blocked_actions' => $ready ? [] : ['OPEN_SAFE'],
            'next_action' => $ready ? 'OPEN_SAFE' : 'WAIT_FOR_BACKEND_STATE',
            'blocking_reason' => $ready ? null : $status,
            'links' => [
                'safe' => URL::signedRoute('meanly.storefront.orders.safe.show', ['order' => $order->uuid]),
                'status' => URL::signedRoute('meanly.storefront.orders.safe.status', ['order' => $order->uuid]),
            ],
        ];
    }

    private function safeStatus(bool $paid, bool $ready, bool $failed, string $safeSource, string $safeStatus): string
    {
        if ($failed) {
            return $safeSource === 'provider' ? 'provider_redeem_failed' : 'failed';
        }

        if ($ready) {
            return $safeSource === 'provider' ? 'provider_code_ready' : 'local_code_ready';
        }

        if ($paid) {
            return $safeSource === 'provider' ? 'provider_redeem_pending' : 'preparing';
        }

        return 'payment_pending';
    }

    private function orderSafeTransitionId(string $status, bool $ready): string
    {
        if ($ready) {
            return StorefrontTransitionRegistry::OPEN_SAFE;
        }

        if ($status === 'payment_pending') {
            return StorefrontTransitionRegistry::PAYMENT_PENDING;
        }

        return $status === 'failed' || $status === 'provider_redeem_failed'
            ? StorefrontTransitionRegistry::FORBIDDEN
            : StorefrontTransitionRegistry::WAIT_FOR_BACKEND_STATE;
    }
}
