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
            'credential_id' => base64_encode('test-storefront-credential'),
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

        $brand = \App\Models\Brand::create([
            'name' => 'Sovereign',
            'is_active' => true,
        ]);

        // 6. Create matching Provider Product
        $this->providerProduct = ProviderProduct::create([
            'provider_id' => $this->provider->id,
            'brand_id' => $brand->id,
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
        $identity = app(\App\Services\CanonicalProductIdentityService::class)->forProviderProduct($this->providerProduct);
        app(\App\Services\CanonicalProductIdentityCurationService::class)->saveOverride(
            $identity['fingerprint'],
            [
                'brand' => $identity['brand'],
                'canonical_category' => $identity['canonical_category'],
                'confidence' => 'high',
                'signals' => ['identity_override:approved'],
                'review_status' => \App\Models\CanonicalProductIdentityOverride::STATUS_APPROVED,
            ],
            $this->user->id,
        );

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

    private function mockPasskeySignatureForTransaction(array $transaction): void
    {
        $realPasskey = $this->user->passkeys()->first();

        $this->storefrontSigningContext($transaction);

        $this->mock(FindPasskeyToAuthenticateAction::class, function ($mock) use ($realPasskey) {
            $mock->shouldReceive('execute')->andReturn($realPasskey);
        });
    }

    private function storefrontSigningContext(array $transaction): void
    {
        $this->mockProviderAvailability(true);

        $this->actingAs($this->user)
            ->postJson(route('partner.dashboard.storefront.buy_options'), [
                'transaction' => $transaction,
            ])
            ->assertOk();
    }

    private function assertionForCurrentSigningContext(?string $txHash = null): array
    {
        $txHash ??= (string) session('storefront_signing_tx_hash');
        $challenge = $this->base64UrlEncode(hex2bin($txHash) ?: '');

        return [
            'rawId' => 'mocked-raw-id',
            'response' => [
                'clientDataJSON' => $this->base64UrlEncode(json_encode([
                    'type' => 'webauthn.get',
                    'challenge' => $challenge,
                    'origin' => 'http://localhost',
                ], JSON_UNESCAPED_SLASHES)),
                'authenticatorData' => $this->base64UrlEncode('mock-authenticator-data'),
                'signature' => $this->base64UrlEncode('mock-signature'),
                'userHandle' => $this->base64UrlEncode((string) $this->user->id),
            ],
        ];
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function mockProviderAvailability(bool $available): void
    {
        if ($this->app->bound(\App\Services\WildflowService::class)) {
            return;
        }

        $this->mock(\App\Services\WildflowService::class, function ($mock) use ($available) {
            $mock->shouldReceive('checkAvailability')
                ->byDefault()
                ->andReturn(['available' => $available]);
        });
    }

    private function normalizeStorefrontSigningPayloadForTest(array $payload): array
    {
        $salesChannels = \App\Support\SalesChannels::normalizeSelection($payload['sales_channels'] ?? []);
        sort($salesChannels);

        $amount = array_key_exists('amount', $payload) && $payload['amount'] !== null && $payload['amount'] !== ''
            ? round((float) $payload['amount'], 4)
            : null;

        return [
            'action' => (string) ($payload['action'] ?? 'stock_procurement'),
            'provider_product_id' => (int) ($payload['provider_product_id'] ?? 0),
            'shop_id' => (int) ($payload['shop_id'] ?? 0),
            'count' => (int) ($payload['count'] ?? $payload['quantity'] ?? 0),
            'amount' => $amount,
            'payment_method' => ($payload['payment_method'] ?? null) === 'native_token' ? 'native_token' : 'rub_token',
            'sales_channels' => $salesChannels,
        ];
    }

    public function test_storefront_products_are_paginated_and_do_not_expose_provider_identity(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson(route('partner.dashboard.storefront.products', [
                'per_page' => 1,
            ]));

        $response->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonStructure([
                'products' => [[
                    'id',
                    'name',
                    'public_sku',
                    'supply_class',
                    'supply_label',
                    'category_slug',
                    'category_label',
                    'purchase_price_formatted',
                    'nominal_price_formatted',
                ]],
                'categories',
            ]);

        $product = $response->json('products.0');

        $this->assertStringStartsWith('MS-', $product['public_sku']);
        $this->assertSame('gift_cards', $product['category_slug']);
        $this->assertSame('Подарочные карты', $product['category_label']);
        $this->assertSame('Подарочные карты', $response->json('categories.gift_cards'));
        $this->assertArrayNotHasKey('provider', $product);
        $this->assertArrayNotHasKey('provider_id', $product);
        $this->assertArrayNotHasKey('sku', $product);
        $this->assertArrayNotHasKey('market_sku', $product);
        $this->assertArrayNotHasKey('redemption_instructions', $product);
        $this->assertArrayNotHasKey('activation_url', $product);
        $this->assertStringNotContainsString('Sovereign Provider', $response->getContent());
    }

    public function test_storefront_passkey_options_are_limited_to_current_user_credentials(): void
    {
        $otherUser = User::factory()->create();
        \Spatie\LaravelPasskeys\Models\Passkey::factory()->create([
            'authenticatable_id' => $otherUser->id,
            'credential_id' => base64_encode('wrong-icloud-credential'),
        ]);

        $response = $this->actingAs($this->user)
            ->postJson(route('partner.dashboard.storefront.buy_options'), [
                'transaction' => [
                    'action' => 'stock_procurement',
                    'provider_product_id' => $this->providerProduct->id,
                    'shop_id' => $this->shop->id,
                    'count' => 1,
                    'amount' => null,
                    'payment_method' => 'rub_token',
                    'sales_channels' => ['meanly_storefront'],
                ],
            ]);

        $response->assertOk();

        $allowCredentials = $response->json('allowCredentials');
        $this->assertCount(1, $allowCredentials);
        $this->assertSame('public-key', $allowCredentials[0]['type']);
        $expectedCredential = $this->user->passkeys()->first()->credential_id;
        $this->assertSame(
            rtrim(strtr($expectedCredential, '+/', '-_'), '='),
            $allowCredentials[0]['id']
        );
    }

    public function test_storefront_passkey_options_use_tx_hash_as_challenge(): void
    {
        $this->mockProviderAvailability(true);

        $response = $this->actingAs($this->user)
            ->postJson(route('partner.dashboard.storefront.buy_options'), [
                'transaction' => [
                    'action' => 'stock_procurement',
                    'provider_product_id' => $this->providerProduct->id,
                    'shop_id' => $this->shop->id,
                    'count' => 1,
                    'amount' => null,
                    'payment_method' => 'rub_token',
                    'sales_channels' => ['meanly_storefront'],
                ],
            ]);

        $response->assertOk()
            ->assertJsonStructure(['challenge', 'tx_hash', 'tx_nonce', 'l1_address']);

        $this->assertSame(
            $this->base64UrlEncode(hex2bin($response->json('tx_hash')) ?: ''),
            $response->json('challenge')
        );
        $this->assertSame($response->json('tx_hash'), session('storefront_signing_tx_hash'));
        $this->assertSame($response->json('tx_nonce'), session('storefront_signing_tx_nonce'));
    }

    public function test_storefront_passkey_options_check_provider_stock_before_signing(): void
    {
        DB::table('api_wildflow_dev.local_vouchers')->where('service_sku', 'SOV-PIN-SERVICE')->delete();

        $this->mock(\App\Services\WildflowService::class, function ($mock) {
            $mock->shouldReceive('checkAvailability')
                ->once()
                ->andReturn(['available' => false]);
        });

        $response = $this->actingAs($this->user)
            ->postJson(route('partner.dashboard.storefront.buy_options'), [
                'transaction' => [
                    'action' => 'stock_procurement',
                    'provider_product_id' => $this->providerProduct->id,
                    'shop_id' => $this->shop->id,
                    'count' => 1,
                    'amount' => null,
                    'payment_method' => 'rub_token',
                    'sales_channels' => ['meanly_storefront'],
                ],
            ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Passkey-подпись не требуется', $response->json('error'));
        $this->assertNull(session('storefront_signing_tx_hash'));
    }

    public function test_storefront_products_can_be_filtered_by_safe_category(): void
    {
        ProviderProduct::create([
            'provider_id' => $this->provider->id,
            'sku' => 'SL1-GAME-PROD-01',
            'market_sku' => 'SL1-GAME-PROD-01',
            'name' => 'Steam Wallet Code',
            'category' => 'Gaming',
            'purchase_price' => 900.00,
            'retail_price' => 1000.00,
            'min_price' => 1000.00,
            'max_price' => 1000.00,
            'currency' => 'RUB',
            'is_active' => true,
        ]);

        $giftCards = $this->actingAs($this->user)
            ->getJson(route('partner.dashboard.storefront.products', [
                'category' => 'gift_cards',
                'per_page' => 24,
            ]));

        $giftCards->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('products.0.category_slug', 'gift_cards');

        $games = $this->actingAs($this->user)
            ->getJson(route('partner.dashboard.storefront.products', [
                'category' => 'games',
                'per_page' => 24,
            ]));

        $games->assertOk()
            ->assertJsonPath('products.0.category_slug', 'gift_cards');
        $this->assertTrue(collect($games->json('products'))->contains(fn (array $product): bool => $product['name'] === 'Steam Wallet Code'));
    }

    public function test_partner_dashboard_renders_storefront_without_upstream_identifiers(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('partner.dashboard'));

        $response->assertOk()
            ->assertSee('Meanly Vault', false)
            ->assertSee('Operator Workspace', false)
            ->assertSee('Подарочные карты', false)
            ->assertSee('MS-', false)
            ->assertDontSee('Sovereign Provider')
            ->assertDontSee('SL1-SOV-PROD-01');
    }

    public function test_operator_workspace_route_opens_partner_console_tab(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('partner.operator'));

        $response->assertOk()
            ->assertSee('Operator Workspace', false)
            ->assertSee('Commerce Scorecard', false)
            ->assertSee('System Health', false)
            ->assertSee('Team Members', false)
            ->assertDontSee('Sellers', false)
            ->assertSee('Yandex Connected', false)
            ->assertSee('API Apps', false);
    }

    public function test_operator_workspace_data_endpoint_returns_partner_intelligence(): void
    {
        $seller = \App\Models\Seller::create([
            'first_name' => 'Channel',
            'last_name' => 'Manager',
            'email' => 'channel-manager@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);
        $this->legalEntity->sellers()->attach($seller->id, ['role' => 'manager']);

        $this->shop->forceFill([
            'business_id' => 'business-1',
            'campaign_id' => 'campaign-1',
            'api_key' => 'yandex-api-token',
        ])->save();

        \App\Models\ApiApplication::create([
            'shop_id' => $this->shop->id,
            'type' => \App\Models\ApiApplication::TYPE_SHOP,
            'name' => 'Seller API',
            'domain' => 'seller.example.test',
            'token' => \App\Models\ApiApplication::generateToken(),
            'is_active' => true,
        ]);

        $product = \App\Models\Product::create([
            'shop_id' => $this->shop->id,
            'sku' => 'OPERATOR-SKU-1',
            'name' => 'Operator Product',
            'price_rub' => 100000,
            'is_active' => true,
            'ym_errors' => ['missing_picture'],
        ]);

        $warehouse = \App\Models\Warehouse::create([
            'shop_id' => $this->shop->id,
            'name' => 'Yandex Stock',
            'channel' => 'yandex_market',
            'is_active' => true,
            'is_main' => false,
            'ym_id' => 1001,
        ]);

        \App\Models\WarehouseStock::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'count' => 2,
        ]);

        \App\Models\Ticket::create([
            'shop_id' => $this->shop->id,
            'seller_id' => $seller->id,
            'subject' => 'Need help with channel order',
            'status' => 'open',
            'priority' => 'high',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson(route('partner.dashboard.operator.data'));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'summary',
                    'critical_alerts',
                    'trusted_recommendations',
                    'pending_reviews',
                    'failed_publishes',
                    'scorecard',
                    'health',
                    'tokenomics',
                ],
            ])
            ->assertJsonPath('data.summary.team_members', 1)
            ->assertJsonPath('data.summary.active_team_members', 1)
            ->assertJsonPath('data.summary.active_channels', 1)
            ->assertJsonPath('data.summary.yandex_connected_channels', 1)
            ->assertJsonPath('data.summary.api_applications', 1)
            ->assertJsonMissingPath('data.summary.sellers')
            ->assertJsonPath('data.health.team.total', 1)
            ->assertJsonPath('data.health.team.active', 1)
            ->assertJsonMissingPath('data.health.sellers')
            ->assertJsonPath('data.health.integrations.active_api_applications', 1)
            ->assertJsonPath('data.health.integrations.yandex_connected_channels', 1)
            ->assertJsonPath('data.health.inventory.low_stock', 1)
            ->assertJsonPath('data.tokenomics.recommendations.generated_count', 6)
            ->assertJsonPath('data.tokenomics.recommendations.used_count', 6);

        $this->assertDatabaseHas('token_metering_events', [
            'legal_entity_id' => $this->legalEntity->id,
            'event_type' => 'recommendation_generated',
        ]);
        $this->assertDatabaseHas('sovereign_ledger', [
            'legal_entity_id' => $this->legalEntity->id,
            'event_type' => 'TOKEN_USAGE_METERED',
        ]);
    }

    public function test_ai_audit_is_metered_as_sl1_usage(): void
    {
        $this->mock(\App\Services\Ai\PartnerAnalystService::class, function ($mock) {
            $mock->shouldReceive('analyze')
                ->once()
                ->andReturn('AI audit completed');
        });

        $response = $this->actingAs($this->user)
            ->postJson(route('partner.dashboard.ai.audit'));

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('token_metering_events', [
            'legal_entity_id' => $this->legalEntity->id,
            'shop_id' => $this->shop->id,
            'event_type' => 'ai_audit_run',
            'sl1_amount' => 2.0000,
        ]);
        $this->assertDatabaseHas('token_metering_events', [
            'legal_entity_id' => $this->legalEntity->id,
            'shop_id' => $this->shop->id,
            'event_type' => 'ai_audit_object',
        ]);
    }

    /**
     * Test successful standard fiat RUB storefront purchase.
     */
    public function test_fiat_rub_checkout_success()
    {
        $this->mockPasskeySignatureForTransaction([
            'action' => 'buy_once',
            'provider_product_id' => $this->providerProduct->id,
            'shop_id' => $this->shop->id,
            'count' => 1,
            'amount' => null,
            'payment_method' => 'rub_token',
            'sales_channels' => [],
        ]);

        $response = $this->actingAs($this->user)
            ->postJson(route('partner.dashboard.storefront.buy_once'), [
                'provider_product_id' => $this->providerProduct->id,
                'shop_id' => $this->shop->id,
                'quantity' => 1,
                'payment_method' => 'rub_token',
                'assertion' => $this->assertionForCurrentSigningContext(),
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
        $this->assertDatabaseHas('token_metering_events', [
            'legal_entity_id' => $this->legalEntity->id,
            'shop_id' => $this->shop->id,
            'event_type' => 'order_fulfillment',
        ]);
        $this->assertDatabaseHas('token_metering_events', [
            'legal_entity_id' => $this->legalEntity->id,
            'shop_id' => $this->shop->id,
            'event_type' => 'marketplace_success_fee',
        ]);
    }

    /**
     * Test successful Native Network Token (SL1) storefront purchase with Passkey authentication.
     */
    public function test_native_token_checkout_success()
    {
        $this->mockPasskeySignatureForTransaction([
            'action' => 'buy_once',
            'provider_product_id' => $this->providerProduct->id,
            'shop_id' => $this->shop->id,
            'count' => 1,
            'amount' => null,
            'payment_method' => 'native_token',
            'sales_channels' => [],
        ]);

        $response = $this->actingAs($this->user)
            ->postJson(route('partner.dashboard.storefront.buy_once'), [
                'provider_product_id' => $this->providerProduct->id,
                'shop_id' => $this->shop->id,
                'quantity' => 1,
                'payment_method' => 'native_token',
                'assertion' => $this->assertionForCurrentSigningContext(),
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
            'comment' => 'Прямая суверенная B2B закупка через Simple Layer One Ledger с нативным токеном SL1.',
        ]);
    }

    /**
     * Test native token checkout failure if passkey assertion is missing.
     */
    public function test_native_token_checkout_fails_if_missing_assertion()
    {
        $this->storefrontSigningContext([
            'action' => 'buy_once',
            'provider_product_id' => $this->providerProduct->id,
            'shop_id' => $this->shop->id,
            'count' => 1,
            'amount' => null,
            'payment_method' => 'native_token',
            'sales_channels' => [],
        ]);

        $response = $this->actingAs($this->user)
            ->postJson(route('partner.dashboard.storefront.buy_once'), [
                'provider_product_id' => $this->providerProduct->id,
                'shop_id' => $this->shop->id,
                'quantity' => 1,
                'payment_method' => 'native_token',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error', 'Для подтверждения Simple Layer One транзакции требуется подпись Passkey.');
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

        $this->mockPasskeySignatureForTransaction([
            'action' => 'buy_once',
            'provider_product_id' => $this->providerProduct->id,
            'shop_id' => $this->shop->id,
            'count' => 1,
            'amount' => null,
            'payment_method' => 'native_token',
            'sales_channels' => [],
        ]);

        $response = $this->actingAs($this->user)
            ->postJson(route('partner.dashboard.storefront.buy_once'), [
                'provider_product_id' => $this->providerProduct->id,
                'shop_id' => $this->shop->id,
                'quantity' => 1,
                'payment_method' => 'native_token',
                'assertion' => $this->assertionForCurrentSigningContext(),
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

        $this->storefrontSigningContext([
            'action' => 'buy_once',
            'provider_product_id' => $this->providerProduct->id,
            'shop_id' => $this->shop->id,
            'count' => 1,
            'amount' => null,
            'payment_method' => 'native_token',
            'sales_channels' => [],
        ]);

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

    public function test_stock_procurement_requires_passkey_signature(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('partner.dashboard.storefront.add_to_catalog'), [
                'provider_product_id' => $this->providerProduct->id,
                'shop_id' => $this->shop->id,
                'count' => 1,
                'payment_method' => 'rub',
                'sales_channels' => ['meanly_storefront'],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error', 'Для подтверждения Simple Layer One транзакции требуется подпись Passkey.');
    }

    public function test_stock_procurement_rejects_changed_payload_after_passkey_context(): void
    {
        $this->storefrontSigningContext([
            'action' => 'stock_procurement',
            'provider_product_id' => $this->providerProduct->id,
            'shop_id' => $this->shop->id,
            'count' => 2,
            'amount' => null,
            'payment_method' => 'rub_token',
            'sales_channels' => ['meanly_storefront'],
        ]);

        $response = $this->actingAs($this->user)
            ->postJson(route('partner.dashboard.storefront.add_to_catalog'), [
                'provider_product_id' => $this->providerProduct->id,
                'shop_id' => $this->shop->id,
                'count' => 1,
                'payment_method' => 'rub',
                'sales_channels' => ['meanly_storefront'],
                'assertion' => ['rawId' => 'mocked-raw-id'],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error', 'Параметры Simple Layer One транзакции изменились после Passkey-подтверждения. Подпишите сделку заново.');
    }

    public function test_stock_procurement_rejects_assertion_with_wrong_tx_hash_challenge(): void
    {
        $realPasskey = $this->user->passkeys()->first();
        $this->storefrontSigningContext([
            'action' => 'stock_procurement',
            'provider_product_id' => $this->providerProduct->id,
            'shop_id' => $this->shop->id,
            'count' => 1,
            'amount' => null,
            'payment_method' => 'rub_token',
            'sales_channels' => ['meanly_storefront'],
        ]);

        $this->mock(FindPasskeyToAuthenticateAction::class, function ($mock) use ($realPasskey) {
            $mock->shouldReceive('execute')->andReturn($realPasskey);
        });

        $response = $this->actingAs($this->user)
            ->postJson(route('partner.dashboard.storefront.add_to_catalog'), [
                'provider_product_id' => $this->providerProduct->id,
                'shop_id' => $this->shop->id,
                'count' => 1,
                'payment_method' => 'rub_token',
                'sales_channels' => ['meanly_storefront'],
                'assertion' => $this->assertionForCurrentSigningContext(str_repeat('0', 64)),
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error', 'WebAuthn challenge не совпадает с tx_hash Simple Layer One транзакции.');
    }

    public function test_stock_procurement_rejects_reused_simple_layer_one_nonce(): void
    {
        $this->mockPasskeySignatureForTransaction([
            'action' => 'stock_procurement',
            'provider_product_id' => $this->providerProduct->id,
            'shop_id' => $this->shop->id,
            'count' => 1,
            'amount' => null,
            'payment_method' => 'rub_token',
            'sales_channels' => ['meanly_storefront'],
        ]);

        app(\App\Services\LedgerService::class)->record(
            null,
            'FINANCE_HOLD',
            $this->legalEntity,
            [
                'simple_layer_one' => [
                    'tx_nonce' => session('storefront_signing_tx_nonce'),
                ],
            ],
            $this->legalEntity
        );

        $response = $this->actingAs($this->user)
            ->postJson(route('partner.dashboard.storefront.add_to_catalog'), [
                'provider_product_id' => $this->providerProduct->id,
                'shop_id' => $this->shop->id,
                'count' => 1,
                'payment_method' => 'rub_token',
                'sales_channels' => ['meanly_storefront'],
                'assertion' => $this->assertionForCurrentSigningContext(),
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error', 'Simple Layer One nonce уже был использован. Подпишите новую транзакцию.');
    }

    public function test_l1_replay_mints_deposits_as_rubt(): void
    {
        app(\App\Services\LedgerService::class)->record(
            null,
            'FINANCE_DEPOSIT',
            $this->legalEntity,
            [
                'asset' => 'RUBT',
                'amount' => 123.45,
                'amount_rub' => 123.45,
                'token_amount' => 123.45,
                'currency' => 'RUB',
                'token_currency' => 'RUBT',
                'backing_currency' => 'RUB',
                'backing_ratio' => 1,
            ],
            $this->legalEntity
        );

        $balances = app(\App\Services\L1StateService::class)->reconstructBalance($this->legalEntity);

        $this->assertSame(50123.45, $balances['rubt_available_balance']);
        $this->assertSame(50123.45, $balances['available_balance']);
        $this->assertSame(1000.0000, $balances['sl1_available_balance']);
    }

    public function test_stock_procurement_can_spend_rubt_with_passkey(): void
    {
        $this->mockPasskeySignatureForTransaction([
            'action' => 'stock_procurement',
            'provider_product_id' => $this->providerProduct->id,
            'shop_id' => $this->shop->id,
            'count' => 1,
            'amount' => null,
            'payment_method' => 'rub_token',
            'sales_channels' => ['meanly_storefront'],
        ]);
        $txHash = session('storefront_signing_tx_hash');
        $txNonce = session('storefront_signing_tx_nonce');

        $response = $this->actingAs($this->user)
            ->postJson(route('partner.dashboard.storefront.add_to_catalog'), [
                'provider_product_id' => $this->providerProduct->id,
                'shop_id' => $this->shop->id,
                'count' => 1,
                'payment_method' => 'rub_token',
                'sales_channels' => ['meanly_storefront'],
                'assertion' => $this->assertionForCurrentSigningContext(),
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('currency', 'RUBT')
            ->assertJsonPath('tx_hash', $txHash)
            ->assertJsonPath('tx_nonce', $txNonce)
            ->assertJsonStructure(['explorer_url', 'explorer_reference']);

        $balances = app(\App\Services\L1StateService::class)->reconstructBalance($this->legalEntity);
        $this->assertSame(49091.0, $balances['rubt_available_balance']);
        $this->assertSame(0.0, $balances['rubt_reserved_balance']);

        $this->assertTrue(\App\Models\SovereignLedger::query()
            ->where('event_type', 'FINANCE_HOLD')
            ->where('legal_entity_id', $this->legalEntity->id)
            ->exists());

        $hold = \App\Models\SovereignLedger::query()
            ->where('event_type', 'FINANCE_HOLD')
            ->where('legal_entity_id', $this->legalEntity->id)
            ->latest('id')
            ->firstOrFail();
        $this->assertSame($txHash, data_get($hold->payload, 'simple_layer_one.tx_hash'));
        $this->assertSame($txNonce, data_get($hold->payload, 'simple_layer_one.tx_nonce'));
        $this->assertSame('Simple Layer One', data_get($hold->payload, 'simple_layer_one.network'));
        $this->assertSame('RUBT', data_get($hold->payload, 'simple_layer_one.canonical_payload.asset'));
        $this->assertNotEmpty(data_get($hold->payload, 'simple_layer_one.public_key'));
        $this->assertNotEmpty(data_get($hold->payload, 'simple_layer_one.clientDataJSON'));
        $this->assertSame($txHash, data_get($hold->payload, 'tx_hash'));

        $this->actingAs($this->user)
            ->getJson(route('partner.dashboard.simple_layer_1.trace', ['reference' => $txHash]))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('trace.network', 'Simple Layer One')
            ->assertJsonPath('trace.target.payload.tx_hash', $txHash);

        $this->assertDatabaseHas('product_inventory', [
            'shop_id' => $this->shop->id,
            'status' => 'available',
        ]);
    }

    public function test_stock_procurement_can_spend_sl1_with_passkey(): void
    {
        $this->mockPasskeySignatureForTransaction([
            'action' => 'stock_procurement',
            'provider_product_id' => $this->providerProduct->id,
            'shop_id' => $this->shop->id,
            'count' => 1,
            'amount' => null,
            'payment_method' => 'native_token',
            'sales_channels' => ['meanly_storefront'],
        ]);

        $response = $this->actingAs($this->user)
            ->postJson(route('partner.dashboard.storefront.add_to_catalog'), [
                'provider_product_id' => $this->providerProduct->id,
                'shop_id' => $this->shop->id,
                'count' => 1,
                'payment_method' => 'native_token',
                'sales_channels' => ['meanly_storefront'],
                'assertion' => $this->assertionForCurrentSigningContext(),
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('currency', 'SL1');

        $balances = app(\App\Services\L1StateService::class)->reconstructBalance($this->legalEntity);
        $this->assertSame(990.9085, $balances['sl1_available_balance']);
        $this->assertSame(0.0, $balances['sl1_reserved_balance']);
    }
}
