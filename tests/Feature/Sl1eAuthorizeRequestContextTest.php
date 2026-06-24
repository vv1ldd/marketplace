<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class Sl1eAuthorizeRequestContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_options_hydrates_redirect_uri_from_connect_state_cache(): void
    {
        config([
            'identity_governance.stream_enabled' => true,
            'identity_governance.stream_authorize_enabled' => true,
        ]);

        $state = 'connect-state-for-hydration-test';
        Cache::put('simple_l1:connect_state:'.hash('sha256', $state), [
            'state' => $state,
            'nonce' => 'cached-nonce',
            'client_id' => 'meanly.test',
            'redirect_uri' => 'https://meanly.test/simple-l1/callback?popup=1',
            'mode' => 'register',
        ], now()->addMinutes(5));

        $response = $this->postJson('https://api.meanly.test/api/sl1e/authorize/register/options', [
            'clientId' => 'meanly.test',
            'state' => $state,
            'mode' => 'register',
            'username' => 'hydrateuser',
        ], [
            'X-Forwarded-Host' => 'meanly.test',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['flowId', 'options']);
    }

    public function test_authorize_options_use_request_host_for_webauthn_rp_id(): void
    {
        config([
            'identity_governance.stream_enabled' => true,
            'identity_governance.stream_authorize_enabled' => true,
        ]);

        $response = $this->postJson('https://api.meanly.test/api/sl1e/authorize/options', [
            'clientId' => 'meanly.ru',
            'redirectUri' => 'https://meanly.ru/simple-l1/callback?popup=1',
            'state' => 'rp-id-state',
            'nonce' => 'rp-id-nonce',
            'mode' => 'login',
            'requestHost' => 'meanly.ru',
        ], [
            'X-Forwarded-Host' => 'meanly.ru',
        ]);

        $response->assertOk()
            ->assertJsonPath('options.rpId', 'meanly.ru');
    }

    public function test_authorize_rejects_cross_region_redirect_uri(): void
    {
        config([
            'identity_governance.stream_enabled' => true,
            'identity_governance.stream_authorize_enabled' => true,
        ]);

        $this->postJson('https://api.meanly.test/api/sl1e/authorize/options', [
            'clientId' => 'meanly.one',
            'redirectUri' => 'https://meanly.one/simple-l1/callback?popup=1',
            'state' => 'cross-region-state',
            'nonce' => 'cross-region-nonce',
            'mode' => 'login',
            'requestHost' => 'meanly.ru',
        ], [
            'X-Forwarded-Host' => 'meanly.ru',
        ])->assertStatus(422);
    }
}
