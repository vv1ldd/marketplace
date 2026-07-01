<?php

namespace App\Services\Dgs;

use App\Models\Customer;
use App\Models\Order\Order;
use App\Models\Order\OrderItems;
use App\Models\User;

class DgsFulfillmentPayloadBuilder
{
    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    public function build(
        string $serviceSku,
        string $reference,
        float $price,
        int $quantity,
        array $meta = [],
    ): array {
        $buyerAddress = filled($meta['user_l1_address'] ?? null)
            ? strtolower(trim((string) $meta['user_l1_address']))
            : null;

        if ($buyerAddress === null) {
            throw new \RuntimeException('Node DGS fulfillment requires user_l1_address in driver meta.');
        }

        $orderId = (string) ($meta['order_uuid'] ?? $meta['marketplace_order_uuid'] ?? $reference);
        $skuBidx = (string) ($meta['sku_bidx'] ?? $serviceSku);
        $ezpinSku = $meta['ezpin_sku'] ?? $serviceSku;
        if (is_string($ezpinSku) && ctype_digit($ezpinSku)) {
            $ezpinSku = (int) $ezpinSku;
        }

        $productId = 'prod_'.preg_replace('/[^a-z0-9._:-]/', '_', strtolower($skuBidx));

        return [
            'order_id' => $orderId,
            'idempotency_key' => $reference,
            'strategy' => 'license_key',
            'buyer_address' => $buyerAddress,
            'product_id' => $productId,
            'paid_at_unix' => (int) ($meta['paid_at_unix'] ?? time()),
            'metadata' => array_filter([
                'sku' => $serviceSku,
                'service_sku' => $serviceSku,
                'ezpin_sku' => $ezpinSku,
                'ezpin_purchase_mode' => $meta['ezpin_purchase_mode'] ?? 'catalog',
                'quantity' => max(1, $quantity),
                'pre_order' => (bool) ($meta['pre_order'] ?? false),
                'destination' => $meta['email'] ?? $meta['destination'] ?? null,
                'default_price' => $price,
                'price' => $price,
            ], fn ($value) => $value !== null && $value !== ''),
        ];
    }

    public static function resolveBuyerAddress(Order $order, ?OrderItems $item = null, ?Customer $customer = null): ?string
    {
        $candidates = [
            data_get($order->info, 'user_l1_address'),
            data_get($order->client_info, 'user_l1_address'),
            data_get($item?->client_info, 'user_l1_address'),
            data_get($order->info, 'entity_l1_address'),
            data_get($order->client_info, 'entity_l1_address'),
        ];

        if ($order->relationLoaded('user') || $order->user) {
            $order->loadMissing(['user']);
            if ($order->user instanceof User) {
                $candidates[] = $order->user->sovereignIdentityAddress();
            }
        }

        if ($customer && method_exists($customer, 'sovereignIdentityAddress')) {
            $candidates[] = $customer->sovereignIdentityAddress();
        }

        foreach ($candidates as $candidate) {
            if (filled($candidate)) {
                return strtolower(trim((string) $candidate));
            }
        }

        return null;
    }
}
