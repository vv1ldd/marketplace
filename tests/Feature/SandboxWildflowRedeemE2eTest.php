<?php

namespace Tests\Feature;

use App\Models\ApiApplication;
use App\Models\Currency;
use App\Models\LegalEntity;
use App\Models\Order\Order;
use App\Models\Order\OrderItems;
use App\Models\User;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SandboxWildflowRedeemE2eTest extends TestCase
{
    use RefreshDatabase;

    public function test_meanly_sandbox_order_issues_voucher_redeems_real_sandbox_code_and_captures_deterministic_amounts(): void
    {
        Mail::fake();
        config(['app.domain' => 'localhost', 'session.domain' => null]);

        Http::fake([
            '*/partners/grant-credit' => Http::response(['success' => true, 'reservation_id' => 'SBX-HOLD-1'], 200),
            '*/providers/ezpin-sandbox/order' => Http::response([
                'order' => ['referenceCode' => 'WF-SANDBOX-ORDER-1'],
            ], 200),
            '*/providers/ezpin-sandbox/orders/*/normalized-cards' => Http::response([
                'cards' => [
                    ['pin_code' => 'EZPIN-SANDBOX-REAL-CODE-001'],
                ],
            ], 200),
        ]);

        Role::firstOrCreate(['name' => 'b2b_partner', 'guard_name' => 'web']);

        $user = User::factory()->create(['email' => 'meanly-owner@example.test']);
        $user->assignRole('b2b_partner');
        \Spatie\LaravelPasskeys\Models\Passkey::factory()->create([
            'authenticatable_id' => $user->id,
        ]);

        $legalEntity = LegalEntity::create([
            'user_id' => $user->id,
            'name' => 'MEANLY',
            'short_name' => 'MEANLY',
            'inn' => '770000000777',
            'available_balance' => 1000.00,
            'reserved_balance' => 0,
            'currency' => 'RUB',
            'is_active' => true,
        ]);
        $user->managedLegalEntities()->attach($legalEntity->id, ['role' => 'owner']);

        $shop = new Shop([
            'name' => 'MEANLY',
            'domain' => 'meanly.test',
            'voucher_prefix' => 'MEANLY',
            'shop_region' => 'RU',
            'is_active' => true,
            'is_sandbox' => true,
        ]);
        $shop->legal_entity_id = $legalEntity->id;
        $shop->save();

        Currency::updateOrCreate(
            ['code' => 'USD'],
            [
                'name' => 'US Dollar',
                'rate_to_rub' => 85.0,
                'manual_rate' => 85.0,
                'is_auto_update' => false,
            ]
        );

        $token = 'meanly-shop-token';
        ApiApplication::create([
            'shop_id' => $shop->id,
            'type' => ApiApplication::TYPE_SHOP,
            'name' => 'Meanly Redeem API',
            'token' => $token,
            'is_active' => true,
        ]);

        $sandboxOrder = $this->actingAs($user)->postJson('http://meanly.test/partner/dashboard/sandbox', [
            'mode' => 'wildflow_sandbox',
            'sku' => 'WF-SBX-E2E-001',
            'service_sku' => 'EZPIN-SBX-001',
            'nominal_amount' => 1.00,
            'nominal_currency' => 'USD',
            'exchange_rate' => 85.0,
            'price_rub' => 8500,
            'code' => 'SANDBOX-TEST-CODE-0000',
        ]);

        $sandboxOrder->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('calculation.cost_rub', 85)
            ->assertJsonPath('calculation.price_rub_minor', 8500);

        $voucherCode = $sandboxOrder->json('voucher_code');
        $this->assertIsString($voucherCode);

        $order = Order::firstOrFail();
        $order->update([
            'info' => array_merge($order->info ?? [], [
                'fake' => true,
                'redeem_live_provider' => true,
            ]),
        ]);
        $this->assertTrue($order->fresh()->isYandexSandboxOrder());
        $this->assertTrue($order->fresh()->shouldRedeemThroughProvider());

        $legalEntity->refresh();
        $this->assertSame(915.0, (float) $legalEntity->available_balance);
        $this->assertSame(85.0, (float) $legalEntity->reserved_balance);

        $this->withToken($token)
            ->postJson('/api/redeem/verify-code', ['code' => $voucherCode])
            ->assertOk()
            ->assertJsonPath('data.sku', 'WF-SBX-E2E-001');

        $this->withToken($token)->postJson('/api/redeem/activate', [
            'code' => $voucherCode,
            'verification_code' => 'TRUSTED_USER',
            'email' => 'client@example.test',
            'first_name' => 'Sandbox',
            'last_name' => 'Client',
        ])->assertOk()
            ->assertJsonPath('status', 'success');

        $item = OrderItems::firstOrFail();
        $this->assertSame('success', $item->purchase_status);
        $this->assertSame('EZPIN-SANDBOX-REAL-CODE-001', $item->original_code);
        $this->assertTrue($item->is_activated);
        $this->assertTrue($item->is_redeemed);
        $this->assertStringStartsWith('SL1-', $item->provider_order_id);

        $legalEntity->refresh();
        $this->assertSame(915.0, (float) $legalEntity->available_balance);
        $this->assertSame(0.0, (float) $legalEntity->reserved_balance);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/providers/ezpin-sandbox/order'));
        Http::assertSent(fn ($request) => str_contains($request->url(), '/providers/ezpin-sandbox/orders/'.$item->provider_order_id.'/normalized-cards'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/providers/ezpin-sandbox/orders/'.$item->uuid.'/normalized-cards'));
    }
}
