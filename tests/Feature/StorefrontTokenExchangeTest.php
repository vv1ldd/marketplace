<?php

namespace Tests\Feature;

use App\Services\SimpleL1ProtocolClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class StorefrontTokenExchangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_sl1_proof_exchange_issues_short_lived_storefront_token(): void
    {
        config([
            'storefront.token_ttl_seconds' => 600,
            'storefront.token_issuer' => 'meanly-storefront-test',
            'storefront.token_audience' => 'regional-frontends-test',
        ]);

        $this->mock(SimpleL1ProtocolClient::class, function ($mock): void {
            $mock->shouldReceive('introspectProof')
                ->once()
                ->with('proof-token-123')
                ->andReturn([
                    'active' => true,
                    'entity_l1_address' => 'sl1e_abcdef1234567890abcdef1234567890abcdef1',
                    'key_l1_address' => 'sl1k_key',
                    'username' => 'Buyer.User',
                    'alias' => 'buyer',
                    'display_alias' => 'Buyer',
                ]);
        });

        $response = $this->postJson('/api/storefront/v1/identity/token', [
            'proof_token' => 'proof-token-123',
            'scopes' => ['storefront:read', 'storefront:checkout', 'admin:root'],
        ])
            ->assertOk()
            ->assertJsonPath('contract.name', 'storefront-token-exchange')
            ->assertJsonPath('contract.identity_authority', 'simple-l1')
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('expires_in', 600)
            ->assertJsonPath('session.type', 'storefront_token')
            ->assertJsonPath('session.issuer', 'meanly-storefront-test')
            ->assertJsonPath('session.audience', 'regional-frontends-test')
            ->assertJsonPath('session.identity.entity_l1_address', 'sl1e_abcdef1234567890abcdef1234567890abcdef1')
            ->assertJsonPath('session.identity.username', 'buyer.user')
            ->assertJsonPath('session.scopes.0', 'storefront:read')
            ->assertJsonPath('session.scopes.1', 'storefront:checkout')
            ->assertJsonMissing(['admin:root']);

        $token = (string) $response->json('access_token');
        $this->assertStringStartsWith('sft_', $token);

        $this->withToken($token)
            ->getJson('/api/storefront/v1/identity/session')
            ->assertOk()
            ->assertJsonPath('authenticated', true)
            ->assertJsonPath('session.identity.entity_l1_address', 'sl1e_abcdef1234567890abcdef1234567890abcdef1');
    }

    public function test_storefront_token_does_not_authorize_partner_or_seller_apis(): void
    {
        $this->mock(SimpleL1ProtocolClient::class, function ($mock): void {
            $mock->shouldReceive('introspectProof')
                ->once()
                ->andReturn([
                    'active' => true,
                    'entity_l1_address' => 'sl1e_abcdef1234567890abcdef1234567890abcdef1',
                ]);
        });

        $token = (string) $this->postJson('/api/storefront/v1/identity/token', [
            'proof_token' => 'proof-token-456',
        ])->json('access_token');

        $this->withToken($token)
            ->getJson('/api/seller/balance')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'TERMINAL_CREDENTIALS_MISSING');

        $this->withToken($token)
            ->getJson('/api/v1/partners')
            ->assertUnauthorized();
    }

    public function test_simple_l1_session_handoff_issues_storefront_token(): void
    {
        config(['storefront.token_ttl_seconds' => 600]);
        Cache::put('simple_l1:proof_token:test-handoff', 'proof-token-session', now()->addMinutes(10));

        $response = $this->withSession([
            'simple_l1_identity' => [
                'entity_l1_address' => 'sl1e_abcdef1234567890abcdef1234567890abcdef1',
                'key_l1_address' => 'sl1k_key',
                'username' => 'buyer',
                'alias' => 'buyer',
                'display_alias' => 'Buyer',
                'proof_token_hash' => hash('sha256', 'proof-token-session'),
                'proof_handle' => 'test-handoff',
                'protocol' => 'simple-l1',
            ],
        ])->postJson('/api/storefront/v1/identity/handoff', [
            'scopes' => ['storefront:read', 'storefront:vault', 'admin:root'],
        ])
            ->assertOk()
            ->assertJsonPath('contract.name', 'storefront-session-handoff')
            ->assertJsonPath('contract.identity_authority', 'simple-l1')
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('session.identity.entity_l1_address', 'sl1e_abcdef1234567890abcdef1234567890abcdef1')
            ->assertJsonPath('session.identity.username', 'buyer')
            ->assertJsonPath('session.scopes.0', 'storefront:read')
            ->assertJsonPath('session.scopes.1', 'storefront:vault')
            ->assertJsonMissing(['admin:root']);

        $this->assertNull(Cache::get('simple_l1:proof_token:test-handoff'));

        $token = (string) $response->json('access_token');
        $this->withToken($token)
            ->getJson('/api/storefront/v1/identity/session')
            ->assertOk()
            ->assertJsonPath('session.identity.alias', 'buyer');

        $this->withSession([
            'simple_l1_identity' => [
                'entity_l1_address' => 'sl1e_abcdef1234567890abcdef1234567890abcdef1',
                'key_l1_address' => 'sl1k_key',
                'alias' => 'buyer',
                'display_alias' => 'Buyer',
                'proof_token_hash' => hash('sha256', 'proof-token-session'),
                'proof_handle' => 'test-handoff',
                'protocol' => 'simple-l1',
            ],
        ])->postJson('/api/storefront/v1/identity/handoff', [
            'scopes' => ['storefront:read', 'storefront:vault'],
        ])
            ->assertOk()
            ->assertJsonPath('session.scopes.1', 'storefront:vault')
            ->assertJsonPath('session.identity.entity_l1_address', 'sl1e_abcdef1234567890abcdef1234567890abcdef1');
    }

    public function test_simple_l1_connect_accepts_configured_next_vault_return_origin(): void
    {
        config(['storefront.allowed_return_origins' => ['https://meanly.test']]);

        $this->getJson('/simple-l1/connect?'.http_build_query([
            'return_to' => 'https://meanly.test/vault?sl1_handoff=1',
            'mode' => 'connect',
            'intent_type' => 'meanly.vault.open',
            'intent_title' => 'Open customer vault',
        ]))
            ->assertOk()
            ->assertJsonPath('show_handoff', true)
            ->assertJsonPath('handoff.key', 'vault_open');

        $this->assertSame('https://meanly.test/vault?sl1_handoff=1', session('simple_l1_connect.return_to'));
        $this->assertSame('connect', session('simple_l1_connect.flow'));
        $this->assertSame('login', session('simple_l1_connect.mode'));
    }

    public function test_api_host_redirects_frontend_paths_back_to_storefront_origin(): void
    {
        config([
            'storefront.frontend_url' => 'https://meanly.test',
            'storefront.api_hosts' => ['api.meanly.test'],
        ]);

        $this->get('https://api.meanly.test/vault/register?ref=legacy')
            ->assertRedirect('https://meanly.test/vault/register?ref=legacy');

        $this->get('https://api.meanly.test/store')
            ->assertRedirect('https://meanly.test/store');

        $this->get('https://api.meanly.test/')
            ->assertRedirect('https://meanly.test');
    }

    public function test_api_host_connect_uses_storefront_callback_and_authorize_urls(): void
    {
        config([
            'storefront.frontend_url' => 'https://meanly.test',
            'storefront.api_hosts' => ['api.meanly.test'],
            'simple_l1.identity_provider_url' => 'https://meanly.test',
        ]);

        $response = $this->get('https://api.meanly.test/simple-l1/connect?return_to=/store&popup=1');

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');

        $this->assertStringStartsWith('https://meanly.test/authorize?', $location);
        $this->assertStringContainsString(
            'redirect_uri='.urlencode('https://meanly.test/simple-l1/callback?popup=1'),
            $location
        );
    }

    public function test_inactive_sl1_proof_is_rejected(): void
    {
        $this->mock(SimpleL1ProtocolClient::class, function ($mock): void {
            $mock->shouldReceive('introspectProof')
                ->once()
                ->andReturn(['active' => false]);
        });

        $this->postJson('/api/storefront/v1/identity/token', [
            'proof_token' => 'inactive-proof',
        ])->assertStatus(422);
    }
}
