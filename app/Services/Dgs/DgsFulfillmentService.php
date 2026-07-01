<?php

namespace App\Services\Dgs;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DgsFulfillmentService
{
    public function fulfillmentMode(): string
    {
        return (string) config('services.dgs.fulfillment_mode', 'http');
    }

    public function issue(array $payload): array
    {
        $endpoint = rtrim((string) config('services.dgs.fulfillment_url'), '/').'/api/v1/fulfillment/issue';
        $timeout = (int) config('services.dgs.fulfillment_timeout', 60);
        $attempts = 0;
        $response = null;
        $startedAt = hrtime(true);

        do {
            $attempts++;
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->post($endpoint, $payload);
        } while ($attempts < 2 && in_array($response->status(), [502, 503], true));

        $durationMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);

        if ($response->failed()) {
            Log::error('DGS fulfillment issue failed', [
                'type' => 'dgs_fulfillment_latency',
                'duration_ms' => $durationMs,
                'attempts' => $attempts,
                'status' => $response->status(),
                'body' => $response->body(),
                'order_id' => $payload['order_id'] ?? null,
                'idempotency_key' => $payload['idempotency_key'] ?? null,
            ]);

            throw new \RuntimeException('Node DGS rejected fulfillment payload or returned upstream error');
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new \RuntimeException('Node DGS returned a non-JSON fulfillment response');
        }

        Log::info('DGS fulfillment issue completed', [
            'type' => 'dgs_fulfillment_latency',
            'duration_ms' => $durationMs,
            'attempts' => $attempts,
            'node_status' => $json['status'] ?? null,
            'fulfillment_id' => $json['fulfillment_id'] ?? null,
            'order_id' => $payload['order_id'] ?? null,
            'idempotency_key' => $payload['idempotency_key'] ?? null,
        ]);

        return $json;
    }
}
