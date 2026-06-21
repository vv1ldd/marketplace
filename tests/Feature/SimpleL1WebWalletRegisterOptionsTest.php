<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SimpleL1WebWalletRegisterOptionsTest extends TestCase
{
    public function test_register_options_user_label_uses_username(): void
    {
        config([
            'simple_l1.runtime_url' => 'https://pass.simplelayer.one',
            'simple_l1.client_name' => 'Meanly',
        ]);

        Http::fake([
            'https://pass.simplelayer.one/api/sl1e/authorize/register/options' => Http::response(
                json_encode([
                    'success' => true,
                    'flowId' => 'flow-123',
                    'entityAddress' => 'sl1e_testentityaddress000000000000000000000',
                    'options' => [
                        'user' => [
                            'name' => 'sl1e_testentityaddress000000000000000000000',
                            'displayName' => 'sl1e_testentityaddress000000000000000000000',
                        ],
                    ],
                ], JSON_UNESCAPED_SLASHES),
                200,
                ['Content-Type' => 'application/json'],
            ),
        ]);

        $response = $this->postJson('https://api.meanly.test/api/sl1e/authorize/register/options', [
            'clientId' => 'meanly.test',
            'clientName' => 'Meanly',
            'redirectUri' => 'https://meanly.test/simple-l1/callback',
            'mode' => 'register',
            'username' => 'seballos',
        ], [
            'X-Forwarded-Host' => 'meanly.test',
        ]);

        $response->assertOk()
            ->assertJsonPath('options.user.name', '@seballos')
            ->assertJsonPath('options.user.displayName', 'Meanly · @seballos')
            ->assertJsonPath('username', 'seballos');
    }

    public function test_register_options_without_username_injects_generated_username(): void
    {
        config([
            'simple_l1.runtime_url' => 'https://pass.simplelayer.one',
            'simple_l1.client_name' => 'Meanly',
        ]);

        Http::fake(function ($request) {
            $payload = json_decode($request->body(), true);
            if (! is_array($payload) || empty($payload['username'])) {
                return Http::response([
                    'success' => false,
                    'message' => 'Username is required to create a Safe.',
                ], 422, ['Content-Type' => 'application/json']);
            }

            return Http::response(
                json_encode([
                    'success' => true,
                    'flowId' => 'flow-auto',
                    'entityAddress' => 'sl1e_testentityaddress000000000000000000000',
                    'username' => $payload['username'],
                    'options' => [
                        'user' => [
                            'name' => $payload['username'],
                            'displayName' => $payload['username'],
                        ],
                    ],
                ], JSON_UNESCAPED_SLASHES),
                200,
                ['Content-Type' => 'application/json'],
            );
        });

        $response = $this->postJson('https://api.meanly.test/api/sl1e/authorize/register/options', [
            'clientId' => 'meanly.test',
            'clientName' => 'Meanly',
            'redirectUri' => 'https://meanly.test/simple-l1/callback',
            'mode' => 'register',
        ], [
            'X-Forwarded-Host' => 'meanly.test',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('options.user.name', 'Digital Safe')
            ->assertJsonPath('options.user.displayName', 'Meanly · Digital Safe')
            ->assertJsonMissingPath('username');
    }
}
