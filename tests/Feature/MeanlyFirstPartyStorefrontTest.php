<?php

namespace Tests\Feature;

use App\Models\LegalEntity;
use App\Models\Order\Order;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\ProductSalesChannel;
use App\Models\Provider;
use App\Models\ProviderProduct;
use App\Models\Shop;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WildflowCatalog;
use App\Services\MeanlyCatalogReconciliationService;
use App\Services\MeanlyFirstPartyStorefrontService;
use App\Services\PartnerOperatorIntelligenceService;
use App\Services\Provider\ProviderHub;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MeanlyFirstPartyStorefrontTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.domain' => 'localhost',
            'meanly_storefront.legal_entity.inn' => '770000099001',
            'meanly_storefront.legal_entity.name' => 'Meanly First Party LLC',
            'meanly_storefront.legal_entity.short_name' => 'Meanly',
            'meanly_storefront.shop.name' => 'Meanly Test Store',
            'meanly_storefront.shop.domain' => 'meanly.test',
            'meanly_storefront.shop.voucher_prefix' => 'MEAN',
            'meanly_storefront.shop.business_id' => '900001',
            'meanly_storefront.shop.campaign_id' => '900002',
            'meanly_storefront.shop.api_key' => 'ym-api-key',
            'meanly_storefront.shop.notification_token' => 'ym-token',
        ]);
    }

    private function seedStorefrontCheckoutProduct(
        string $sku = 'MEANLY-CHECKOUT-EMAIL',
        string $voucher = 'MEAN-EMAIL-TEST01-ZZ',
    ): Product {
        $storefront = app(MeanlyFirstPartyStorefrontService::class);
        $shop = $storefront->shop();

        $product = Product::create([
            'shop_id' => $shop->id,
            'sku' => $sku,
            'name' => 'Meanly Checkout Email Card',
            'price_rub' => 15000,
            'type' => 'giftcard',
            'category' => 'Gift Cards',
            'is_active' => true,
        ]);

        ProductSalesChannel::create([
            'product_id' => $product->id,
            'shop_id' => $shop->id,
            'channel' => 'meanly_storefront',
            'is_enabled' => true,
        ]);

        ProductInventory::create([
            'shop_id' => $shop->id,
            'sku' => $product->sku,
            'nominal_amount' => 150,
            'nominal_currency' => 'RUB',
            'voucher' => $voucher,
            'is_used' => false,
            'status' => 'available',
            'expires_at' => now()->addYear(),
        ]);

        return $product;
    }

    private function checkoutUser(array $attributes = []): User
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);

        $user = User::factory()->create([
            'first_name' => 'Account',
            'email' => 'account-buyer@example.test',
            ...$attributes,
        ]);

        \Spatie\LaravelPasskeys\Models\Passkey::factory()->create([
            'authenticatable_id' => $user->id,
            'credential_id' => base64_encode('checkout-test-credential'),
        ]);

        return $user;
    }

    private function fundBuyerWallet(User $user, int $amountMinor = 50000): void
    {
        app(\App\Services\BuyerWalletService::class)->mintRUBT(
            user: $user,
            amountMinor: $amountMinor,
            reason: 'Feature test buyer wallet funding',
            idempotencyKey: 'feature-test-wallet-funding:'.$user->id.':'.$amountMinor.':'.\Illuminate\Support\Str::random(8),
        );
    }

    /**
     * @return array{product: Product, provider_product: ProviderProduct, provider: Provider}
     */
    private function seedProviderBackedStorefrontProduct(bool $preOrder = false): array
    {
        $storefront = app(MeanlyFirstPartyStorefrontService::class);
        $shop = $storefront->shop();
        $provider = Provider::create([
            'name' => 'Wildflow Sandbox',
            'type' => 'wildflow-sandbox',
            'is_active' => true,
            'credentials' => [
                'base_url' => 'http://api.wildflow.test/api/v1/',
                'api_key' => 'sandbox',
                'terminal_id' => '9937',
            ],
        ]);

        $providerProduct = ProviderProduct::create([
            'provider_id' => $provider->id,
            'sku' => '2334',
            'market_sku' => '2334',
            'name' => 'Provider Product 2334',
            'purchase_price' => 23,
            'retail_price' => 25,
            'min_price' => 25,
            'max_price' => 25,
            'currency' => 'USD',
            'is_active' => true,
            'data' => ['service_sku' => '2334', 'pre_order' => $preOrder],
        ]);

        $product = Product::create([
            'shop_id' => $shop->id,
            'provider_id' => $provider->id,
            'wildflow_catalog_sku' => '2334',
            'sku' => 'MEANLY-PROVIDER-2334',
            'name' => 'Provider Product 2334',
            'price_rub' => 250000,
            'type' => 'giftcard',
            'category' => 'Gift Cards',
            'is_active' => true,
        ]);

        ProductSalesChannel::create([
            'product_id' => $product->id,
            'shop_id' => $shop->id,
            'channel' => 'meanly_storefront',
            'is_enabled' => true,
        ]);

        return [
            'product' => $product,
            'provider_product' => $providerProduct,
            'provider' => $provider,
        ];
    }

    private function mockPublicProviderAvailability(bool $available, bool $preOrderSupported = false): void
    {
        $this->mock(\App\Services\WildflowService::class, function ($mock) use ($available, $preOrderSupported) {
            $mock->shouldReceive('checkAvailability')
                ->byDefault()
                ->andReturn([
                    'available' => $available,
                    'pre_order_supported' => $preOrderSupported,
                    'raw' => [
                        'availability' => [
                            'availability' => $available,
                            'pre_order' => $preOrderSupported,
                        ],
                    ],
                ]);
        });
    }

    private function mockProviderRedemptionDriver(string $code = 'REAL-PROVIDER-CODE-123'): object
    {
        $driver = new class($code) implements \App\Services\Provider\ProviderDriverInterface {
            public int $createCalls = 0;

            public function __construct(private readonly string $code) {}

            public function setProvider(Provider $provider): self
            {
                return $this;
            }

            public function createOrder(string $sku, string $reference, float $price, int $quantity, array $meta = []): string
            {
                $this->createCalls++;

                return $reference;
            }

            public function getCodes(string $externalOrderId): array
            {
                return [$this->code];
            }

            public function getBalance(): float
            {
                return 0.0;
            }

            public function getRates(): array
            {
                return [];
            }
        };

        $this->mock(ProviderHub::class, function ($mock) use ($driver) {
            $mock->shouldReceive('forProvider')
                ->byDefault()
                ->andReturn($driver);
        });

        return $driver;
    }

    private function walletAssertionForTx(User $user, string $txHash): array
    {
        $challenge = $this->base64UrlEncode(hex2bin($txHash) ?: '');

        return $this->walletAssertionForChallenge($user, $challenge);
    }

    private function walletAssertionForChallenge(User $user, string $challenge): array
    {
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
                'userHandle' => $this->base64UrlEncode((string) $user->id),
            ],
        ];
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function canonicalCheckoutViewData(Product $product): array
    {
        $selectedOffer = [
            'product_id' => $product->id,
            'url' => route('meanly.canonical-products.show', 'meanly-checkout-email-card'),
            'availability' => 'in_stock',
            'price' => [
                'amount' => 150,
                'currency' => 'RUB',
            ],
            'seller' => [
                'name' => 'Meanly seller',
            ],
        ];

        $facts = [
            'name' => 'Meanly Checkout Email Card',
            'description' => 'Digital checkout test card.',
            'url' => route('meanly.canonical-products.show', 'meanly-checkout-email-card'),
            'machine_readable_at' => route('llms.catalog.canonical-products.show', 'meanly-checkout-email-card'),
            'canonical_category' => 'gift_cards',
            'canonical_category_label' => 'Gift Cards',
            'brand' => 'Meanly',
            'region' => 'US',
            'face_value' => 25,
            'face_value_currency' => 'USD',
            'canonical_identity' => [
                'brand' => 'Meanly',
                'platform' => 'meanly',
                'product_family' => 'checkout',
                'region' => 'US',
                'face_value' => 25,
                'face_value_currency' => 'USD',
            ],
            'seller_offers' => [
                'count' => 1,
                'best_offer' => $selectedOffer,
                'offers' => [$selectedOffer],
            ],
            'indexing_policy' => [
                'robots' => 'noindex,follow',
            ],
        ];

        return [
            'facts' => $facts,
            'intentResolution' => [
                'selected_offer' => $selectedOffer,
                'alternatives' => [],
                'intent_label' => 'Best offer',
                'intent' => 'best_offer',
                'machine_readable_at' => route('llms.catalog.canonical-products.intents.show', [
                    'identitySlug' => 'meanly-checkout-email-card',
                    'intent' => 'best_offer',
                ]),
            ],
            'jsonLd' => [],
            'errors' => new \Illuminate\Support\ViewErrorBag(),
        ];
    }

    public function test_meanly_identity_reconciliation_and_channel_visibility_are_scoped(): void
    {
        $storefront = app(MeanlyFirstPartyStorefrontService::class);
        $shop = $storefront->shop();

        $product = Product::create([
            'shop_id' => $shop->id,
            'sku' => 'MEANLY-SKU-001',
            'name' => 'Meanly Gift Card',
            'price_rub' => 10000,
            'type' => 'giftcard',
            'category' => 'Gift Cards',
            'is_active' => true,
        ]);

        $summary = app(MeanlyCatalogReconciliationService::class)->reconcile($shop, [
            ['offer' => ['offerId' => 'MEANLY-SKU-001', 'name' => 'Meanly Gift Card', 'basicPrice' => ['value' => 100]]],
            ['offer' => ['offerId' => 'YANDEX-ONLY-001', 'name' => 'Yandex Only Card', 'basicPrice' => ['value' => 250]]],
        ]);

        $this->assertSame(1, $summary['local_products']);
        $this->assertSame(2, $summary['yandex_offers']);
        $this->assertSame(1, $summary['missing_local_count']);
        $this->assertSame(0, $summary['price_mismatch_count']);

        $this->assertDatabaseHas('product_sales_channels', [
            'product_id' => $product->id,
            'shop_id' => $shop->id,
            'channel' => 'meanly_storefront',
            'is_enabled' => true,
        ]);
        $this->assertDatabaseHas('product_sales_channels', [
            'product_id' => $product->id,
            'shop_id' => $shop->id,
            'channel' => 'yandex_market',
            'is_enabled' => true,
        ]);
        $this->assertDatabaseHas('sovereign_ledger', [
            'shop_id' => $shop->id,
            'event_type' => 'MEANLY_CATALOG_RECONCILED',
        ]);
        $this->assertDatabaseHas('token_metering_events', [
            'legal_entity_id' => $shop->legal_entity_id,
            'shop_id' => $shop->id,
            'event_type' => 'catalog_sync',
        ]);
    }

    public function test_public_marketplace_storefront_checkout_fulfills_and_includes_enabled_sellers(): void
    {
        $storefront = app(MeanlyFirstPartyStorefrontService::class);
        $shop = $storefront->shop();

        $product = Product::create([
            'shop_id' => $shop->id,
            'sku' => 'MEANLY-CHECKOUT-001',
            'name' => 'Steam Meanly Checkout Card',
            'price_rub' => 15000,
            'nominal_value' => 150,
            'purchase_currency' => 'RUB',
            'type' => 'giftcard',
            'category' => 'Gift Cards',
            'is_active' => true,
        ]);
        ProductSalesChannel::create([
            'product_id' => $product->id,
            'shop_id' => $shop->id,
            'channel' => 'meanly_storefront',
            'is_enabled' => true,
        ]);
        ProductInventory::create([
            'shop_id' => $shop->id,
            'sku' => $product->sku,
            'nominal_amount' => 150,
            'nominal_currency' => 'RUB',
            'voucher' => 'MEAN-ABCDE-TEST01-ZZ',
            'is_used' => false,
            'status' => 'available',
            'expires_at' => now()->addYear(),
        ]);

        $otherEntity = LegalEntity::create([
            'name' => 'Other Seller LLC',
            'short_name' => 'Other',
            'inn' => '770000099002',
            'available_balance' => 1000,
            'reserved_balance' => 0,
            'currency' => 'RUB',
            'is_active' => true,
        ]);
        $otherShop = new Shop([
            'name' => 'Other Shop',
            'domain' => 'other.test',
            'voucher_prefix' => 'OTHR',
            'is_active' => true,
        ]);
        $otherShop->legal_entity_id = $otherEntity->id;
        $otherShop->save();
        $otherProduct = Product::create([
            'shop_id' => $otherShop->id,
            'sku' => 'OTHER-SKU-001',
            'name' => 'Steam Other Seller Card',
            'price_rub' => 99900,
            'nominal_value' => 150,
            'purchase_currency' => 'RUB',
            'type' => 'giftcard',
            'category' => 'Gift Cards',
            'is_active' => true,
        ]);
        ProductSalesChannel::create([
            'product_id' => $otherProduct->id,
            'shop_id' => $otherShop->id,
            'channel' => 'meanly_storefront',
            'is_enabled' => true,
        ]);

        $this->artisan('catalog:rebuild-identities');

        $this->get(route('meanly.storefront.index'))
            ->assertOk()
            ->assertSee('Steam Meanly Checkout', false)
            ->assertSee('Steam Other Seller', false);

        $this->get(route('meanly.storefront.products.show', $product->slug))
            ->assertOk()
            ->assertSee('Steam Meanly Checkout Card', false)
            ->assertDontSee('OTHER-SKU-001', false);

        $response = $this->postJson(route('meanly.storefront.checkout'), [
            'product_id' => $product->id,
            'quantity' => 1,
            'email' => 'buyer@example.test',
            'name' => 'Buyer',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('vouchers.0.code', 'MEAN-ABCDE-TEST01-ZZ');

        $order = Order::where('sales_channel', 'meanly_storefront')->firstOrFail();
        $this->assertSame($shop->id, $order->shop_id);
        $this->assertSame(150.0, (float) $order->total_amount);

        $inventory = ProductInventory::where('voucher', 'MEAN-ABCDE-TEST01-ZZ')->firstOrFail();
        $this->assertTrue($inventory->is_used);
        $this->assertSame('sold', $inventory->status);
        $this->assertNotNull($inventory->order_item_id);

        $this->assertDatabaseHas('sovereign_ledger', [
            'shop_id' => $shop->id,
            'event_type' => 'ORDER_RECEIVE',
        ]);
        $this->assertDatabaseHas('sovereign_ledger', [
            'shop_id' => $shop->id,
            'event_type' => 'VOUCHER_SLIP_ISSUED',
        ]);
        $this->assertDatabaseHas('token_metering_events', [
            'legal_entity_id' => $shop->legal_entity_id,
            'shop_id' => $shop->id,
            'event_type' => 'order_fulfillment',
        ]);
    }

    public function test_authenticated_storefront_checkout_uses_account_email_when_not_gift(): void
    {
        $product = $this->seedStorefrontCheckoutProduct();
        $user = $this->checkoutUser();

        $response = $this->actingAs($user)->postJson(route('meanly.storefront.checkout'), [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('vouchers.0.code', 'MEAN-EMAIL-TEST01-ZZ');

        $order = Order::where('sales_channel', 'meanly_storefront')->firstOrFail();
        $this->assertSame('account-buyer@example.test', data_get($order->client_info, 'email'));
        $this->assertSame('account-buyer@example.test', data_get($order->client_info, 'delivery_email'));
        $this->assertSame('account-buyer@example.test', data_get($order->client_info, 'buyer_email'));
        $this->assertSame($user->id, data_get($order->client_info, 'buyer_user_id'));
        $this->assertFalse(data_get($order->client_info, 'is_gift'));
    }

    public function test_authenticated_gift_storefront_checkout_requires_recipient_email(): void
    {
        $product = $this->seedStorefrontCheckoutProduct();
        $user = $this->checkoutUser();

        $response = $this->actingAs($user)->postJson(route('meanly.storefront.checkout'), [
            'product_id' => $product->id,
            'quantity' => 1,
            'is_gift' => true,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->assertFalse(ProductInventory::where('voucher', 'MEAN-EMAIL-TEST01-ZZ')->firstOrFail()->is_used);
    }

    public function test_guest_storefront_checkout_requires_delivery_email(): void
    {
        $product = $this->seedStorefrontCheckoutProduct();

        $response = $this->postJson(route('meanly.storefront.checkout'), [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->assertFalse(ProductInventory::where('voucher', 'MEAN-EMAIL-TEST01-ZZ')->firstOrFail()->is_used);
    }

    public function test_guest_storefront_checkout_with_delivery_email_still_fulfills(): void
    {
        $product = $this->seedStorefrontCheckoutProduct();

        $response = $this->postJson(route('meanly.storefront.checkout'), [
            'product_id' => $product->id,
            'quantity' => 1,
            'email' => 'guest-buyer@example.test',
            'name' => 'Guest Buyer',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('vouchers.0.code', 'MEAN-EMAIL-TEST01-ZZ');

        $order = Order::where('sales_channel', 'meanly_storefront')->firstOrFail();
        $this->assertSame('guest-buyer@example.test', data_get($order->client_info, 'email'));
        $this->assertSame('guest-buyer@example.test', data_get($order->client_info, 'delivery_email'));
        $this->assertNull(data_get($order->client_info, 'buyer_user_id'));
    }

    public function test_canonical_product_checkout_view_hides_email_requirement_until_gift_mode(): void
    {
        $product = $this->seedStorefrontCheckoutProduct();
        $user = $this->checkoutUser(['email' => 'render-buyer@example.test']);
        $viewData = $this->canonicalCheckoutViewData($product);

        $this->actingAs($user);
        $html = view('catalog.product', $viewData)->render();

        $this->assertStringNotContainsString('Код придет на ваш email', $html);
        $this->assertStringNotContainsString('render-buyer@example.test', $html);
        $this->assertStringContainsString('Отправить на другой email', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*id="email"[^>]*data-gift-email[^>]*>/', $html);
        preg_match('/<input[^>]*id="email"[^>]*data-gift-email[^>]*>/', $html, $emailInput);
        $this->assertStringNotContainsString('required', $emailInput[0]);
        $this->assertStringContainsString('email.required = toggle.checked;', $html);
        $this->assertStringContainsString('data-inline-order-safe-template', $html);
        $this->assertStringContainsString('renderInlineOrderSafe', $html);
        $this->assertStringContainsString('renderStandaloneSafeFallback', $html);
        $this->assertStringContainsString('openSafe({ automatic: true });', $html);
        $this->assertStringContainsString('safeLink.href = result.cabinet_safe_url;', $html);
        $this->assertStringContainsString('Открыть код', $html);
        $this->assertStringNotContainsString('const fallbackUrl', $html);
        $this->assertStringNotContainsString('window.location.assign(fallbackUrl)', $html);
        $this->assertStringNotContainsString('window.location.assign(standaloneSafeUrl)', $html);
        $this->assertStringNotContainsString('window.location.assign(result.cabinet_safe_url', $html);
        $this->assertStringNotContainsString('result.cabinet_safe_url || result.redirect_url || result.safe_url', $html);
        $this->assertStringNotContainsString('MEAN-EMAIL-TEST01-ZZ', $html);
    }

    public function test_storefront_product_checkout_view_keeps_wallet_success_inline(): void
    {
        $product = $this->seedStorefrontCheckoutProduct();
        $user = $this->checkoutUser(['email' => 'storefront-render-buyer@example.test']);

        $html = $this->actingAs($user)
            ->get(route('meanly.storefront.products.show', $product->slug))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-inline-order-safe-template', $html);
        $this->assertStringContainsString('renderInlineOrderSafe', $html);
        $this->assertStringContainsString('renderStandaloneSafeFallback', $html);
        $this->assertStringContainsString('safeLink.href = result.cabinet_safe_url;', $html);
        $this->assertStringContainsString('Открыть код', $html);
        $this->assertStringNotContainsString('window.location.assign(result.cabinet_safe_url || result.redirect_url || result.safe_url)', $html);
        $this->assertStringNotContainsString('window.location.assign(standaloneSafeUrl)', $html);
    }

    public function test_storefront_product_can_connect_external_simple_l1_identity(): void
    {
        $product = $this->seedStorefrontCheckoutProduct('MEANLY-SL1-CONNECT', 'MEAN-SL1-TEST01-ZZ');
        $l1Address = 'sl1e_'.str_repeat('c', 39);

        $this->get(route('meanly.storefront.products.show', $product->slug))
            ->assertOk()
            ->assertSee('Connect Simple L1 wallet')
            ->assertSee(route('meanly.simple_l1.connect', ['return_to' => '/store/products/'.$product->slug]), false);

        $this->withSession([
            'simple_l1_identity' => [
                'l1_address' => $l1Address,
                'proof_token' => 'proof-token',
            ],
        ])->get(route('meanly.storefront.products.show', $product->slug))
            ->assertOk()
            ->assertSee('Кошелек подключен')
            ->assertSee($l1Address)
            ->assertSee('Переподключить SL1 wallet');
    }

    public function test_simple_l1_callback_verifies_proof_and_stores_marketplace_session_identity(): void
    {
        $l1Address = 'sl1e_'.str_repeat('d', 39);

        config(['simple_l1.identity_provider_url' => 'https://api.wildflow.test']);
        Http::fake([
            'https://api.wildflow.test/api/simple-l1/proofs/introspect' => Http::response([
                'protocol' => 'simple-l1',
                'active' => true,
                'identity' => [
                    'l1_address' => $l1Address,
                    'entity_l1_address' => $l1Address,
                    'key_l1_address' => 'sl1_'.str_repeat('e', 40),
                    'address_version' => 'simple-l1:v1:entity',
                    'key_address_version' => 'simple-l1:v1:passkey',
                    'user_id' => 1,
                ],
                'proof' => [
                    'expires_at' => now()->addMinutes(5)->toIso8601String(),
                    'context' => ['action' => 'meanly.marketplace.connect'],
                ],
            ]),
        ]);

        $this->withSession([
            'simple_l1_connect.state' => 'expected-state',
            'simple_l1_connect.return_to' => '/store/products/meanly-sl1-connect',
        ])->get('/simple-l1/callback?state=expected-state&proof_token=proof-token')
            ->assertRedirect('/store/products/meanly-sl1-connect')
            ->assertSessionHas('sovereign_l1_address', $l1Address)
            ->assertSessionHas('simple_l1_identity.l1_address', $l1Address)
            ->assertSessionHas('simple_l1_identity.entity_l1_address', $l1Address)
            ->assertSessionHas('simple_l1_identity.key_l1_address', 'sl1_'.str_repeat('e', 40));
    }

    public function test_storefront_checkout_submits_simple_l1_intent_and_stores_projection(): void
    {
        $product = $this->seedStorefrontCheckoutProduct('MEANLY-SL1-INTENT', 'MEAN-SL1-INTENT-ZZ');
        $l1Address = 'sl1e_'.str_repeat('f', 39);
        $keyAddress = 'sl1_'.str_repeat('a', 40);

        config(['simple_l1.identity_provider_url' => 'https://api.wildflow.test']);
        Http::fake([
            'https://api.wildflow.test/api/simple-l1/intents' => Http::response([
                'protocol' => 'simple-l1',
                'created' => true,
                'identity' => [
                    'entity_l1_address' => $l1Address,
                    'key_l1_address' => $keyAddress,
                ],
                'intent' => [
                    'intent_id' => 'sl1i_checkout_intent_001',
                    'status' => 'accepted',
                    'capability' => 'marketplace.checkout.create',
                    'scope' => 'marketplace:meanly',
                    'payload_hash' => str_repeat('1', 64),
                    'decision' => 'allow',
                    'reason_codes' => ['grant.allow.matched'],
                ],
            ], 201),
        ]);

        $this->withSession([
            'simple_l1_identity' => [
                'l1_address' => $l1Address,
                'entity_l1_address' => $l1Address,
                'key_l1_address' => $keyAddress,
                'proof_token' => 'proof-token',
            ],
        ])->postJson(route('meanly.storefront.checkout'), [
            'product_id' => $product->id,
            'quantity' => 1,
            'email' => 'sl1-buyer@example.test',
            'name' => 'SL1 Buyer',
        ])->assertOk()
            ->assertJsonPath('success', true);

        $order = Order::where('sales_channel', 'meanly_storefront')->firstOrFail();
        $this->assertSame('sl1i_checkout_intent_001', data_get($order->info, 'simple_l1.intent_id'));
        $this->assertSame('accepted', data_get($order->info, 'simple_l1.intent_status'));
        $this->assertSame($l1Address, data_get($order->info, 'simple_l1.entity_l1_address'));
        $this->assertNull(data_get($order->info, 'simple_l1.receipt'));

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/api/simple-l1/intents')
            && $request['capability'] === 'marketplace.checkout.create'
            && $request['scope'] === 'marketplace:meanly'
            && $request['proof_token'] === 'proof-token');
    }

    public function test_storefront_checkout_stops_when_simple_l1_intent_is_rejected(): void
    {
        $product = $this->seedStorefrontCheckoutProduct('MEANLY-SL1-REJECTED', 'MEAN-SL1-REJECT-ZZ');
        $l1Address = 'sl1e_'.str_repeat('8', 39);

        config(['simple_l1.identity_provider_url' => 'https://api.wildflow.test']);
        Http::fake([
            'https://api.wildflow.test/api/simple-l1/intents' => Http::response([
                'protocol' => 'simple-l1',
                'created' => true,
                'identity' => ['entity_l1_address' => $l1Address],
                'intent' => [
                    'intent_id' => 'sl1i_checkout_rejected_001',
                    'status' => 'rejected',
                    'decision' => 'deny',
                    'reason_codes' => ['grant.none.matched'],
                ],
            ], 201),
        ]);

        $this->withSession([
            'simple_l1_identity' => [
                'l1_address' => $l1Address,
                'entity_l1_address' => $l1Address,
                'proof_token' => 'proof-token',
            ],
        ])->postJson(route('meanly.storefront.checkout'), [
            'product_id' => $product->id,
            'quantity' => 1,
            'email' => 'sl1-rejected@example.test',
            'name' => 'SL1 Rejected',
        ])->assertStatus(422);

        $this->assertSame(0, Order::query()->count());
    }

    public function test_wallet_checkout_options_require_authentication(): void
    {
        $product = $this->seedStorefrontCheckoutProduct();

        $this->postJson(route('meanly.storefront.checkout.wallet.options'), [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertUnauthorized();
    }

    public function test_wallet_checkout_options_challenge_equals_transaction_hash(): void
    {
        $product = $this->seedStorefrontCheckoutProduct();
        $user = $this->checkoutUser();
        $this->fundBuyerWallet($user);

        $response = $this->actingAs($user)->postJson(route('meanly.storefront.checkout.wallet.options'), [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $response->assertOk()
            ->assertJsonPath('asset', 'RUBT')
            ->assertJsonPath('canonical_payload.intent', 'BUYER_PURCHASE_DEBIT')
            ->assertJsonPath('canonical_payload.asset', 'RUBT');

        $payload = $response->json();
        $this->assertSame(hash('sha256', $payload['canonical_json']), $payload['tx_hash']);
        $this->assertSame($this->base64UrlEncode(hex2bin($payload['tx_hash']) ?: ''), $payload['challenge']);
        $this->assertSame(15000, $payload['amount_minor']);
    }

    public function test_wallet_checkout_confirm_requires_passkey_and_does_not_debit_or_consume_stock(): void
    {
        $product = $this->seedStorefrontCheckoutProduct();
        $user = $this->checkoutUser();
        $this->fundBuyerWallet($user);

        $options = $this->actingAs($user)->postJson(route('meanly.storefront.checkout.wallet.options'), [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertOk()->json();

        $this->actingAs($user)->postJson(route('meanly.storefront.checkout.wallet.confirm'), [
            'pending_tx_id' => $options['pending_tx_id'],
            'tx_hash' => $options['tx_hash'],
        ])->assertStatus(422)->assertJsonValidationErrors('assertion');

        $this->assertSame(50000, app(\App\Services\BuyerWalletService::class)->balance($user)['available_minor']);
        $this->assertFalse(ProductInventory::where('voucher', 'MEAN-EMAIL-TEST01-ZZ')->firstOrFail()->is_used);
        $this->assertDatabaseMissing('wallet_ledger_entries', [
            'user_id' => $user->id,
            'direction' => 'debit',
            'tx_hash' => $options['tx_hash'],
        ]);
    }

    public function test_wallet_checkout_rejects_insufficient_rubt_before_voucher_consumption(): void
    {
        $product = $this->seedStorefrontCheckoutProduct();
        $user = $this->checkoutUser();

        $this->actingAs($user)->postJson(route('meanly.storefront.checkout.wallet.options'), [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertStatus(422)->assertJsonValidationErrors('wallet');

        $this->assertFalse(ProductInventory::where('voucher', 'MEAN-EMAIL-TEST01-ZZ')->firstOrFail()->is_used);
    }

    public function test_legacy_checkout_rejects_rubt_payment_method_without_passkey_proof(): void
    {
        $product = $this->seedStorefrontCheckoutProduct();
        $user = $this->checkoutUser();
        $this->fundBuyerWallet($user);

        $this->actingAs($user)->postJson(route('meanly.storefront.checkout'), [
            'product_id' => $product->id,
            'quantity' => 1,
            'payment_method' => 'rubt',
        ])->assertStatus(422)->assertJsonValidationErrors('payment_method');

        $this->assertFalse(ProductInventory::where('voucher', 'MEAN-EMAIL-TEST01-ZZ')->firstOrFail()->is_used);
        $this->assertSame(50000, app(\App\Services\BuyerWalletService::class)->balance($user)['available_minor']);
    }

    public function test_wallet_checkout_confirms_with_passkey_then_debits_and_fulfills(): void
    {
        $product = $this->seedStorefrontCheckoutProduct();
        $user = $this->checkoutUser();
        $this->fundBuyerWallet($user);

        $options = $this->actingAs($user)->postJson(route('meanly.storefront.checkout.wallet.options'), [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertOk()->json();

        $passkey = $user->passkeys()->first();
        $this->mock(\Spatie\LaravelPasskeys\Actions\FindPasskeyToAuthenticateAction::class, function ($mock) use ($passkey) {
            $mock->shouldReceive('execute')->andReturn($passkey);
        });

        $response = $this->actingAs($user)->postJson(route('meanly.storefront.checkout.wallet.confirm'), [
            'pending_tx_id' => $options['pending_tx_id'],
            'tx_hash' => $options['tx_hash'],
            'assertion' => $this->walletAssertionForTx($user, $options['tx_hash']),
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('tx_hash', $options['tx_hash'])
            ->assertJsonPath('safe_status', 'local_code_ready')
            ->assertJsonStructure(['safe_url', 'safe_status_url', 'safe_open_url', 'cabinet_safe_url', 'redirect_url']);
        $this->assertArrayNotHasKey('vouchers', $response->json());
        $this->assertSame($response->json('safe_url'), $response->json('redirect_url'));
        $this->assertStringNotContainsString('/cabinet', $response->json('redirect_url'));

        $order = Order::where('sales_channel', 'meanly_storefront')->firstOrFail();
        $cabinetSafeUrl = $response->json('cabinet_safe_url');
        $this->assertStringContainsString('/cabinet?safe='.$order->uuid, $cabinetSafeUrl);
        $this->assertStringEndsWith('#safe-'.$order->uuid, $cabinetSafeUrl);

        $this->actingAs($user)
            ->get(route('filament.client.pages.dashboard', ['safe' => $order->uuid], false))
            ->assertOk()
            ->assertSee('Сейф закрыт', false)
            ->assertSee('Открыть сейф Passkey', false)
            ->assertDontSee('data-safe-uuid="'.$order->uuid.'"', false);

        $vaultOptions = $this->actingAs($user)
            ->getJson(route('cabinet.vault.passkey.options'))
            ->assertOk()
            ->assertJsonStructure(['unlock_id', 'challenge'])
            ->json();

        $this->actingAs($user)
            ->postJson(route('cabinet.vault.passkey.confirm'), [
                'unlock_id' => $vaultOptions['unlock_id'],
                'assertion' => $this->walletAssertionForChallenge($user, $vaultOptions['challenge']),
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $cabinet = $this->actingAs($user)->get(route('filament.client.pages.dashboard', ['safe' => $order->uuid], false));

        $cabinet
            ->assertOk()
            ->assertSee('id="safe-'.$order->uuid.'"', false)
            ->assertSee('data-safe-uuid="'.$order->uuid.'"', false)
            ->assertSee('data-safe-open-url="'.route('meanly.storefront.orders.safe.open', ['order' => $order->uuid]).'"', false)
            ->assertSee('data-safe-open-button', false)
            ->assertSee('data-safe-inline-panel', false)
            ->assertSee('Открыть отдельно', false)
            ->assertSee('class="vault-card is-focused"', false)
            ->assertDontSee('MEAN-EMAIL-TEST01-ZZ', false)
            ->assertDontSee('target="_blank"', false);

        $this->assertSame(35000, app(\App\Services\BuyerWalletService::class)->balance($user)['available_minor']);
        $this->assertTrue(ProductInventory::where('voucher', 'MEAN-EMAIL-TEST01-ZZ')->firstOrFail()->is_used);
        $this->assertDatabaseHas('wallet_ledger_entries', [
            'user_id' => $user->id,
            'direction' => 'debit',
            'entry_type' => 'BUYER_PURCHASE_DEBIT',
            'amount_minor' => 15000,
            'tx_hash' => $options['tx_hash'],
        ]);
        $this->assertSame('buyer_wallet_rubt', data_get(Order::firstOrFail()->info, 'payment_method'));
    }

    public function test_order_safe_page_hides_code_until_opened(): void
    {
        $product = $this->seedStorefrontCheckoutProduct();
        $user = $this->checkoutUser();
        $this->fundBuyerWallet($user);

        $options = $this->actingAs($user)->postJson(route('meanly.storefront.checkout.wallet.options'), [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertOk()->json();

        $passkey = $user->passkeys()->first();
        $this->mock(\Spatie\LaravelPasskeys\Actions\FindPasskeyToAuthenticateAction::class, function ($mock) use ($passkey) {
            $mock->shouldReceive('execute')->andReturn($passkey);
        });

        $checkout = $this->actingAs($user)->postJson(route('meanly.storefront.checkout.wallet.confirm'), [
            'pending_tx_id' => $options['pending_tx_id'],
            'tx_hash' => $options['tx_hash'],
            'assertion' => $this->walletAssertionForTx($user, $options['tx_hash']),
        ])->assertOk()->json();

        $this->get($checkout['safe_url'])
            ->assertOk()
            ->assertSee('Сейф заказа', false)
            ->assertSee('Сейф готов', false)
            ->assertDontSee('MEAN-EMAIL-TEST01-ZZ', false);
    }

    public function test_order_safe_open_returns_code_for_authorized_buyer(): void
    {
        $product = $this->seedStorefrontCheckoutProduct();
        $user = $this->checkoutUser();
        $this->fundBuyerWallet($user);

        $options = $this->actingAs($user)->postJson(route('meanly.storefront.checkout.wallet.options'), [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertOk()->json();

        $passkey = $user->passkeys()->first();
        $this->mock(\Spatie\LaravelPasskeys\Actions\FindPasskeyToAuthenticateAction::class, function ($mock) use ($passkey) {
            $mock->shouldReceive('execute')->andReturn($passkey);
        });

        $this->actingAs($user)->postJson(route('meanly.storefront.checkout.wallet.confirm'), [
            'pending_tx_id' => $options['pending_tx_id'],
            'tx_hash' => $options['tx_hash'],
            'assertion' => $this->walletAssertionForTx($user, $options['tx_hash']),
        ])->assertOk();

        $order = Order::where('sales_channel', 'meanly_storefront')->firstOrFail();
        $openUrl = \Illuminate\Support\Facades\URL::signedRoute('meanly.storefront.orders.safe.open', [
            'order' => $order->uuid,
        ]);

        $this->postJson($openUrl)
            ->assertOk()
            ->assertJsonPath('status', 'local_code_ready')
            ->assertJsonPath('codes.0.code', 'MEAN-EMAIL-TEST01-ZZ');

        $this->assertNotEmpty(data_get($order->refresh()->info, 'order_safe.opened_at'));
    }

    public function test_cabinet_safe_open_requires_authenticated_owner(): void
    {
        $product = $this->seedStorefrontCheckoutProduct();
        $user = $this->checkoutUser();
        $this->fundBuyerWallet($user);

        $options = $this->actingAs($user)->postJson(route('meanly.storefront.checkout.wallet.options'), [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertOk()->json();

        $passkey = $user->passkeys()->first();
        $this->mock(\Spatie\LaravelPasskeys\Actions\FindPasskeyToAuthenticateAction::class, function ($mock) use ($passkey) {
            $mock->shouldReceive('execute')->andReturn($passkey);
        });

        $this->actingAs($user)->postJson(route('meanly.storefront.checkout.wallet.confirm'), [
            'pending_tx_id' => $options['pending_tx_id'],
            'tx_hash' => $options['tx_hash'],
            'assertion' => $this->walletAssertionForTx($user, $options['tx_hash']),
        ])->assertOk();

        $order = Order::where('sales_channel', 'meanly_storefront')->firstOrFail();
        $openUrl = route('meanly.storefront.orders.safe.open', ['order' => $order->uuid]);
        session()->forget('storefront_order_safe.'.$order->uuid);

        $this->actingAs($user)->postJson($openUrl)
            ->assertOk()
            ->assertJsonPath('status', 'local_code_ready')
            ->assertJsonPath('codes.0.code', 'MEAN-EMAIL-TEST01-ZZ');

        $otherUser = $this->checkoutUser(['email' => 'other-cabinet-buyer@example.test']);

        $this->actingAs($otherUser)
            ->postJson($openUrl)
            ->assertForbidden();
    }

    public function test_public_storefront_availability_blocks_when_provider_has_no_instant_inventory_even_if_preorder_exists(): void
    {
        $fixture = $this->seedProviderBackedStorefrontProduct(preOrder: true);
        $this->mockPublicProviderAvailability(false, true);

        $this->postJson(route('meanly.storefront.checkout.availability'), [
            'product_id' => $fixture['product']->id,
            'quantity' => 1,
        ])->assertOk()
            ->assertJsonPath('status', 'unavailable')
            ->assertJsonPath('available', false)
            ->assertJsonPath('pre_order_supported', true)
            ->assertJsonPath('preorder_available', false)
            ->assertJsonPath('recommended_fulfillment_mode', 'instant')
            ->assertJsonPath('source', 'provider_inventory');
    }

    public function test_public_storefront_checkout_blocks_unavailable_provider_product_before_payment(): void
    {
        $fixture = $this->seedProviderBackedStorefrontProduct(preOrder: true);
        $this->mockPublicProviderAvailability(false, true);

        $this->mock(ProviderHub::class, function ($mock) {
            $mock->shouldNotReceive('forProvider');
        });

        $this->postJson(route('meanly.storefront.checkout'), [
            'product_id' => $fixture['product']->id,
            'quantity' => 1,
            'email' => 'buyer@example.test',
            'name' => 'Buyer',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('availability');
    }

    public function test_provider_backed_entitlement_stock_makes_product_buyable_without_provider_preorder(): void
    {
        $fixture = $this->seedProviderBackedStorefrontProduct(preOrder: true);
        $shop = $fixture['product']->shop;

        ProductInventory::create([
            'shop_id' => $shop->id,
            'sku' => $fixture['product']->sku,
            'nominal_amount' => 25,
            'nominal_currency' => 'USD',
            'voucher' => 'LOCAL-ENTITLEMENT-AVAILABLE',
            'is_used' => false,
            'status' => 'available',
            'expires_at' => now()->addYear(),
        ]);

        $this->mock(\App\Services\WildflowService::class, function ($mock) {
            $mock->shouldNotReceive('checkAvailability');
        });

        $this->postJson(route('meanly.storefront.checkout.availability'), [
            'product_id' => $fixture['product']->id,
            'quantity' => 1,
        ])->assertOk()
            ->assertJsonPath('status', 'available')
            ->assertJsonPath('available', true)
            ->assertJsonPath('source', 'seller_entitlements')
            ->assertJsonPath('pre_order_supported', false)
            ->assertJsonPath('preorder_available', false)
            ->assertJsonPath('recommended_fulfillment_mode', 'instant');
    }

    public function test_provider_backed_entitlement_exchange_reveals_provider_code_not_local_voucher(): void
    {
        $fixture = $this->seedProviderBackedStorefrontProduct(preOrder: false);
        $shop = $fixture['product']->shop;
        ProductInventory::create([
            'shop_id' => $shop->id,
            'sku' => $fixture['product']->sku,
            'nominal_amount' => 25,
            'nominal_currency' => 'USD',
            'voucher' => 'LOCAL-ENTITLEMENT-DO-NOT-REVEAL',
            'is_used' => false,
            'status' => 'available',
            'expires_at' => now()->addYear(),
        ]);

        $driver = new class implements \App\Services\Provider\ProviderDriverInterface {
            public int $createCalls = 0;

            public function setProvider(Provider $provider): self
            {
                return $this;
            }

            public function createOrder(string $sku, string $reference, float $price, int $quantity, array $meta = []): string
            {
                $this->createCalls++;

                return $reference;
            }

            public function getCodes(string $externalOrderId): array
            {
                return ['REAL-PROVIDER-CODE-123'];
            }

            public function getBalance(): float
            {
                return 0.0;
            }

            public function getRates(): array
            {
                return [];
            }
        };

        $this->mock(ProviderHub::class, function ($mock) use ($driver) {
            $mock->shouldReceive('forProvider')
                ->once()
                ->andReturn($driver);
        });

        $response = $this->postJson(route('meanly.storefront.checkout'), [
            'product_id' => $fixture['product']->id,
            'quantity' => 1,
            'email' => 'buyer@example.test',
            'name' => 'Buyer',
        ]);

        $response->assertOk()
            ->assertJsonPath('safe_status', 'provider_code_ready')
            ->assertJsonPath('fulfillment_mode', 'instant')
            ->assertJsonPath('vouchers', []);

        $order = Order::where('sales_channel', 'meanly_storefront')->firstOrFail();
        $item = $order->items()->firstOrFail();
        $inventory = ProductInventory::where('voucher', 'LOCAL-ENTITLEMENT-DO-NOT-REVEAL')->firstOrFail();

        $this->assertSame('REAL-PROVIDER-CODE-123', $item->original_code);
        $this->assertSame('provider_code_ready', data_get($item->client_info, 'provider_redemption.status'));
        $this->assertSame('exchanged', $inventory->status);
        $this->assertSame('exchanged', data_get($item->client_info, 'local_entitlement.status'));
        $this->assertSame(1, $driver->createCalls);

        app(\App\Services\StorefrontFulfillmentService::class)->fulfillProviderOrder($order->refresh());
        $this->assertSame(1, $driver->createCalls);

        $openUrl = \Illuminate\Support\Facades\URL::signedRoute('meanly.storefront.orders.safe.open', [
            'order' => $order->uuid,
        ]);

        $this->postJson($openUrl)
            ->assertOk()
            ->assertJsonPath('status', 'provider_code_ready')
            ->assertJsonPath('codes.0.code', 'REAL-PROVIDER-CODE-123');
    }

    public function test_provider_backed_entitlement_exchange_failure_does_not_reveal_local_voucher(): void
    {
        $fixture = $this->seedProviderBackedStorefrontProduct(preOrder: false);
        $shop = $fixture['product']->shop;
        ProductInventory::create([
            'shop_id' => $shop->id,
            'sku' => $fixture['product']->sku,
            'nominal_amount' => 25,
            'nominal_currency' => 'USD',
            'voucher' => 'LOCAL-ENTITLEMENT-FAILED-SECRET',
            'is_used' => false,
            'status' => 'available',
            'expires_at' => now()->addYear(),
        ]);

        $driver = new class implements \App\Services\Provider\ProviderDriverInterface {
            public function setProvider(Provider $provider): self
            {
                return $this;
            }

            public function createOrder(string $sku, string $reference, float $price, int $quantity, array $meta = []): string
            {
                throw new \RuntimeException('Provider rejected test order');
            }

            public function getCodes(string $externalOrderId): array
            {
                return ['SHOULD-NOT-BE-USED'];
            }

            public function getBalance(): float
            {
                return 0.0;
            }

            public function getRates(): array
            {
                return [];
            }
        };

        $this->mock(ProviderHub::class, function ($mock) use ($driver) {
            $mock->shouldReceive('forProvider')
                ->once()
                ->andReturn($driver);
        });

        $response = $this->postJson(route('meanly.storefront.checkout'), [
            'product_id' => $fixture['product']->id,
            'quantity' => 1,
            'email' => 'buyer@example.test',
            'name' => 'Buyer',
        ]);

        $response->assertOk()
            ->assertJsonPath('safe_status', 'provider_redeem_failed')
            ->assertJsonPath('vouchers', []);

        $order = Order::where('sales_channel', 'meanly_storefront')->firstOrFail();
        $item = $order->items()->firstOrFail();
        $inventory = ProductInventory::where('voucher', 'LOCAL-ENTITLEMENT-FAILED-SECRET')->firstOrFail();

        $this->assertNull($item->original_code);
        $this->assertSame('provider_redeem_failed', data_get($item->client_info, 'provider_redemption.status'));
        $this->assertSame('exchange_failed', $inventory->status);
        $this->assertSame('exchange_failed', data_get($item->client_info, 'local_entitlement.status'));

        $openUrl = \Illuminate\Support\Facades\URL::signedRoute('meanly.storefront.orders.safe.open', [
            'order' => $order->uuid,
        ]);

        $safeResponse = $this->postJson($openUrl)
            ->assertStatus(422)
            ->assertJsonPath('status', 'provider_redeem_failed')
            ->assertJsonPath('codes', null);

        $ticket = Ticket::where('order_id', $order->id)->firstOrFail();

        $safeResponse
            ->assertJsonPath('support_ticket_id', $ticket->id)
            ->assertJsonStructure(['support_ticket_url']);

        $supportUrl = $safeResponse->json('support_ticket_url');

        $this->get($supportUrl)
            ->assertOk()
            ->assertSee('Чат с поддержкой')
            ->assertSee('Тикет #'.$ticket->id)
            ->assertDontSee('/partner-old', false);

        $messagesUrl = \Illuminate\Support\Facades\URL::signedRoute('meanly.storefront.orders.safe.support-ticket.messages', [
            'order' => $order->uuid,
        ]);

        $this->getJson($messagesUrl)
            ->assertOk()
            ->assertJsonPath('ticket.id', $ticket->id)
            ->assertJsonPath('messages.0.role', 'user');

        $replyUrl = \Illuminate\Support\Facades\URL::signedRoute('meanly.storefront.orders.safe.support-ticket.reply', [
            'order' => $order->uuid,
        ]);

        $this->post($replyUrl, ['message' => 'Покупатель ждет проверку выдачи.'])
            ->assertRedirect();

        $this->assertSame($order->shop_id, $ticket->shop_id);
        $this->assertSame('high', $ticket->priority);
        $this->assertSame('open', $ticket->status);
        $this->assertStringContainsString($order->order_id, $ticket->subject);
        $this->assertCount(2, $ticket->refresh()->messages);
    }

    public function test_public_storefront_checkout_rejects_preorder_mode_from_buyer_request(): void
    {
        $product = $this->seedStorefrontCheckoutProduct();

        $this->postJson(route('meanly.storefront.checkout'), [
            'product_id' => $product->id,
            'quantity' => 1,
            'email' => 'buyer@example.test',
            'name' => 'Buyer',
            'fulfillment_mode' => 'preorder',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('fulfillment_mode');
    }

    public function test_public_storefront_checkout_succeeds_with_seller_local_stock(): void
    {
        $product = $this->seedStorefrontCheckoutProduct();

        $this->mock(ProviderHub::class, function ($mock) {
            $mock->shouldNotReceive('forProvider');
        });

        $response = $this->postJson(route('meanly.storefront.checkout'), [
            'product_id' => $product->id,
            'quantity' => 1,
            'email' => 'buyer@example.test',
            'name' => 'Buyer',
        ]);

        $response->assertOk()
            ->assertJsonPath('safe_status', 'local_code_ready')
            ->assertJsonPath('fulfillment_mode', 'instant');

        $order = Order::where('sales_channel', 'meanly_storefront')->firstOrFail();
        $item = $order->items()->firstOrFail();

        $this->assertSame('success', $item->purchase_status);
        $this->assertSame('instant', data_get($order->info, 'fulfillment_mode'));
        $this->assertTrue(ProductInventory::where('voucher', 'MEAN-EMAIL-TEST01-ZZ')->firstOrFail()->is_used);
    }

    public function test_wallet_checkout_options_reject_preorder_mode_from_buyer_request(): void
    {
        $product = $this->seedStorefrontCheckoutProduct();
        $user = $this->checkoutUser();
        $this->fundBuyerWallet($user, 500000);

        $this->actingAs($user)->postJson(route('meanly.storefront.checkout.wallet.options'), [
            'product_id' => $product->id,
            'quantity' => 1,
            'fulfillment_mode' => 'preorder',
            'preorder_acknowledged' => true,
        ])->assertStatus(422)
            ->assertJsonValidationErrors('fulfillment_mode');

        $this->assertSame(500000, app(\App\Services\BuyerWalletService::class)->balance($user)['available_minor']);
    }

    public function test_public_storefront_availability_reports_provider_inventory_source_when_unavailable(): void
    {
        $fixture = $this->seedProviderBackedStorefrontProduct(preOrder: false);
        $this->mockPublicProviderAvailability(false);

        $this->postJson(route('meanly.storefront.checkout.availability'), [
            'product_id' => $fixture['product']->id,
            'quantity' => 1,
        ])->assertOk()
            ->assertJsonPath('status', 'unavailable')
            ->assertJsonPath('pre_order_supported', false)
            ->assertJsonPath('recommended_fulfillment_mode', 'instant')
            ->assertJsonPath('source', 'provider_inventory');
    }

    public function test_public_product_page_disables_checkout_when_provider_inventory_is_unavailable(): void
    {
        $fixture = $this->seedProviderBackedStorefrontProduct(preOrder: true);
        $fixture['product']->forceFill([
            'name' => 'Swissôtel 25 USD US',
            'slug' => 'swissotel-25-us',
        ])->save();
        $this->mockPublicProviderAvailability(false, true);

        $this->get(route('meanly.storefront.products.show', $fixture['product']->slug))
            ->assertOk()
            ->assertSee('Swissôtel 25 USD US', false)
            ->assertSee('Скоро в продаже', false)
            ->assertSee('Поставщик поддерживает предзаказ, но моментальная выдача сейчас недоступна.', false)
            ->assertSee('data-submit-checkout disabled', false);
    }

    public function test_wallet_checkout_options_block_unavailable_provider_product_before_debit(): void
    {
        $fixture = $this->seedProviderBackedStorefrontProduct(preOrder: false);
        $this->mockPublicProviderAvailability(false);
        $user = $this->checkoutUser();
        $this->fundBuyerWallet($user, 500000);

        $this->mock(ProviderHub::class, function ($mock) {
            $mock->shouldNotReceive('forProvider');
        });

        $this->actingAs($user)->postJson(route('meanly.storefront.checkout.wallet.options'), [
            'product_id' => $fixture['product']->id,
            'quantity' => 1,
        ])->assertStatus(422)
            ->assertJsonValidationErrors('availability');

        $this->assertSame(500000, app(\App\Services\BuyerWalletService::class)->balance($user)['available_minor']);
        $this->assertDatabaseMissing('wallet_ledger_entries', [
            'user_id' => $user->id,
            'direction' => 'debit',
            'entry_type' => 'BUYER_PURCHASE_DEBIT',
        ]);
    }

    public function test_public_storefront_checkout_succeeds_with_provider_inventory_without_seller_stock(): void
    {
        $fixture = $this->seedProviderBackedStorefrontProduct(preOrder: false);
        $this->mockPublicProviderAvailability(true);
        $driver = $this->mockProviderRedemptionDriver();

        $response = $this->postJson(route('meanly.storefront.checkout'), [
            'product_id' => $fixture['product']->id,
            'quantity' => 1,
            'email' => 'buyer@example.test',
            'name' => 'Buyer',
        ]);

        $response->assertOk()
            ->assertJsonPath('safe_status', 'provider_code_ready')
            ->assertJsonPath('fulfillment_mode', 'instant')
            ->assertJsonPath('vouchers', []);

        $this->assertSame(1, $driver->createCalls);

        $this->assertDatabaseHas('orders', [
            'sales_channel' => 'meanly_storefront',
        ]);
    }

    public function test_public_storefront_does_not_create_provider_order_when_provider_inventory_is_missing(): void
    {
        $fixture = $this->seedProviderBackedStorefrontProduct(preOrder: false);
        $this->mockPublicProviderAvailability(false);
        $this->mock(ProviderHub::class, function ($mock) {
            $mock->shouldNotReceive('forProvider');
        });

        $response = $this->postJson(route('meanly.storefront.checkout'), [
            'product_id' => $fixture['product']->id,
            'quantity' => 1,
            'email' => 'buyer@example.test',
            'name' => 'Buyer',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('availability');

        $this->assertDatabaseMissing('orders', [
            'sales_channel' => 'meanly_storefront',
        ]);
    }

    public function test_provider_backed_storefront_product_uses_provider_inventory_when_catalog_exists(): void
    {
        $storefront = app(MeanlyFirstPartyStorefrontService::class);
        $shop = $storefront->shop();
        $provider = Provider::create([
            'name' => 'Wildflow Sandbox',
            'type' => 'wildflow-sandbox',
            'is_active' => true,
            'credentials' => ['base_url' => 'http://api.wildflow.test/api/v1/', 'api_key' => 'sandbox'],
        ]);

        WildflowCatalog::create([
            'provider_id' => $provider->id,
            'sku' => 'WF-SWISSOTEL-25-US',
            'service_sku' => '123456',
            'retail_price' => 25,
            'purchase_price' => 23,
            'type' => 'gift_card',
            'data' => ['service_sku' => '123456', 'currency' => 'USD', 'product' => ['title' => 'Swissotel 25 USD US']],
            'is_active' => true,
        ]);

        $product = Product::create([
            'shop_id' => $shop->id,
            'provider_id' => $provider->id,
            'wildflow_catalog_sku' => 'WF-SWISSOTEL-25-US',
            'sku' => 'MEANLY-SWISSOTEL-25-US',
            'name' => 'Swissotel 25 USD US',
            'price_rub' => 250000,
            'type' => 'giftcard',
            'category' => 'Gift Cards',
            'is_active' => true,
        ]);
        ProductSalesChannel::create([
            'product_id' => $product->id,
            'shop_id' => $shop->id,
            'channel' => 'meanly_storefront',
            'is_enabled' => true,
        ]);

        $this->mockPublicProviderAvailability(true);

        $response = $this->postJson(route('meanly.storefront.checkout.availability'), [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'available')
            ->assertJsonPath('available', true)
            ->assertJsonPath('source', 'provider_inventory');
    }

    public function test_provider_backed_storefront_product_uses_provider_inventory_when_catalog_mapping_is_slim(): void
    {
        $storefront = app(MeanlyFirstPartyStorefrontService::class);
        $shop = $storefront->shop();
        $provider = Provider::create([
            'name' => 'Wildflow Sandbox',
            'type' => 'wildflow-sandbox',
            'is_active' => true,
            'credentials' => ['base_url' => 'http://api.wildflow.test/api/v1/', 'api_key' => 'sandbox'],
        ]);

        $providerProduct = ProviderProduct::create([
            'provider_id' => $provider->id,
            'sku' => '2334',
            'market_sku' => '2334',
            'name' => 'Provider Product 2334',
            'purchase_price' => 23,
            'retail_price' => 25,
            'min_price' => 25,
            'max_price' => 25,
            'currency' => 'USD',
            'is_active' => true,
            'data' => ['service_sku' => '2334', 'pre_order' => false],
        ]);

        $product = Product::create([
            'shop_id' => $shop->id,
            'provider_id' => $provider->id,
            'wildflow_catalog_sku' => '2334',
            'sku' => 'MEANLY-PROVIDER-2334',
            'name' => 'Provider Product 2334',
            'price_rub' => 250000,
            'type' => 'giftcard',
            'category' => 'Gift Cards',
            'is_active' => true,
        ]);
        ProductSalesChannel::create([
            'product_id' => $product->id,
            'shop_id' => $shop->id,
            'channel' => 'meanly_storefront',
            'is_enabled' => true,
        ]);

        $this->mockPublicProviderAvailability(true);

        $response = $this->postJson(route('meanly.storefront.checkout.availability'), [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'available')
            ->assertJsonPath('available', true)
            ->assertJsonPath('source', 'provider_inventory');
    }

    public function test_order_safe_rejects_unauthorized_unsigned_access(): void
    {
        $product = $this->seedStorefrontCheckoutProduct();
        $user = $this->checkoutUser();
        $this->fundBuyerWallet($user);

        $options = $this->actingAs($user)->postJson(route('meanly.storefront.checkout.wallet.options'), [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertOk()->json();

        $passkey = $user->passkeys()->first();
        $this->mock(\Spatie\LaravelPasskeys\Actions\FindPasskeyToAuthenticateAction::class, function ($mock) use ($passkey) {
            $mock->shouldReceive('execute')->andReturn($passkey);
        });

        $this->actingAs($user)->postJson(route('meanly.storefront.checkout.wallet.confirm'), [
            'pending_tx_id' => $options['pending_tx_id'],
            'tx_hash' => $options['tx_hash'],
            'assertion' => $this->walletAssertionForTx($user, $options['tx_hash']),
        ])->assertOk();

        $order = Order::where('sales_channel', 'meanly_storefront')->firstOrFail();
        session()->forget('storefront_order_safe.'.$order->uuid);
        $otherUser = $this->checkoutUser(['email' => 'other-buyer@example.test']);

        $this->actingAs($otherUser)
            ->get(route('meanly.storefront.orders.safe.show', ['order' => $order->uuid]))
            ->assertForbidden();
    }

    public function test_operator_workspace_exposes_first_party_storefront_metrics_only_for_meanly_entity(): void
    {
        $storefront = app(MeanlyFirstPartyStorefrontService::class);
        $shop = $storefront->shop();
        $entity = $shop->legalEntity;

        Order::create([
            'order_id' => 'MS-METRICS-1',
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'status' => 'COMPLETED',
            'shop_id' => $shop->id,
            'progress_id' => 4,
            'sales_channel' => 'meanly_storefront',
            'total_amount' => 250,
            'currency' => 'RUB',
        ]);

        $payload = app(PartnerOperatorIntelligenceService::class)->payload($entity);

        $this->assertSame($shop->id, $payload['first_party_storefront']['shop_id']);
        $this->assertSame(1, $payload['first_party_storefront']['storefront_orders_30_days']);
        $this->assertSame(250.0, $payload['first_party_storefront']['storefront_gmv_30_days']);

        $other = LegalEntity::create([
            'name' => 'Non Meanly LLC',
            'short_name' => 'Non Meanly',
            'inn' => '770000099003',
            'available_balance' => 1000,
            'reserved_balance' => 0,
            'currency' => 'RUB',
            'is_active' => true,
        ]);

        $this->assertNull(app(PartnerOperatorIntelligenceService::class)->payload($other)['first_party_storefront']);
    }
}
