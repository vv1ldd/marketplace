<?php

namespace Tests\Feature;

use App\Models\LegalEntity;
use App\Models\Provider;
use App\Models\ProviderProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeanlyApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.wildflow_token' => 'kernel-platform-token',
            'services.wildflow.kernel_mode' => 'local',
        ]);
    }

    public function test_meanly_api_credentials_are_primary_for_partner_access(): void
    {
        $provider = Provider::updateOrCreate(
            ['type' => 'wildflow'],
            [
                'name' => 'Meanly API Catalog Provider',
                'is_active' => true,
                'credentials' => ['api_key' => 'kernel-platform-token'],
            ]
        );

        ProviderProduct::create([
            'provider_id' => $provider->id,
            'sku' => 'MEANLY-API-SKU-1',
            'market_sku' => 'MEANLY-API-MARKET-1',
            'name' => 'Meanly API Gift Card',
            'category' => 'Gift Card',
            'purchase_price' => 9.50,
            'retail_price' => 10.00,
            'currency' => 'USD',
            'is_active' => true,
            'data' => [],
        ]);

        $entity = LegalEntity::withoutEvents(fn () => LegalEntity::create([
            'name' => 'Meanly API Partner',
            'inn' => '770000009999',
            'available_balance' => 100,
            'reserved_balance' => 0,
            'currency' => 'USD',
            'is_active' => true,
            'meanly_api_token' => 'meanly-token',
            'meanly_financial_secret' => 'meanly-secret',
            'meanly_ip_whitelist' => [],
        ]));

        $this->withHeaders([
            'X-Client-Id' => (string) $entity->id,
            'X-Auth-Token' => 'meanly-token',
        ])
            ->getJson('/api/v1/providers/ezpin/check-availability/MEANLY-API-SKU-1?terminal_id='.$entity->id)
            ->assertOk()
            ->assertJsonPath('available', true)
            ->assertJsonPath('availability.affordable', true);
    }
}
