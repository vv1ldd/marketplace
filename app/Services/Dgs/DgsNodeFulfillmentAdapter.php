<?php

namespace App\Services\Dgs;

class DgsNodeFulfillmentAdapter
{
    /**
     * @param  array<string, mixed>  $nodeResponse
     * @return array<string, mixed>
     */
    public function normalizeOrderResponse(array $nodeResponse, string $reference): array
    {
        $issued = ($nodeResponse['status'] ?? null) === 'ISSUED';

        return [
            'referenceCode' => $reference,
            'order_id' => $reference,
            'status' => $issued ? 1 : 0,
            'status_text' => $issued ? 'accept' : (string) ($nodeResponse['status'] ?? 'pending'),
            'is_completed' => $issued,
            'node_fulfillment' => [
                'fulfillment_id' => $nodeResponse['fulfillment_id'] ?? null,
                'status' => $nodeResponse['status'] ?? null,
                'receipt_hash' => $nodeResponse['receipt_hash'] ?? null,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $nodeResponse
     * @return array<int, array<string, mixed>>
     */
    public function normalizedCards(array $nodeResponse): array
    {
        if (($nodeResponse['status'] ?? null) !== 'ISSUED') {
            return [];
        }

        $licenseKey = data_get($nodeResponse, 'payload.license_key');
        if (! filled($licenseKey)) {
            return [];
        }

        return [[
            'pin_code' => (string) $licenseKey,
            'pinCode' => (string) $licenseKey,
            'code' => (string) $licenseKey,
        ]];
    }
}
