<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function withCommerceCryptoRailsEnabled(): static
    {
        config(['blockchain_networks.crypto_rails_enabled' => true]);

        return $this;
    }

    protected function withSettlementAdapterEnabled(string $adapterKey = 'polygon', string $mode = 'read_only'): static
    {
        config([
            'settlement_adapters.'.$adapterKey.'.enabled' => true,
            'settlement_adapters.'.$adapterKey.'.mode' => $mode,
        ]);

        return $this;
    }

    protected function assertStorefrontRedirect($response, string $path = ''): void
    {
        $path = $path === '' ? '/' : '/'.ltrim($path, '/');
        $response->assertRedirect($path);
    }
}
