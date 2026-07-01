<?php

namespace Tests\Feature;

use App\Models\Provider;
use App\Services\Provider\WildflowDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DgsSplitModeFulfillmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_split_mode_routes_sandbox_fulfillment_to_node_and_preserves_php_grant_credit(): void
    {
        config([
            'services.wildflow.verify_tls' => false,
            'services.wildflow.kernel_url' => 'https://php-dgs.test/api/v1',
            'services.dgs.fulfillment_mode' => 'split',
            'services.dgs.fulfillment_url' => 'http://dgs-node-sidecar.test:8091',
        ]);

        $provider = Provider::updateOrCreate(
            ['type' => 'wildflow-sandbox'],
            [
                'name' => 'Wildflow Sandbox',
                'is_active' => true,
                'settings' => ['upstream_provider' => 'ezpin-sandbox'],
                'credentials' => [
                    'base_url' => 'https://php-dgs.test/api/v1/',
                    'api_key' => 'wf-token',
                    'client_id' => 'wf-client',
                    'financial_secret' => 'wf-secret',
                ],
            ]
        );

        Http::fake([
            'https://php-dgs.test/api/v1/partners/grant-credit' => Http::response([
                'success' => true,
                'reservation_id' => 'HOLD-1',
            ], 200),
            'http://dgs-node-sidecar.test:8091/api/v1/fulfillment/issue' => Http::response([
                'fulfillment_id' => 'ful_lic_split001',
                'status' => 'ISSUED',
                'strategy' => 'license_key',
                'payload' => ['license_key' => 'PIN-EZP-SBX-SPLIT-001'],
            ], 200),
        ]);

        $driver = (new WildflowDriver())->setProvider($provider);
        $reference = '48872be1-b981-4770-84d3-887cbe449100';

        $driver->createOrder(
            sku: '4402',
            reference: $reference,
            price: 5.0,
            quantity: 1,
            meta: [
                'terminal_id' => '9937',
                'user_l1_address' => 'sl1:id:buyer-split-test',
                'order_uuid' => 'order-uuid-split-001',
                'sku_bidx' => 'WF-SBX-4402',
                'ezpin_sku' => 4402,
            ]
        );

        Http::assertSent(fn ($request) => str_contains($request->url(), '/partners/grant-credit'));
        Http::assertSent(function ($request) use ($reference) {
            if (! str_contains($request->url(), '/api/v1/fulfillment/issue')) {
                return false;
            }

            $payload = $request->data();

            return ($payload['idempotency_key'] ?? null) === $reference
                && ($payload['buyer_address'] ?? null) === 'sl1:id:buyer-split-test'
                && ($payload['strategy'] ?? null) === 'license_key';
        });
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/providers/ezpin-sandbox/order'));

        $this->assertSame(['PIN-EZP-SBX-SPLIT-001'], $driver->getCodes($reference));
    }

    public function test_http_mode_keeps_legacy_php_provider_order_path(): void
    {
        config([
            'services.wildflow.verify_tls' => false,
            'services.wildflow.kernel_url' => 'https://php-dgs.test/api/v1',
            'services.dgs.fulfillment_mode' => 'http',
        ]);

        $provider = Provider::updateOrCreate(
            ['type' => 'wildflow-sandbox'],
            [
                'name' => 'Wildflow Sandbox',
                'is_active' => true,
                'settings' => ['upstream_provider' => 'ezpin-sandbox'],
                'credentials' => [
                    'base_url' => 'https://php-dgs.test/api/v1/',
                    'api_key' => 'wf-token',
                    'client_id' => 'wf-client',
                    'financial_secret' => 'wf-secret',
                ],
            ]
        );

        Http::fake([
            'https://php-dgs.test/api/v1/partners/grant-credit' => Http::response(['success' => true], 200),
            'https://php-dgs.test/api/v1/providers/ezpin-sandbox/order' => Http::response([
                'order' => ['referenceCode' => 'WF-LEGACY-1'],
            ], 200),
            'https://php-dgs.test/api/v1/providers/ezpin-sandbox/orders/*/normalized-cards' => Http::response([
                'cards' => [['pin_code' => 'LEGACY-PIN-001']],
            ], 200),
        ]);

        $driver = (new WildflowDriver())->setProvider($provider);
        $reference = '48872be1-b981-4770-84d3-887cbe449101';

        $driver->createOrder(
            sku: '4402',
            reference: $reference,
            price: 5.0,
            quantity: 1,
            meta: ['terminal_id' => '9937']
        );

        Http::assertSent(fn ($request) => str_contains($request->url(), '/providers/ezpin-sandbox/order'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/fulfillment/issue'));
        $this->assertSame(['LEGACY-PIN-001'], $driver->getCodes($reference));
    }

    public function test_split_mode_routes_production_ezpin_to_node_when_allowlisted(): void
    {
        config([
            'services.wildflow.verify_tls' => false,
            'services.wildflow.kernel_url' => 'https://php-dgs.test/api/v1',
            'services.dgs.fulfillment_mode' => 'split',
            'services.dgs.fulfillment_url' => 'http://dgs-node-sidecar.test:8091',
            'services.dgs.split_fulfillment_providers' => ['ezpin-sandbox', 'ezpin'],
        ]);

        $provider = Provider::updateOrCreate(
            ['type' => 'wildflow'],
            [
                'name' => 'Wildflow',
                'is_active' => true,
                'credentials' => [
                    'base_url' => 'https://php-dgs.test/api/v1/',
                    'api_key' => 'wf-token',
                    'client_id' => 'wf-client',
                    'financial_secret' => 'wf-secret',
                ],
            ]
        );

        Http::fake([
            'https://php-dgs.test/api/v1/partners/grant-credit' => Http::response(['success' => true], 200),
            'http://dgs-node-sidecar.test:8091/api/v1/fulfillment/issue' => Http::response([
                'fulfillment_id' => 'ful_lic_prod001',
                'status' => 'ISSUED',
                'strategy' => 'license_key',
                'payload' => ['license_key' => 'PIN-EZP-PROD-001'],
            ], 200),
        ]);

        $driver = (new WildflowDriver())->setProvider($provider);
        $reference = '48872be1-b981-4770-84d3-887cbe449102';

        $driver->createOrder('4402', $reference, 5.0, 1, [
            'terminal_id' => '9937',
            'user_l1_address' => 'sl1:id:buyer-prod-split',
        ]);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/fulfillment/issue'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/providers/ezpin/order'));
        $this->assertSame(['PIN-EZP-PROD-001'], $driver->getCodes($reference));
    }

    public function test_split_mode_keeps_production_ezpin_on_php_when_not_allowlisted(): void
    {
        config([
            'services.wildflow.verify_tls' => false,
            'services.wildflow.kernel_url' => 'https://php-dgs.test/api/v1',
            'services.dgs.fulfillment_mode' => 'split',
            'services.dgs.split_fulfillment_providers' => ['ezpin-sandbox'],
        ]);

        $provider = Provider::updateOrCreate(
            ['type' => 'wildflow'],
            [
                'name' => 'Wildflow',
                'is_active' => true,
                'credentials' => [
                    'base_url' => 'https://php-dgs.test/api/v1/',
                    'api_key' => 'wf-token',
                    'client_id' => 'wf-client',
                    'financial_secret' => 'wf-secret',
                ],
            ]
        );

        Http::fake([
            'https://php-dgs.test/api/v1/partners/grant-credit' => Http::response(['success' => true], 200),
            'https://php-dgs.test/api/v1/providers/ezpin/order' => Http::response([
                'order' => ['referenceCode' => 'WF-PROD-1'],
            ], 200),
        ]);

        $driver = (new WildflowDriver())->setProvider($provider);
        $driver->createOrder('4402', '48872be1-b981-4770-84d3-887cbe449103', 5.0, 1, [
            'terminal_id' => '9937',
        ]);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/providers/ezpin/order'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/fulfillment/issue'));
    }
}
