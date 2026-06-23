<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SimpleL1WebWalletHandoffTest extends TestCase
{
    public function test_handoff_qr_uses_short_storefront_link_with_legacy_runtime(): void
    {
        config([
            'simple_l1.runtime_url' => 'https://pass.simplelayer.one',
            'simple_l1.identity_provider_url' => 'https://pass.simplelayer.one',
            'storefront.frontend_url' => 'https://meanly.test',
            'identity_governance.stream_authorize_enabled' => false,
        ]);

        $handoffId = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';

        Http::fake([
            'https://pass.simplelayer.one/api/sl1e/authorize/handoff' => Http::response(
                json_encode([
                    'success' => true,
                    'handoffId' => $handoffId,
                    'qrUrl' => 'https://pass.simplelayer.one/authorize?client_id=meanly.test&redirect_uri=https%3A%2F%2Fmeanly.test%2Fsimple-l1%2Fcallback&handoff_id='.$handoffId.'&handoff_token=abc',
                    'qrDataUrl' => 'data:image/png;base64,STALE',
                ], JSON_UNESCAPED_SLASHES),
                200,
                ['Content-Type' => 'application/json'],
            ),
        ]);

        $response = $this->postJson('https://api.meanly.test/api/sl1e/authorize/handoff', [
            'clientId' => 'meanly.test',
            'redirectUri' => 'https://meanly.test/simple-l1/callback',
            'state' => 'handoff-state',
            'nonce' => 'handoff-nonce',
        ], [
            'X-Forwarded-Host' => 'meanly.test',
            'X-Forwarded-Proto' => 'https',
        ]);

        $response->assertOk();

        $payload = $response->json();
        $this->assertSame('https://meanly.test/h/'.$handoffId, $payload['qrUrl']);
        $this->assertNotSame('data:image/png;base64,STALE', $payload['qrDataUrl'] ?? null);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', (string) ($payload['qrDataUrl'] ?? ''));

        $cached = Cache::get('sl1e:handoff:qr:'.$handoffId);
        $this->assertStringContainsString('meanly.test/authorize', (string) $cached);
        $this->assertStringContainsString('handoff_id='.$handoffId, (string) $cached);
    }

    public function test_stream_handoff_qr_uses_short_storefront_link(): void
    {
        config([
            'storefront.frontend_url' => 'https://meanly.test',
            'identity_governance.stream_enabled' => true,
            'identity_governance.stream_authorize_enabled' => true,
        ]);

        $response = $this->postJson('https://api.meanly.test/api/sl1e/authorize/handoff', [
            'clientId' => 'meanly.test',
            'redirectUri' => 'https://meanly.test/simple-l1/callback',
            'state' => 'handoff-state',
            'nonce' => 'handoff-nonce',
        ], [
            'X-Forwarded-Host' => 'meanly.test',
            'X-Forwarded-Proto' => 'https',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $payload = $response->json();
        $handoffId = (string) ($payload['handoffId'] ?? '');
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/i', $handoffId);
        $this->assertSame('https://meanly.test/h/'.$handoffId, $payload['qrUrl']);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', (string) ($payload['qrDataUrl'] ?? ''));

        $cached = Cache::get('sl1e:handoff:qr:'.$handoffId);
        $this->assertStringContainsString('meanly.test/authorize', (string) $cached);
        $this->assertStringContainsString('handoff_id='.$handoffId, (string) $cached);
    }

    public function test_handoff_short_link_redirects_to_full_authorize_url(): void
    {
        $handoffId = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';
        $target = 'https://meanly.test/authorize?handoff_id='.$handoffId.'&handoff_token=abc';

        Cache::put('sl1e:handoff:qr:'.$handoffId, $target, now()->addMinutes(3));

        $response = $this->get('https://meanly.test/h/'.$handoffId);

        $response->assertRedirect($target);
    }
}
