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
}
