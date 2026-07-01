<?php

namespace App\Services;

use App\Models\Order\Order;
use App\Models\Order\OrderItems;
use App\Models\Product;
use App\Models\Provider;
use App\Models\ProviderProduct;
use App\Models\User;
use App\Models\WildflowCatalog;
use App\Services\Provider\WildflowDriver;

class DgsShadowIngestDispatcher
{
    public function __construct(
        private readonly DgsShadowIngestService $ingest
    ) {}

    /**
     * @param  array<int, string>|array<int, array<string, mixed>>  $codesOrCards
     */
    public function dispatchFromProviderFulfillment(
        Order $order,
        OrderItems $item,
        Product $product,
        Provider $provider,
        ?ProviderProduct $providerProduct,
        ?WildflowCatalog $catalog,
        array $codesOrCards,
        string $externalOrderId,
        ?WildflowDriver $driver = null
    ): void {
        if (! $this->shouldDispatchForProvider($provider)) {
            return;
        }

        $legacyCards = $this->resolveLegacyCards($driver, $externalOrderId, $codesOrCards);
        if ($legacyCards === []) {
            return;
        }

        $phpOrder = $this->resolvePhpOrder($item, $externalOrderId);
        $mpOrder = $this->resolveMpOrder($order, $item, $externalOrderId);
        $mpProduct = $this->resolveMpProduct($product, $providerProduct, $catalog);

        $this->ingest->fireShadowIngest($phpOrder, $mpOrder, $mpProduct, $legacyCards);
    }

    private function shouldDispatchForProvider(Provider $provider): bool
    {
        return in_array((string) $provider->type, [
            'ezpin',
            'ezpin-sandbox',
            'wildflow',
            'wildflow-sandbox',
        ], true);
    }

    /**
     * @param  array<int, string>|array<int, array<string, mixed>>  $codesOrCards
     * @return array<int, array<string, mixed>>
     */
    private function resolveLegacyCards(?WildflowDriver $driver, string $externalOrderId, array $codesOrCards): array
    {
        if ($driver) {
            try {
                $normalized = $driver->getNormalizedCards($externalOrderId);
                if ($normalized !== []) {
                    return $normalized;
                }
            } catch (\Throwable) {
                // Fall back to codes already fetched for checkout.
            }
        }

        return collect($codesOrCards)
            ->map(function ($entry) {
                if (is_array($entry)) {
                    return $entry;
                }

                return filled($entry) ? ['pin_code' => (string) $entry] : null;
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function resolvePhpOrder(OrderItems $item, string $externalOrderId): array
    {
        $aggregatorStatus = data_get($item->client_info, 'provider_redemption.aggregator_order.status');

        return [
            'reference' => $externalOrderId,
            'status' => filled($aggregatorStatus) ? (string) $aggregatorStatus : 'accepted',
            'is_completed' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveMpOrder(Order $order, OrderItems $item, string $externalOrderId): array
    {
        $order->loadMissing(['user']);

        return [
            'uuid' => (string) ($item->uuid ?: $order->uuid ?: $order->order_id),
            'idempotency_key' => $externalOrderId,
            'user_l1_address' => $this->resolveBuyerAddress($order, $item),
            'paid_at_unix' => optional($order->created_at)->timestamp,
            'quantity' => max(1, (int) $item->count),
            'metadata' => array_filter([
                'destination' => data_get($item->client_info, 'delivery_email')
                    ?: data_get($item->client_info, 'email'),
                'price' => data_get($item->client_info, 'amount')
                    ?: data_get($item->client_info, 'meta.amount'),
                'order_id' => (string) $order->order_id,
            ], fn ($value) => $value !== null && $value !== ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveMpProduct(Product $product, ?ProviderProduct $providerProduct, ?WildflowCatalog $catalog): array
    {
        $ezpinSku = data_get($providerProduct?->data, 'ezpin_sku')
            ?? data_get($providerProduct?->data, 'sku')
            ?? data_get($catalog?->data, 'ezpin_sku')
            ?? data_get($catalog?->data, 'provider_sku');

        if (is_string($ezpinSku) && ctype_digit($ezpinSku)) {
            $ezpinSku = (int) $ezpinSku;
        }

        return [
            'type' => (string) ($product->type ?: 'gift_card'),
            'sku_bidx' => (string) ($product->wildflow_catalog_sku ?: $product->sku),
            'ezpin_sku' => $ezpinSku,
            'ezpin_purchase_mode' => data_get($providerProduct?->data, 'purchase_mode', 'catalog'),
            'default_price' => data_get($providerProduct?->data, 'default_price')
                ?? data_get($catalog?->data, 'default_price')
                ?? $product->retail_price,
        ];
    }

    private function resolveBuyerAddress(Order $order, OrderItems $item): ?string
    {
        $candidates = [
            data_get($order->info, 'user_l1_address'),
            data_get($order->client_info, 'user_l1_address'),
            data_get($item->client_info, 'user_l1_address'),
            data_get($order->info, 'entity_l1_address'),
            data_get($order->client_info, 'entity_l1_address'),
        ];

        if ($order->user instanceof User) {
            $candidates[] = $order->user->sovereignIdentityAddress();
        }

        foreach ($candidates as $candidate) {
            if (filled($candidate)) {
                return strtolower(trim((string) $candidate));
            }
        }

        return null;
    }
}
