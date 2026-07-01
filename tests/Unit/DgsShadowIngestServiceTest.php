<?php

namespace Tests\Unit;

use App\Services\DgsShadowIngestService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DgsShadowIngestServiceTest extends TestCase
{
    public function test_fire_shadow_ingest_posts_canonical_payload_when_url_is_configured(): void
    {
        config([
            'services.dgs_shadow.ingest_url' => 'http://dgs-node-sidecar:8092/shadow/ingest',
            'services.dgs_shadow.timeout_seconds' => 1,
        ]);

        Http::fake([
            'http://dgs-node-sidecar:8092/shadow/ingest' => Http::response(['processed' => 1], 200),
        ]);

        app(DgsShadowIngestService::class)->fireShadowIngest(
            ['reference' => '48872be1-b981-4770-84d3-887cbe449100', 'status' => 'accepted'],
            [
                'uuid' => 'ord_shadow_test_001',
                'idempotency_key' => '48872be1-b981-4770-84d3-887cbe449100',
                'user_l1_address' => 'sl1:id:buyer-shadow-test-001',
            ],
            [
                'type' => 'gift_card',
                'sku_bidx' => 'ezpin_steam_25',
                'ezpin_sku' => 12345,
            ],
            [
                ['pin_code' => 'PIN-SECRET-9901'],
            ]
        );

        Http::assertSent(function ($request) {
            $payload = $request->data();

            return $request->url() === 'http://dgs-node-sidecar:8092/shadow/ingest'
                && ($payload['mp_order']['idempotency_key'] ?? null) === '48872be1-b981-4770-84d3-887cbe449100'
                && ($payload['legacy_normalized_cards']['cards'][0]['pin_code'] ?? null) === 'PIN-SECRET-9901';
        });
    }

    public function test_fire_shadow_ingest_is_noop_when_url_is_missing(): void
    {
        config(['services.dgs_shadow.ingest_url' => null]);
        Http::fake();

        app(DgsShadowIngestService::class)->fireShadowIngest([], [], [], [['pin_code' => 'X']]);

        Http::assertNothingSent();
    }
}
