<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\LegalEntity;
use App\Models\Shop;
use App\Models\Provider;
use App\Models\WildflowCatalog;
use App\Models\ProviderProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelPasskeys\Actions\FindPasskeyToAuthenticateAction;

class PasskeyStorefrontCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected LegalEntity $legalEntity;
    protected Shop $shop;
    protected Provider $provider;
    protected WildflowCatalog $catalogItem;
    protected ProviderProduct $providerProduct;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.domain' => 'localhost']);
        config(['session.domain' => null]);

        // 1. Create B2B Partner Role and User
        $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'b2b_partner', 'guard_name' => 'web']);
        
        $this->user = User::factory()->create([
            'first_name' => 'Sovereign',
            'last_name' => 'Partner',
            'email' => 'partner@sovereign.l1',
        ]);
        $this->user->assignRole($role);

        // Seed a dummy passkey to pass EnsureUserHasPasskey middleware check
        \Spatie\LaravelPasskeys\Models\Passkey::factory()->create([
            'authenticatable_id' => $this->user->id,
        ]);

        // 2. Create Legal Entity with both fiat and native token balances
        $this->legalEntity = LegalEntity::create([
            'user_id' => $this->user->id,
            'name' => 'Sovereign Consortium Corp',
            'available_balance' => 50000.00,
            'native_token_balance' => 500.0000,
            'native_token_reserved' => 0.0000,
            'native_token_currency' => 'SL1',
            'tariff_type' => 'privileged',
            'currency' => 'RUB',
            'inn' => '1234567890',
            'kpp' => '123456789',
            'ogrn' => '1234567890123',
            'bank_account' => '12345678901234567890',
            'bank_correspondent_account' => '12345678901234567890',
            'bank_name' => 'Sovereign Bank',
            'bank_bik' => '123456789',
            'legal_address' => 'Sovereign Street 1',
            'postal_address' => 'Sovereign Street 1',
            'director_name' => 'Director Name',
        ]);
        $this->user->managedLegalEntities()->attach($this->legalEntity->id, ['role' => 'owner']);

        // Record a deposit onto the Sovereign Ledger so that L1StateService reconstructs a valid available balance
        app(\App\Services\LedgerService::class)->record(
            null,
            'DEPOSIT_INTENT_CLEARED',
            $this->legalEntity,
            ['amount' => 50000.00],
            $this->legalEntity
        );

        // 3. Create Shop (legal_entity_id is not in fillable)
        $this->shop = new Shop([
            'name' => 'Sovereign Store',
            'domain' => 'sovereign.store',
            'voucher_prefix' => 'SOV',
            'shop_region' => 'RU',
            'is_active' => true,
        ]);
        $this->shop->legal_entity_id = $this->legalEntity->id;
        $this->shop->save();

        // 4. Create Provider (Sovereign type to trigger L1 Clearing)
        $this->provider = Provider::create([
            'name' => 'Sovereign Provider',
            'type' => 'sovereign',
            'is_active' => true,
        ]);

        // 5. Create Wildflow Catalog Item
        $this->catalogItem = WildflowCatalog::create([
            'sku' => 'SL1-SOV-PROD-01',
            'reward_type' => 'Gift-Card',
            'retail_price' => 1000.00,
            'purchase_price' => 900.00,
            'is_active' => true,
            'provider_id' => $this->provider->id,
            'service_sku' => app(\App\Services\VaultTransitService::class)->encrypt('SOV-PIN-SERVICE'),
            'type' => 'unified_catalog',
            'data' => [
                'percentage_of_buying_price' => 0.0,
                'display_name' => 'Sovereign Product',
                'currency' => 'RUB',
            ],
        ]);

        // 6. Create matching Provider Product
        $this->providerProduct = ProviderProduct::create([
            'provider_id' => $this->provider->id,
            'sku' => 'SL1-SOV-PROD-01',
            'market_sku' => 'SL1-SOV-PROD-01',
            'name' => 'Sovereign Product',
            'category' => 'Gift-Card',
            'purchase_price' => 900.00,
            'retail_price' => 1000.00,
            'min_price' => 1000.00,
            'max_price' => 1000.00,
            'currency' => 'RUB',
            'is_active' => true,
        ]);

        // 7. Initialize SQLite table for api_wildflow_dev.local_vouchers
        if (DB::getDriverName() === 'sqlite') {
            try {
                DB::statement("ATTACH DATABASE ':memory:' AS api_wildflow_dev");
            } catch (\Exception $e) {
                // Ignore if already attached
            }
        }

        Schema::dropIfExists('api_wildflow_dev.local_vouchers');

        Schema::create('api_wildflow_dev.local_vouchers', function ($table) {
            $table->id();
            $table->string('service_sku');
            $table->text('code');
            $table->string('serial')->nullable();
            $table->dateTime('expiry_date')->nullable();
            $table->boolean('is_used')->default(false);
            $table->string('order_id')->nullable();
            $table->dateTime('claimed_at')->nullable();
        });

        // 8. Seed some available vouchers in local_vouchers
        DB::table('api_wildflow_dev.local_vouchers')->insert([
            [
                'service_sku' => 'SOV-PIN-SERVICE',
                'code' => app(\App\Services\VaultTransitService::class)->encrypt('VOUCHER-SECRET-PIN-123'),
                'serial' => 'SN-12345',
                'expiry_date' => now()->addYear(),
                'is_used' => false,
            ],
            [
                'service_sku' => 'SOV-PIN-SERVICE',
                'code' => app(\App\Services\VaultTransitService::class)->encrypt('VOUCHER-SECRET-PIN-456'),
                'serial' => 'SN-67890',
                'expiry_date' => now()->addYear(),
                'is_used' => false,
            ]
        ]);
    }

    /**
     * Test successful standard fiat RUB storefront purchase.
     */
    public function test_fiat_rub_checkout_success()
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('partner.dashboard.storefront.buy_once'), [
                'provider_product_id' => $this->providerProduct->id,
                'shop_id' => $this->shop->id,
                'quantity' => 1,
                'payment_method' => 'rub',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'total_cost', 'currency', 'vouchers', 'message']);

        // Assert balance deductions
        $this->legalEntity->refresh();
        // privilege tariff for 1000 RUB retail, buying price 900, VIP = buy * 1.01 = 909 RUB
        $expectedCost = 909.00;
        $this->assertEquals(50000.00 - $expectedCost, (float)$this->legalEntity->available_balance);

        // Assert Order and Items
        $this->assertDatabaseHas('orders', [
            'shop_id' => $this->shop->id,
            'status' => 'COMPLETED',
        ]);
    }

    /**
     * Test successful Native Network Token (SL1) storefront purchase with Passkey authentication.
     */
    public function test_native_token_checkout_success()
    {
        // 1. Mock FindPasskeyToAuthenticateAction to simulate valid FaceID/TouchID cryptographic signature verification
        $realPasskey = $this->user->passkeys()->first();

        $this->mock(FindPasskeyToAuthenticateAction::class, function ($mock) use ($realPasskey) {
            $mock->shouldReceive('execute')->andReturn($realPasskey);
        });

        // 2. Set storefront signing context in session
        session(['storefront_signing_options' => json_encode(['challenge' => 'dummy', 'rpId' => 'localhost'])]);

        $response = $this->actingAs($this->user)
            ->postJson(route('partner.dashboard.storefront.buy_once'), [
                'provider_product_id' => $this->providerProduct->id,
                'shop_id' => $this->shop->id,
                'quantity' => 1,
                'payment_method' => 'native_token',
                'assertion' => ['rawId' => 'mocked-raw-id']
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'total_cost', 'currency', 'vouchers', 'message']);

        // Assert balance deductions
        $this->legalEntity->refresh();
        // 909 RUB / 100 + 0.0015 gas fee = 9.0915 SL1
        $expectedSl1Cost = 9.09 + 0.0015;
        $this->assertEquals(500.0000 - $expectedSl1Cost, (float)$this->legalEntity->native_token_balance);

        // Assert Order and Items are marked with native SL1 descriptors
        $this->assertDatabaseHas('orders', [
            'shop_id' => $this->shop->id,
            'status' => 'COMPLETED',
            'comment' => 'Прямая суверенная B2B закупка через Simple Layer 1 Ledger с нативным токеном SL1.',
        ]);
    }

    /**
     * Test native token checkout failure if passkey assertion is missing.
     */
    public function test_native_token_checkout_fails_if_missing_assertion()
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('partner.dashboard.storefront.buy_once'), [
                'provider_product_id' => $this->providerProduct->id,
                'shop_id' => $this->shop->id,
                'quantity' => 1,
                'payment_method' => 'native_token',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error', 'Для оплаты нативными токенами требуется подпись Passkey.');
    }

    /**
     * Test native token checkout failure when available SL1 token balance is insufficient.
     */
    public function test_native_token_checkout_fails_if_insufficient_balance()
    {
        // Record a FINANCE_HOLD block to drain the reconstructed native available balance
        app(\App\Services\LedgerService::class)->record(
            null,
            'FINANCE_HOLD',
            $this->legalEntity,
            [
                'payment_method' => 'native_token',
                'sl1_amount' => 995.0000,
                'gas_fee' => 0.0000,
            ],
            $this->legalEntity
        );

        $realPasskey = $this->user->passkeys()->first();

        $this->mock(FindPasskeyToAuthenticateAction::class, function ($mock) use ($realPasskey) {
            $mock->shouldReceive('execute')->andReturn($realPasskey);
        });

        session(['storefront_signing_options' => json_encode(['challenge' => 'dummy', 'rpId' => 'localhost'])]);

        $response = $this->actingAs($this->user)
            ->postJson(route('partner.dashboard.storefront.buy_once'), [
                'provider_product_id' => $this->providerProduct->id,
                'shop_id' => $this->shop->id,
                'quantity' => 1,
                'payment_method' => 'native_token',
                'assertion' => ['rawId' => 'mocked-raw-id']
            ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Недостаточно средств в нативных токенах.', $response->json('error'));
    }

    /**
     * Test native token checkout failure when signature verification fails.
     */
    public function test_native_token_checkout_fails_if_invalid_assertion()
    {
        // Mock cryptographic verification to throw an exception
        $this->mock(FindPasskeyToAuthenticateAction::class, function ($mock) {
            $mock->shouldReceive('execute')->andThrow(new \Exception("Signature verification failed."));
        });

        session(['storefront_signing_options' => json_encode(['challenge' => 'dummy', 'rpId' => 'localhost'])]);

        $response = $this->actingAs($this->user)
            ->postJson(route('partner.dashboard.storefront.buy_once'), [
                'provider_product_id' => $this->providerProduct->id,
                'shop_id' => $this->shop->id,
                'quantity' => 1,
                'payment_method' => 'native_token',
                'assertion' => ['rawId' => 'mocked-raw-id']
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error', 'Криптографическая проверка подписи не удалась: Signature verification failed.');
    }
}
