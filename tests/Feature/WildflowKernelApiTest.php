<?php

namespace Tests\Feature;

use App\Models\LegalEntity;
use App\Models\Currency;
use App\Models\Provider;
use App\Models\ProviderProduct;
use App\Models\WildflowCreditReservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WildflowKernelApiTest extends TestCase
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

    public function test_kernel_provider_routes_use_local_catalog_and_availability(): void
    {
        $provider = Provider::updateOrCreate(
            ['type' => 'wildflow'],
            [
                'name' => 'Wildflow',
                'is_active' => true,
                'credentials' => ['api_key' => 'kernel-platform-token'],
            ]
        );

        ProviderProduct::create([
            'provider_id' => $provider->id,
            'sku' => 'EZ-KERNEL-10',
            'market_sku' => 'WFC-KERNEL-10',
            'name' => 'Kernel Gift Card',
            'category' => 'Gift Card',
            'purchase_price' => 9.50,
            'retail_price' => 10.00,
            'currency' => 'USD',
            'is_active' => true,
            'data' => [],
        ]);

        $this->kernelHeaders()
            ->getJson('/api/v1/providers/ezpin/unified-catalog')
            ->assertOk()
            ->assertJsonPath('provider.type', 'wildflow')
            ->assertJsonPath('provider.requested_type', 'ezpin')
            ->assertJsonPath('items.0.service_sku', 'EZ-KERNEL-10');

        $this->kernelHeaders()
            ->getJson('/api/v1/providers/ezpin/check-availability/EZ-KERNEL-10')
            ->assertOk()
            ->assertJsonPath('available', true)
            ->assertJsonPath('availability.availability', true);
    }

    public function test_partner_finance_routes_are_idempotent_and_local(): void
    {
        $entity = LegalEntity::withoutEvents(fn () => LegalEntity::create([
            'name' => 'Kernel Seller',
            'inn' => '770000001111',
            'available_balance' => 100,
            'reserved_balance' => 0,
            'currency' => 'RUB',
            'is_active' => true,
            'wildflow_api_token' => 'seller-token',
            'wildflow_financial_secret' => 'seller-secret',
        ]));

        $headers = $this->signedKernelHeaders($entity, 'seller-token', 'seller-secret', 'POST', '/api/v1/partners/grant-credit', [
            'amount' => 25,
            'reference' => 'REF-1',
            'terminal_id' => (string) $entity->id,
        ]);

        $first = $this->withHeaders($headers)
            ->postJson('/api/v1/partners/grant-credit', [
                'amount' => 25,
                'reference' => 'REF-1',
                'terminal_id' => (string) $entity->id,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('idempotent', false);

        $this->withHeaders($headers)
            ->postJson('/api/v1/partners/grant-credit', [
                'amount' => 25,
                'reference' => 'REF-1',
                'terminal_id' => (string) $entity->id,
            ])
            ->assertOk()
            ->assertJsonPath('reservation_id', $first->json('reservation_id'))
            ->assertJsonPath('idempotent', true);

        $this->assertSame(1, WildflowCreditReservation::count());

        $topUpPath = '/api/v1/partners/top-up';
        $topUpPayload = ['amount' => 10, 'terminal_id' => (string) $entity->id, 'reference' => 'TOP-1'];
        $this->withHeaders($this->signedKernelHeaders($entity, 'seller-token', 'seller-secret', 'POST', $topUpPath, $topUpPayload))
            ->postJson($topUpPath, $topUpPayload)
            ->assertOk()
            ->assertJsonPath('balance', 110);

        $showPath = '/api/v1/partners/'.$entity->id;
        $this->withHeaders($this->signedKernelHeaders($entity, 'seller-token', 'seller-secret', 'GET', $showPath, []))
            ->getJson('/api/v1/partners/'.$entity->id)
            ->assertOk()
            ->assertJsonPath('data.balance', 110);
    }

    public function test_local_wildflow_service_does_not_make_outbound_http_for_kernel_calls(): void
    {
        Http::preventStrayRequests();

        $entity = LegalEntity::withoutEvents(fn () => LegalEntity::create([
            'name' => 'Local Kernel Seller',
            'inn' => '770000001112',
            'available_balance' => 100,
            'reserved_balance' => 0,
            'currency' => 'RUB',
            'is_active' => true,
        ]));

        $result = app(\App\Services\WildflowService::class)->grantCredit(15, 'LOCAL-REF', (string) $entity->id);

        $this->assertTrue($result['success']);
        $this->assertSame(1, WildflowCreditReservation::count());
    }

    public function test_api_orders_are_gated_by_partner_balance_converted_to_usd(): void
    {
        Currency::query()->updateOrCreate(['code' => 'USD'], [
            'name' => 'US Dollar',
            'rate_to_rub' => 100,
            'is_auto_update' => false,
        ]);
        Currency::query()->updateOrCreate(['code' => 'RUB'], [
            'name' => 'Ruble',
            'rate_to_rub' => 1,
            'is_auto_update' => false,
        ]);

        $provider = Provider::updateOrCreate(
            ['type' => 'wildflow'],
            [
                'name' => 'Wildflow',
                'is_active' => true,
                'credentials' => ['api_key' => 'kernel-platform-token'],
            ]
        );

        ProviderProduct::create([
            'provider_id' => $provider->id,
            'sku' => 'EZ-BALANCE-10',
            'market_sku' => 'WFC-BALANCE-10',
            'name' => 'Balance Gated Card',
            'category' => 'Gift Card',
            'purchase_price' => 10.00,
            'retail_price' => 10.00,
            'currency' => 'USD',
            'is_active' => true,
            'data' => [],
        ]);

        $entity = LegalEntity::withoutEvents(fn () => LegalEntity::create([
            'name' => 'RUB Balance Seller',
            'inn' => '770000001113',
            'available_balance' => 500,
            'reserved_balance' => 0,
            'currency' => 'RUB',
            'is_active' => true,
            'wildflow_api_token' => 'seller-token',
            'wildflow_financial_secret' => 'seller-secret',
        ]));

        $availability = $this->withHeaders($this->signedKernelHeaders($entity, 'seller-token', 'seller-secret', 'GET', '/api/v1/providers/ezpin/check-availability/EZ-BALANCE-10', []))
            ->getJson('/api/v1/providers/ezpin/check-availability/EZ-BALANCE-10?terminal_id='.$entity->id)
            ->assertOk()
            ->assertJsonPath('availability.affordable', false);

        $this->assertEquals(10.0, $availability->json('availability.required_usd'));
        $this->assertEquals(5.0, $availability->json('availability.available_usd'));

        $payload = [
            'service_sku' => 'EZ-BALANCE-10',
            'quantity' => 1,
            'referenceCode' => 'BALANCE-GATED-1',
            'terminal_id' => (string) $entity->id,
        ];

        $order = $this->withHeaders($this->signedKernelHeaders($entity, 'seller-token', 'seller-secret', 'POST', '/api/v1/providers/ezpin/order', $payload))
            ->postJson('/api/v1/providers/ezpin/order', $payload)
            ->assertStatus(402)
            ->assertJsonPath('success', false);

        $this->assertEquals(10.0, $order->json('required_usd'));
        $this->assertEquals(5.0, $order->json('available_usd'));

        $this->assertSame(0, WildflowCreditReservation::query()->count());
        $this->assertSame(500.0, (float) $entity->fresh()->available_balance);
    }

    private function kernelHeaders(string $token = 'kernel-platform-token', string $clientId = 'platform'): self
    {
        return $this->withHeaders([
            'X-Client-Id' => $clientId,
            'X-Auth-Token' => $token,
        ]);
    }

    private function signedKernelHeaders(
        LegalEntity $entity,
        string $token,
        string $secret,
        string $method,
        string $path,
        array $payload
    ): array {
        $timestamp = (string) time();
        $body = $method === 'GET' ? '' : json_encode($payload);

        $signature = $method === 'GET'
            ? hash_hmac('sha256', $timestamp.'.'.$body, $secret)
            : hash_hmac('sha256', $timestamp.'.'.strtoupper($method).'.'.$path.'.'.$body, $secret);

        return [
            'X-Client-Id' => (string) $entity->id,
            'X-Auth-Token' => $token,
            'X-Financial-Timestamp' => $timestamp,
            'X-Financial-Signature' => $signature,
        ];
    }
}
