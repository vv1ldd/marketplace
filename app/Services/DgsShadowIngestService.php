<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DgsShadowIngestService
{
    /**
     * Push a shadow parity event to the Series 5 sidecar without blocking checkout.
     *
     * @param  array<string, mixed>  $phpOrder
     * @param  array<string, mixed>  $mpOrderData
     * @param  array<string, mixed>  $mpProductData
     * @param  array<int, array<string, mixed>>  $legacyCards
     */
    public function fireShadowIngest(array $phpOrder, array $mpOrderData, array $mpProductData, array $legacyCards): void
    {
        $ingestUrl = trim((string) config('services.dgs_shadow.ingest_url', ''));

        if ($ingestUrl === '') {
            return;
        }

        try {
            $payload = [
                'mp_order' => [
                    'uuid' => $mpOrderData['uuid'] ?? null,
                    'idempotency_key' => $mpOrderData['idempotency_key'] ?? ($mpOrderData['uuid'] ?? null),
                    'user_l1_address' => $mpOrderData['user_l1_address'] ?? null,
                    'paid_at_unix' => $mpOrderData['paid_at_unix'] ?? null,
                    'quantity' => $mpOrderData['quantity'] ?? 1,
                    'metadata' => $mpOrderData['metadata'] ?? [],
                ],
                'mp_product' => [
                    'type' => $mpProductData['type'] ?? 'gift_card',
                    'sku_bidx' => $mpProductData['sku_bidx'] ?? null,
                    'ezpin_sku' => $mpProductData['ezpin_sku'] ?? null,
                    'ezpin_purchase_mode' => $mpProductData['ezpin_purchase_mode'] ?? 'catalog',
                    'default_price' => $mpProductData['default_price'] ?? null,
                ],
                'legacy_normalized_cards' => [
                    'cards' => collect($legacyCards)
                        ->map(function (array $card) {
                            $pin = $card['pin_code'] ?? $card['pinCode'] ?? $card['code'] ?? null;

                            return filled($pin) ? ['pin_code' => (string) $pin] : null;
                        })
                        ->filter()
                        ->values()
                        ->all(),
                ],
                'legacy_php_order' => [
                    'reference' => $phpOrder['reference'] ?? null,
                    'status' => $phpOrder['status'] ?? 'accepted',
                    'is_completed' => $phpOrder['is_completed'] ?? true,
                ],
            ];

            $timeout = max(1, (int) config('services.dgs_shadow.timeout_seconds', 1));

            Http::timeout($timeout)
                ->retry(1, 100, throw: false)
                ->acceptJson()
                ->post($ingestUrl, $payload);
        } catch (\Throwable $e) {
            Log::warning('DGS Shadow Ingest failed: '.$e->getMessage(), [
                'ingest_url' => $ingestUrl,
                'reference' => $phpOrder['reference'] ?? null,
            ]);
        }
    }
}
