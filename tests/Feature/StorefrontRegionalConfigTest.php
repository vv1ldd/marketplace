<?php

namespace Tests\Feature;

use Tests\TestCase;

class StorefrontRegionalConfigTest extends TestCase
{
    public function test_configured_regional_frontend_origin_can_preflight_storefront_api(): void
    {
        config(['cors.allowed_origins' => ['https://br.meanly.one']]);

        $this->withHeaders([
            'Origin' => 'https://br.meanly.one',
            'Access-Control-Request-Method' => 'GET',
        ])
            ->optionsJson('/api/storefront/v1/catalog')
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'https://br.meanly.one');
    }

    public function test_unconfigured_origin_is_not_allowed_for_storefront_api(): void
    {
        config(['cors.allowed_origins' => ['https://br.meanly.one']]);

        $response = $this->withHeaders([
            'Origin' => 'https://evil.example',
            'Access-Control-Request-Method' => 'GET',
        ])->optionsJson('/api/storefront/v1/catalog');

        $this->assertNotSame('https://evil.example', $response->headers->get('Access-Control-Allow-Origin'));
    }
}
