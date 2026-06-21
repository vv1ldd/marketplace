<?php

namespace Tests\Unit;

use App\Support\SimpleL1IdentityHost;
use Tests\TestCase;

class SimpleL1IdentityHostTest extends TestCase
{
    public function test_browser_provider_uses_configured_storefront_host(): void
    {
        config([
            'simple_l1.identity_provider_url' => 'https://meanly.test',
            'simple_l1.identity_browser_url' => null,
            'storefront.frontend_url' => 'https://meanly.test',
            'app.url' => 'https://api.meanly.test',
        ]);

        $this->assertSame(
            'https://meanly.test',
            SimpleL1IdentityHost::browserProviderUrl('meanly.test'),
        );
    }

    public function test_browser_provider_maps_api_identity_config_to_storefront(): void
    {
        config([
            'simple_l1.identity_provider_url' => 'https://api.meanly.test',
            'simple_l1.identity_browser_url' => null,
            'storefront.frontend_url' => 'https://meanly.test',
        ]);

        $this->assertSame(
            'https://meanly.test',
            SimpleL1IdentityHost::browserProviderUrl('api.meanly.test'),
        );
    }

    public function test_production_identity_host_is_preserved(): void
    {
        config([
            'simple_l1.identity_provider_url' => 'https://meanly.one',
            'simple_l1.identity_browser_url' => null,
            'storefront.frontend_url' => 'https://meanly.one',
            'app.url' => 'https://meanly.one',
        ]);

        $this->assertSame(
            'https://meanly.one',
            SimpleL1IdentityHost::browserProviderUrl('meanly.one'),
        );
    }

    public function test_explicit_browser_url_overrides_auto_detection(): void
    {
        config([
            'simple_l1.identity_provider_url' => 'https://meanly.test',
            'simple_l1.identity_browser_url' => 'https://identity.example.test',
            'storefront.frontend_url' => 'https://meanly.test',
        ]);

        $this->assertSame(
            'https://identity.example.test',
            SimpleL1IdentityHost::browserProviderUrl('meanly.test'),
        );
    }
}
