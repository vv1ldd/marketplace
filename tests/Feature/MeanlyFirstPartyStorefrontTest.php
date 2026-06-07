<?php

namespace Tests\Feature;

use App\Models\LegalEntity;
use App\Models\Order\Order;
use App\Models\CanonicalProductIdentity;
use App\Models\Order\OrderItems;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\ProductSalesChannel;
use App\Models\Provider;
use App\Models\ProviderProduct;
use App\Models\Shop;
use App\Models\SovereignLedger;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WildflowCatalog;
use App\Services\FinanceService;
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
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => User::ROLE_WALLET_HOLDER, 'guard_name' => 'web']);

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

    private function createCapturedSafeOrder(Product $product, User $user): Order
    {
        $order = Order::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'order_id' => 'MS-CAPTURED-'.\Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(6)),
            'status' => 'COMPLETED',
            'sub_status' => 'DIRECT_STOREFRONT',
            'progress_id' => 4,
            'shop_id' => $product->shop_id,
            'user_id' => $user->id,
            'sales_channel' => 'meanly_storefront',
            'total_amount' => 150,
            'currency' => 'RUB',
            'info' => [
                'payment_status' => 'captured',
                'payment_method' => 'sbp_bank_capture',
            ],
            'client_info' => [
                'buyer_user_id' => $user->id,
                'delivery_email' => $user->email,
            ],
        ]);

        $item = OrderItems::create([
            'key' => 'MEAN-EMAIL-TEST01-ZZ',
            'original_code' => 'MEAN-EMAIL-TEST01-ZZ',
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'order_id' => $order->id,
            'activate_till' => now()->addYear()->format('Y-m-d'),
            'sku' => $product->sku,
            'count' => 1,
            'price_rub' => 15000,
            'type_form_id' => 2,
            'purchase_status' => 'success',
            'client_info' => [
                'buyer_user_id' => $user->id,
                'channel' => 'meanly_storefront',
            ],
        ]);

        ProductInventory::where('voucher', 'MEAN-EMAIL-TEST01-ZZ')->firstOrFail()->update([
            'is_used' => true,
            'status' => 'sold',
            'order_item_id' => $item->id,
            'reservation_reference' => 'test-captured-safe:'.$order->order_id,
            'reserved_at' => now(),
        ]);

        return $order->refresh();
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

    /**
     * @return array{proof_response: array<string, mixed>, private_key: \OpenSSLAsymmetricKey}
     */
    private function signedNativeProofResponse(array $proof): array
    {
        $privateKey = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
        ]);
        $this->assertInstanceOf(\OpenSSLAsymmetricKey::class, $privateKey);

        $details = openssl_pkey_get_details($privateKey);
        $this->assertIsArray($details);
        $x = data_get($details, 'ec.x');
        $y = data_get($details, 'ec.y');
        $this->assertIsString($x);
        $this->assertIsString($y);

        $publicKey = 'base64url:'.$this->base64UrlEncode("\x04".$x.$y);
        $proof['keyPublicKey'] = $publicKey;
        $proof['keyAddress'] = app(\App\Services\L1IdentityService::class)->keyAddressFromPublicKey($publicKey);
        $proof['entityAddress'] ??= 'sl1e_'.substr(hash('sha256', 'simple-l1:v1:entity:native:'.$publicKey), 0, 39);
        $proof['requestHost'] ??= parse_url((string) data_get($proof, 'redirectUri', ''), PHP_URL_HOST);
        $proof['routingDecisionId'] ??= 'rdc_test_'.substr(hash('sha256', $proof['keyAddress']), 0, 12);
        $proof['policyVersion'] ??= 'l1_policy_v0';
        $proof['routingDecision'] ??= [
            'routing_decision_id' => $proof['routingDecisionId'],
            'request_host' => $proof['requestHost'],
            'client_id' => $proof['clientId'] ?? '',
            'policy_version' => $proof['policyVersion'],
            'eligible_keys' => [$proof['keyAddress']],
            'selected_key' => $proof['keyAddress'],
            'selection_reason' => 'single_eligible_key',
            'exclusion_reasons' => [],
            'policy_applied' => ['allowed_relying_parties_or_client_match'],
            'intent' => data_get($proof, 'intent.type', 'identity.session'),
            'timestamp' => now()->timestamp,
        ];
        $proof['signatureAlgorithm'] = 'p256-sha256-der';
        $proof['signaturePayload'] = $this->nativeProofSigningPayload($proof);

        $signature = '';
        $this->assertTrue(openssl_sign($proof['signaturePayload'], $signature, $privateKey, OPENSSL_ALGO_SHA256));
        $proof['signature'] = $this->base64UrlEncode($signature);

        return [
            'private_key' => $privateKey,
            'proof_response' => [
                'protocol' => 'simple-l1',
                'active' => true,
                'proof_token' => 'native-proof-token',
                'proof' => $proof,
                'identity' => [
                    'entity_l1_address' => $proof['entityAddress'],
                    'key_l1_address' => $proof['keyAddress'],
                ],
            ],
        ];
    }

    private function nativeProofSigningPayload(array $proof): string
    {
        return implode("\n", [
            (string) data_get($proof, 'type', ''),
            (string) data_get($proof, 'routingDecisionId', ''),
            (string) data_get($proof, 'policyVersion', ''),
            (string) data_get($proof, 'clientId', ''),
            strtolower((string) data_get($proof, 'requestHost', '')),
            (string) data_get($proof, 'redirectUri', ''),
            (string) data_get($proof, 'state', ''),
            (string) data_get($proof, 'nonce', ''),
            (string) data_get($proof, 'mode', ''),
            strtolower((string) data_get($proof, 'entityAddress', '')),
            strtolower((string) data_get($proof, 'keyAddress', '')),
            (string) data_get($proof, 'issuedAt', ''),
            (string) data_get($proof, 'expiresAt', ''),
            (string) data_get($proof, 'intent.type', ''),
            (string) data_get($proof, 'intent.nonce', ''),
            (string) data_get($proof, 'intent.resource', ''),
        ]);
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

    public function test_public_storefront_offer_uses_pricing_projection_from_market_context(): void
    {
        config(['markets.markets.global.display_currency' => 'USD']);

        $this->mock(FinanceService::class, function ($mock) {
            $mock->shouldReceive('convert')
                ->with(1200.0, 'RUB', 'USD')
                ->andReturn(12.0);
        });

        $storefront = app(MeanlyFirstPartyStorefrontService::class);
        $shop = $storefront->shop();

        $product = Product::create([
            'shop_id' => $shop->id,
            'sku' => 'MEANLY-USD-PROJECTION',
            'name' => 'USD Projection Gift Card',
            'slug' => 'usd-projection-gift-card',
            'price_rub' => 120000,
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

        CanonicalProductIdentity::create([
            'fingerprint' => hash('sha256', 'meanly-usd-projection'),
            'identity_slug' => 'meanly-usd-projection',
            'canonical_category' => 'gift_cards',
            'brand' => 'Meanly',
            'product_family' => 'USD Projection Gift Card',
            'face_value' => 12,
            'face_value_currency' => 'USD',
            'region' => 'US',
            'platform' => 'global',
            'confidence' => 'high',
            'signals' => [],
            'provider_candidates_count' => 0,
            'seller_offers_count' => 1,
            'best_offer_product_id' => $product->id,
            'last_seen_at' => now(),
        ]);

        $response = $this->withHeader('Host', 'meanly.one')->get(route('meanly.storefront.index'));

        $response->assertOk()
            ->assertSee('12.00 USD')
            ->assertDontSee('1 200.00 ₽');
    }

    public function test_public_product_json_ld_uses_pricing_projection_currency(): void
    {
        config(['markets.markets.global.display_currency' => 'USD']);

        $this->mock(FinanceService::class, function ($mock) {
            $mock->shouldReceive('convert')
                ->with(1200.0, 'RUB', 'USD')
                ->andReturn(12.0);
        });

        $storefront = app(MeanlyFirstPartyStorefrontService::class);
        $shop = $storefront->shop();

        $product = Product::create([
            'shop_id' => $shop->id,
            'sku' => 'MEANLY-JSONLD-PROJECTION',
            'name' => 'JSON-LD Projection Gift Card',
            'slug' => 'jsonld-projection-gift-card',
            'price_rub' => 120000,
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

        $response = $this->withHeader('Host', 'meanly.one')->get(route('meanly.storefront.products.show', $product->slug));

        $response->assertOk()
            ->assertSee('"priceCurrency":"USD"', false)
            ->assertSee('"price":12', false)
            ->assertSee('12.00 USD');
    }

    public function test_legacy_public_product_page_uses_pricing_projection_consistently(): void
    {
        config(['markets.markets.global.display_currency' => 'USD']);

        $this->mock(FinanceService::class, function ($mock) {
            $mock->shouldReceive('convert')
                ->with(1200.0, 'RUB', 'USD')
                ->andReturn(12.0);
        });

        $storefront = app(MeanlyFirstPartyStorefrontService::class);
        $shop = $storefront->shop();

        $product = Product::create([
            'shop_id' => $shop->id,
            'sku' => 'MEANLY-LEGACY-PAGE-PROJECTION',
            'name' => 'Legacy Page Projection Gift Card',
            'slug' => 'legacy-page-projection-gift-card',
            'price_rub' => 120000,
            'type' => 'giftcard',
            'category' => 'Gift Cards',
            'is_active' => true,
        ]);

        $response = $this->withHeader('Host', 'meanly.one')->get(route('products.show', $product->slug));

        $response->assertOk()
            ->assertSee('"priceCurrency": "USD"', false)
            ->assertSee('"price": "12"', false)
            ->assertSee('12.00 USD')
            ->assertDontSee('Цена в рублях')
            ->assertDontSee('1 200 ₽');
    }

    public function test_public_product_search_api_returns_projected_display_price_without_storage_price(): void
    {
        config(['markets.markets.global.display_currency' => 'USD']);

        $this->mock(FinanceService::class, function ($mock) {
            $mock->shouldReceive('convert')
                ->with(1200.0, 'RUB', 'USD')
                ->andReturn(12.0);
        });

        $storefront = app(MeanlyFirstPartyStorefrontService::class);
        $shop = $storefront->shop();

        Product::create([
            'shop_id' => $shop->id,
            'sku' => 'MEANLY-SEARCH-PROJECTION',
            'name' => 'Search Projection Gift Card',
            'slug' => 'search-projection-gift-card',
            'price_rub' => 120000,
            'type' => 'giftcard',
            'category' => 'Gift Cards',
            'is_active' => true,
        ]);

        $response = $this->withHeader('Host', 'meanly.one')->getJson(route('products.search', ['query' => 'Search Projection']));

        $response->assertOk()
            ->assertJsonPath('products.0.display_price.amount', 12)
            ->assertJsonPath('products.0.display_price.currency', 'USD')
            ->assertJsonPath('products.0.display_price.label', '12.00 USD');

        $this->assertArrayNotHasKey('price_rub', $response->json('products.0'));
    }

    public function test_canonical_product_page_uses_projected_offer_price_in_html_and_json_ld(): void
    {
        config(['markets.markets.global.display_currency' => 'USD']);

        $this->mock(FinanceService::class, function ($mock) {
            $mock->shouldReceive('convert')
                ->with(1200.0, 'RUB', 'USD')
                ->andReturn(12.0);
        });

        $storefront = app(MeanlyFirstPartyStorefrontService::class);
        $shop = $storefront->shop();

        $product = Product::create([
            'shop_id' => $shop->id,
            'sku' => 'MEANLY-CANONICAL-PROJECTION',
            'name' => 'Canonical Projection Gift Card',
            'slug' => 'canonical-projection-gift-card',
            'price_rub' => 120000,
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

        CanonicalProductIdentity::create([
            'fingerprint' => hash('sha256', 'meanly-canonical-projection'),
            'identity_slug' => 'meanly-canonical-projection',
            'canonical_category' => 'gift_cards',
            'brand' => 'Meanly',
            'product_family' => 'Canonical Projection Gift Card',
            'face_value' => 12,
            'face_value_currency' => 'USD',
            'region' => 'US',
            'platform' => 'global',
            'confidence' => 'high',
            'signals' => [],
            'provider_candidates_count' => 0,
            'seller_offers_count' => 1,
            'best_offer_product_id' => $product->id,
            'last_seen_at' => now(),
        ]);

        $response = $this->withHeader('Host', 'meanly.one')->get(route('meanly.canonical-products.show', 'meanly-canonical-projection'));

        $response->assertOk()
            ->assertSee('"priceCurrency":"USD"', false)
            ->assertSee('"lowPrice":12', false)
            ->assertSee('12 USD')
            ->assertDontSee('1 200.00 ₽');
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
            ->assertJsonPath('vouchers', [])
            ->assertJsonPath('safe_status', 'payment_pending');

        $order = Order::where('sales_channel', 'meanly_storefront')->firstOrFail();
        $this->assertSame($shop->id, $order->shop_id);
        $this->assertSame(150.0, (float) $order->total_amount);

        $this->assertSame('NEW', $order->status);
        $this->assertSame('pending', data_get($order->info, 'payment_status'));

        $inventory = ProductInventory::where('voucher', 'MEAN-ABCDE-TEST01-ZZ')->firstOrFail();
        $this->assertFalse($inventory->is_used);
        $this->assertSame('available', $inventory->status);
        $this->assertNull($inventory->order_item_id);

        $this->assertDatabaseHas('sovereign_ledger', [
            'shop_id' => $shop->id,
            'event_type' => 'ORDER_RECEIVE',
        ]);
        $this->assertDatabaseMissing('sovereign_ledger', [
            'shop_id' => $shop->id,
            'event_type' => 'FINANCE_CAPTURE',
        ]);
        $this->assertDatabaseMissing('sovereign_ledger', [
            'shop_id' => $shop->id,
            'event_type' => 'VOUCHER_SLIP_ISSUED',
        ]);
        $this->assertDatabaseMissing('token_metering_events', [
            'legal_entity_id' => $shop->legal_entity_id,
            'shop_id' => $shop->id,
            'event_type' => 'order_fulfillment',
        ]);
    }

    public function test_authenticated_storefront_checkout_uses_wallet_safe_when_not_gift(): void
    {
        $product = $this->seedStorefrontCheckoutProduct();
        $user = $this->checkoutUser();

        $response = $this->actingAs($user)->postJson(route('meanly.storefront.checkout'), [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('vouchers', [])
            ->assertJsonPath('safe_status', 'payment_pending');

        $order = Order::where('sales_channel', 'meanly_storefront')->firstOrFail();
        $this->assertSame('NEW', $order->status);
        $this->assertSame('pending', data_get($order->info, 'payment_status'));
        $this->assertNull(data_get($order->client_info, 'email'));
        $this->assertNull(data_get($order->client_info, 'delivery_email'));
        $this->assertNull(data_get($order->client_info, 'buyer_email'));
        $this->assertSame($user->sovereignIdentityAddress(), data_get($order->client_info, 'buyer_l1_address'));
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

    public function test_guest_storefront_checkout_with_delivery_email_creates_pending_order_without_fulfillment(): void
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
            ->assertJsonPath('vouchers', [])
            ->assertJsonPath('safe_status', 'payment_pending');

        $order = Order::where('sales_channel', 'meanly_storefront')->firstOrFail();
        $this->assertSame('NEW', $order->status);
        $this->assertSame('pending', data_get($order->info, 'payment_status'));
        $this->assertSame('guest-buyer@example.test', data_get($order->client_info, 'email'));
        $this->assertSame('guest-buyer@example.test', data_get($order->client_info, 'delivery_email'));
        $this->assertNull(data_get($order->client_info, 'buyer_user_id'));
        $this->assertFalse(ProductInventory::where('voucher', 'MEAN-EMAIL-TEST01-ZZ')->firstOrFail()->is_used);
        $this->assertDatabaseMissing('sovereign_ledger', ['event_type' => 'FINANCE_CAPTURE']);
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
        $this->assertStringContainsString(__('catalog.product.send_other_email'), $html);
        $this->assertMatchesRegularExpression('/<input[^>]*id="email"[^>]*data-gift-email[^>]*>/', $html);
        preg_match('/<input[^>]*id="email"[^>]*data-gift-email[^>]*>/', $html, $emailInput);
        $this->assertStringNotContainsString('required', $emailInput[0]);
        $this->assertStringContainsString('email.required = toggle.checked;', $html);
        $this->assertStringContainsString('data-inline-order-safe-template', $html);
        $this->assertStringContainsString('renderInlineOrderSafe', $html);
        $this->assertStringContainsString('renderStandaloneSafeFallback', $html);
        $this->assertStringContainsString(__('product.public.sbp'), $html);
        $this->assertStringContainsString(__('product.public.sbp_soon'), $html);
        $this->assertStringContainsString('openSafe({ automatic: true });', $html);
        $this->assertStringContainsString('safeLink.href = result.cabinet_safe_url;', $html);
        $this->assertStringContainsString(__('product.public.open_code'), $html);
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
        $this->assertStringContainsString(__('product.public.sbp_soon'), $html);
        $this->assertStringContainsString('safeLink.href = result.cabinet_safe_url;', $html);
        $this->assertStringContainsString(__('product.public.open_code'), $html);
        $this->assertStringNotContainsString('window.location.assign(result.cabinet_safe_url || result.redirect_url || result.safe_url)', $html);
        $this->assertStringNotContainsString('window.location.assign(standaloneSafeUrl)', $html);
    }

    public function test_storefront_product_can_connect_external_simple_l1_identity(): void
    {
        $product = $this->seedStorefrontCheckoutProduct('MEANLY-SL1-CONNECT', 'MEAN-SL1-TEST01-ZZ');
        $l1Address = 'sl1e_'.str_repeat('c', 39);

        $this->get(route('meanly.storefront.products.show', $product->slug))
            ->assertOk()
            ->assertSee('Continue with Meanly')
            ->assertSee(route('meanly.simple_l1.connect', ['return_to' => '/store/products/'.$product->slug], false), false);

        \Illuminate\Support\Facades\Cache::put('simple_l1:proof_token:test-proof-handle', 'proof-token', now()->addMinutes(10));

        $this->withSession([
            'simple_l1_identity' => [
                'l1_address' => $l1Address,
                'proof_token_hash' => hash('sha256', 'proof-token'),
                'proof_handle' => 'test-proof-handle',
            ],
        ])->get(route('meanly.storefront.products.show', $product->slug))
            ->assertOk()
            ->assertSee('Meanly connected')
            ->assertSee($l1Address)
            ->assertSee('Reconnect Meanly');
    }

    public function test_simple_l1_connect_passes_marketplace_theme_context_to_pass(): void
    {
        $response = $this->get(route('meanly.simple_l1.connect', [
            'return_to' => '/store',
            'mode' => 'login',
        ]));

        $response->assertOk();
        $location = (string) $response->viewData('authorizeUrl');
        $deepLink = (string) $response->viewData('deepLinkUrl');

        $this->assertStringStartsWith('https://simplel1.online/authorize?', $location);
        $this->assertStringContainsString('client_name=Meanly', $location);
        $this->assertStringContainsString('ui_theme=neobrutalism', $location);
        $this->assertStringContainsString('response_mode=code', $location);
        $this->assertStringStartsWith('simplel1://authorize?', $deepLink);
        $this->assertStringContainsString('client_name=Meanly', $deepLink);
        $this->assertStringContainsString('ui_theme=neobrutalism', $deepLink);
        $this->assertStringContainsString('response_mode=code', $deepLink);
        $response->assertSee(__('auth.simple_l1.identity_confirm.title'), false);
    }

    public function test_simple_l1_connect_skips_handoff_after_user_has_seen_it(): void
    {
        $response = $this
            ->withSession(['simple_l1_handoff_seen.identity_confirm' => now()->toIso8601String()])
            ->get(route('meanly.simple_l1.connect', [
                'return_to' => '/store',
                'mode' => 'login',
            ]));

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');

        $this->assertStringStartsWith('https://simplel1.online/authorize?', $location);
        $this->assertStringContainsString('client_name=Meanly', $location);
    }

    public function test_simple_l1_connect_handoff_is_tracked_per_action(): void
    {
        $response = $this
            ->withSession(['simple_l1_handoff_seen.identity_confirm' => now()->toIso8601String()])
            ->get(route('meanly.simple_l1.connect', [
                'return_to' => '/vault',
                'mode' => 'login',
                'intent_type' => 'meanly.vault.open',
            ]));

        $response->assertOk();
        $response->assertSee(__('auth.simple_l1.vault_open.title'), false);
    }

    public function test_simple_l1_connect_returns_inline_handoff_payload_for_ui_clicks(): void
    {
        $response = $this->getJson(route('meanly.simple_l1.connect', [
            'return_to' => '/vault',
            'mode' => 'login',
            'intent_type' => 'meanly.vault.open',
        ]));

        $response
            ->assertOk()
            ->assertJsonPath('show_handoff', true)
            ->assertJsonPath('handoff.key', 'vault_open')
            ->assertJsonPath('handoff.title', __('auth.simple_l1.vault_open.title'));

        $this->assertStringStartsWith('simplel1://authorize?', (string) $response->json('deep_link_url'));
    }

    public function test_simple_l1_callback_verifies_proof_and_stores_marketplace_session_identity(): void
    {
        $l1Address = 'sl1e_'.str_repeat('d', 39);

        config([
            'simple_l1.identity_provider_url' => 'https://simplel1.online',
            'simple_l1.proof_introspection_path' => '/api/sl1e/proofs/introspect',
        ]);
        Http::fake([
            'https://simplel1.online/api/sl1e/proofs/introspect' => Http::response([
                'protocol' => 'simple-l1',
                'active' => true,
                'proof' => [
                    'type' => 'sl1e.login.proof.v1',
                    'clientId' => config('simple_l1.client_id'),
                    'redirectUri' => route('meanly.simple_l1.callback'),
                    'state' => 'expected-state',
                    'nonce' => 'expected-nonce',
                    'mode' => 'login',
                    'entityAddress' => $l1Address,
                    'keyAddress' => 'sl1_'.str_repeat('e', 40),
                    'alias' => 'selimmmm@simplelayer.one',
                    'username' => 'identity-user@example.test',
                    'displayName' => 'Identity User',
                    'expiresAt' => now()->addMinutes(5)->toIso8601String(),
                ],
            ]),
        ]);

        $this->withSession([
            'simple_l1_connect.state' => 'expected-state',
            'simple_l1_connect.nonce' => 'expected-nonce',
            'simple_l1_connect.client_id' => config('simple_l1.client_id'),
            'simple_l1_connect.redirect_uri' => route('meanly.simple_l1.callback'),
            'simple_l1_connect.mode' => 'login',
            'simple_l1_connect.return_to' => '/store/products/meanly-sl1-connect',
        ])->post('/simple-l1/callback', [
            'state' => 'expected-state',
            'proof_token' => 'proof-token',
        ])
            ->assertRedirect('/store/products/meanly-sl1-connect')
            ->assertSessionHas('sovereign_l1_address', $l1Address)
            ->assertSessionHas('simple_l1_identity.l1_address', $l1Address)
            ->assertSessionHas('simple_l1_identity.entity_l1_address', $l1Address)
            ->assertSessionHas('simple_l1_identity.key_l1_address', 'sl1_'.str_repeat('e', 40))
            ->assertSessionHas('simple_l1_identity.alias', 'selimmmm@simplelayer.one')
            ->assertSessionMissing('simple_l1_identity.proof_token')
            ->assertSessionHas('simple_l1_identity.proof_token_hash', hash('sha256', 'proof-token'));

        $this->assertAuthenticated();
        $this->assertSame($l1Address, data_get(auth()->user()?->meta, 'entity_l1_address'));
        $this->assertSame('external_identity_provider', data_get(auth()->user()?->meta, 'simple_l1.identity_rule'));
        $this->assertSame('selimmmm@simplelayer.one', data_get(auth()->user()?->meta, 'simple_l1.alias'));
        $this->assertSame('selimmmm', auth()->user()?->first_name);
        $this->assertSame('selimmmm', data_get(auth()->user()?->meta, 'display_name'));

        $connectEvent = SovereignLedger::where('event_type', 'IDENTITY_CONNECT_EXTERNAL_INTENT')->firstOrFail();
        $this->assertSame('identity.connect_external', data_get($connectEvent->payload, 'intent_type'));
        $this->assertSame($l1Address, data_get($connectEvent->payload, 'connected_entity_l1_address'));
        $this->assertSame(hash('sha256', 'proof-token'), data_get($connectEvent->payload, 'proof_token_hash'));
    }

    public function test_simple_l1_callback_exchanges_authorization_code_over_top_level_get(): void
    {
        $l1Address = 'sl1e_'.str_repeat('d', 39);

        config([
            'simple_l1.identity_provider_url' => 'https://simplel1.online',
            'simple_l1.proof_introspection_path' => '/api/sl1e/proofs/introspect',
        ]);
        Http::fake([
            'https://simplel1.online/api/sl1e/authorization-code/exchange' => Http::response([
                'success' => true,
                'active' => true,
                'proof_token' => 'proof-token-from-code',
                'proof' => [
                    'type' => 'sl1e.login.proof.v1',
                    'clientId' => config('simple_l1.client_id'),
                    'redirectUri' => route('meanly.simple_l1.callback'),
                    'state' => 'expected-state',
                    'nonce' => 'expected-nonce',
                    'mode' => 'login',
                    'entityAddress' => $l1Address,
                    'keyAddress' => 'sl1_'.str_repeat('e', 40),
                    'expiresAt' => now()->addMinutes(5)->toIso8601String(),
                ],
            ]),
        ]);

        $this->withSession([
            'simple_l1_connect.state' => 'expected-state',
            'simple_l1_connect.nonce' => 'expected-nonce',
            'simple_l1_connect.client_id' => config('simple_l1.client_id'),
            'simple_l1_connect.redirect_uri' => route('meanly.simple_l1.callback'),
            'simple_l1_connect.mode' => 'login',
            'simple_l1_connect.return_to' => '/store/products/meanly-sl1-connect',
        ])->get('/simple-l1/callback?state=expected-state&code=sl1c_test')
            ->assertRedirect('/store/products/meanly-sl1-connect')
            ->assertSessionHas('simple_l1_identity.proof_token_hash', hash('sha256', 'proof-token-from-code'))
            ->assertSessionMissing('simple_l1_identity.proof_token');

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/api/sl1e/authorization-code/exchange')
            && $request['code'] === 'sl1c_test'
            && $request['client_id'] === config('simple_l1.client_id')
            && $request['redirect_uri'] === route('meanly.simple_l1.callback'));
    }

    public function test_simple_l1_callback_accepts_native_direct_proof_without_server_round_trip(): void
    {
        config(['simple_l1.accept_native_direct_proof' => true]);
        $signed = $this->signedNativeProofResponse([
            'type' => 'sl1e.login.proof.v1',
            'clientId' => config('simple_l1.client_id'),
            'redirectUri' => route('meanly.simple_l1.callback'),
            'state' => 'expected-state',
            'nonce' => 'expected-nonce',
            'mode' => 'login',
            'displayName' => 'Native User',
            'issuedAt' => now()->toIso8601String(),
            'expiresAt' => now()->addMinutes(5)->toIso8601String(),
        ]);
        $proofResponse = $signed['proof_response'];
        $l1Address = data_get($proofResponse, 'proof.entityAddress');
        $proofPayload = rtrim(strtr(base64_encode(json_encode($proofResponse, JSON_UNESCAPED_SLASHES)), '+/', '-_'), '=');

        Http::fake();

        $this->withSession([
            'simple_l1_connect.state' => 'expected-state',
            'simple_l1_connect.nonce' => 'expected-nonce',
            'simple_l1_connect.client_id' => config('simple_l1.client_id'),
            'simple_l1_connect.redirect_uri' => route('meanly.simple_l1.callback'),
            'simple_l1_connect.mode' => 'login',
            'simple_l1_connect.return_to' => '/store/products/meanly-sl1-connect',
        ])->get('/simple-l1/callback?state=expected-state&proof_response='.$proofPayload)
            ->assertRedirect('/store/products/meanly-sl1-connect')
            ->assertSessionHas('sovereign_l1_address', $l1Address)
            ->assertSessionHas('simple_l1_identity.entity_l1_address', $l1Address)
            ->assertSessionHas('simple_l1_identity.proof_token_hash', hash('sha256', 'native-proof-token'));

        Http::assertNothingSent();
        $this->assertAuthenticated();
        $this->assertSame('Native User', auth()->user()?->first_name);
        $this->assertDatabaseHas('simple_l1_identity_keys', [
            'entity_l1_address' => $l1Address,
            'key_l1_address' => data_get($proofResponse, 'proof.keyAddress'),
            'key_type' => 'native_macos_p256',
            'revoked_at' => null,
        ]);
        $identityKey = \App\Models\SimpleL1IdentityKey::where('key_l1_address', data_get($proofResponse, 'proof.keyAddress'))->firstOrFail();
        $this->assertSame(data_get($proofResponse, 'proof.routingDecisionId'), data_get($identityKey->metadata, 'last_routing_decision_id'));
        $this->assertSame('single_eligible_key', data_get($identityKey->metadata, 'last_routing_decision.selection_reason'));
        $this->assertSame('PROOF_ACCEPTED', data_get($identityKey->metadata, 'last_verification_result.decision'));
        $this->assertSame('passed', data_get($identityKey->metadata, 'last_verification_result.verification_steps.signature'));
        $this->assertSame('passed', data_get($identityKey->metadata, 'last_verification_result.verification_steps.key_binding'));
        $this->assertContains('ROUTING_DECISION_MATCH', data_get($identityKey->metadata, 'last_verification_result.diagnostic_signals'));

        $connectEvent = SovereignLedger::where('event_type', 'IDENTITY_CONNECT_EXTERNAL_INTENT')->latest('id')->firstOrFail();
        $this->assertSame(data_get($proofResponse, 'proof.routingDecisionId'), data_get($connectEvent->payload, 'routing_decision_id'));
        $this->assertSame('l1_policy_v0', data_get($connectEvent->payload, 'policy_version'));
        $this->assertSame('PROOF_ACCEPTED', data_get($connectEvent->payload, 'verification_result.decision'));
        $this->assertSame(data_get($identityKey->metadata, 'last_verification_result_id'), data_get($connectEvent->payload, 'verification_result_id'));
    }

    public function test_simple_l1_callback_rejects_native_direct_proof_with_bad_signature(): void
    {
        config(['simple_l1.accept_native_direct_proof' => true]);
        $signed = $this->signedNativeProofResponse([
            'type' => 'sl1e.login.proof.v1',
            'clientId' => config('simple_l1.client_id'),
            'redirectUri' => route('meanly.simple_l1.callback'),
            'state' => 'expected-state',
            'nonce' => 'expected-nonce',
            'mode' => 'login',
            'displayName' => 'Native User',
            'issuedAt' => now()->toIso8601String(),
            'expiresAt' => now()->addMinutes(5)->toIso8601String(),
        ]);
        $proofResponse = $signed['proof_response'];
        data_set($proofResponse, 'proof.signature', $this->base64UrlEncode('bad-signature'));
        $proofPayload = rtrim(strtr(base64_encode(json_encode($proofResponse, JSON_UNESCAPED_SLASHES)), '+/', '-_'), '=');

        Http::fake();
        \Illuminate\Support\Facades\Log::spy();

        $this->withSession([
            'simple_l1_connect.state' => 'expected-state',
            'simple_l1_connect.nonce' => 'expected-nonce',
            'simple_l1_connect.client_id' => config('simple_l1.client_id'),
            'simple_l1_connect.redirect_uri' => route('meanly.simple_l1.callback'),
            'simple_l1_connect.mode' => 'login',
            'simple_l1_connect.return_to' => '/store/products/meanly-sl1-connect',
        ])->get('/simple-l1/callback?state=expected-state&proof_response='.$proofPayload)
            ->assertForbidden();

        Http::assertNothingSent();
        $this->assertGuest();
        \Illuminate\Support\Facades\Log::shouldHaveReceived('warning')
            ->once()
            ->with('simple_l1.native_direct_proof_rejected', \Mockery::on(fn ($context) => data_get($context, 'verification_result.accepted') === false
                && data_get($context, 'verification_result.decision') === 'SIGNATURE_REJECTED'
                && data_get($context, 'verification_result.verification_steps.signature') === 'failed'
                && in_array('ROUTING_DECISION_MATCH', data_get($context, 'verification_result.diagnostic_signals', []), true)));
    }

    public function test_simple_l1_callback_rejects_unregistered_native_key_claiming_existing_entity(): void
    {
        config(['simple_l1.accept_native_direct_proof' => true]);
        $existingEntityAddress = 'sl1e_'.str_repeat('c', 39);
        User::factory()->create([
            'entity_l1_address' => $existingEntityAddress,
            'identity_provider' => 'identity_wildflow',
            'meta' => ['entity_l1_address' => $existingEntityAddress],
        ]);

        $signed = $this->signedNativeProofResponse([
            'type' => 'sl1e.login.proof.v1',
            'clientId' => config('simple_l1.client_id'),
            'redirectUri' => route('meanly.simple_l1.callback'),
            'state' => 'expected-state',
            'nonce' => 'expected-nonce',
            'mode' => 'login',
            'entityAddress' => $existingEntityAddress,
            'displayName' => 'Native User',
            'issuedAt' => now()->toIso8601String(),
            'expiresAt' => now()->addMinutes(5)->toIso8601String(),
        ]);
        $proofResponse = $signed['proof_response'];
        $proofPayload = rtrim(strtr(base64_encode(json_encode($proofResponse, JSON_UNESCAPED_SLASHES)), '+/', '-_'), '=');

        Http::fake();

        $this->withSession([
            'simple_l1_connect.state' => 'expected-state',
            'simple_l1_connect.nonce' => 'expected-nonce',
            'simple_l1_connect.client_id' => config('simple_l1.client_id'),
            'simple_l1_connect.redirect_uri' => route('meanly.simple_l1.callback'),
            'simple_l1_connect.mode' => 'login',
            'simple_l1_connect.return_to' => '/store/products/meanly-sl1-connect',
        ])->get('/simple-l1/callback?state=expected-state&proof_response='.$proofPayload)
            ->assertForbidden();

        Http::assertNothingSent();
        $this->assertGuest();
        $this->assertDatabaseMissing('simple_l1_identity_keys', [
            'key_l1_address' => data_get($proofResponse, 'proof.keyAddress'),
        ]);
    }

    public function test_simple_l1_connect_enrolls_native_key_for_existing_entity(): void
    {
        config(['simple_l1.accept_native_direct_proof' => true]);
        $existingEntityAddress = 'sl1e_'.str_repeat('d', 39);
        $existingUser = User::factory()->create([
            'entity_l1_address' => $existingEntityAddress,
            'identity_provider' => 'identity_wildflow',
            'meta' => ['entity_l1_address' => $existingEntityAddress],
        ]);

        $signed = $this->signedNativeProofResponse([
            'type' => 'sl1e.login.proof.v1',
            'clientId' => config('simple_l1.client_id'),
            'redirectUri' => route('meanly.simple_l1.callback'),
            'state' => 'expected-state',
            'nonce' => 'expected-nonce',
            'mode' => 'login',
            'entityAddress' => $existingEntityAddress,
            'displayName' => 'Native User',
            'issuedAt' => now()->toIso8601String(),
            'expiresAt' => now()->addMinutes(5)->toIso8601String(),
        ]);
        $proofResponse = $signed['proof_response'];
        $proofPayload = rtrim(strtr(base64_encode(json_encode($proofResponse, JSON_UNESCAPED_SLASHES)), '+/', '-_'), '=');

        Http::fake();

        $response = $this->withSession([
            'simple_l1_connect.state' => 'expected-state',
            'simple_l1_connect.nonce' => 'expected-nonce',
            'simple_l1_connect.client_id' => config('simple_l1.client_id'),
            'simple_l1_connect.redirect_uri' => route('meanly.simple_l1.callback'),
            'simple_l1_connect.mode' => 'login',
            'simple_l1_connect.flow' => 'connect',
            'simple_l1_connect.return_to' => '/vault',
            'simple_l1_connect.intent' => [
                'intent_type' => 'meanly.vault.open',
                'intent_title' => 'Open Meanly Vault',
                'intent_cta' => 'Continue with Meanly',
            ],
        ])->get('/simple-l1/callback?state=expected-state&proof_response='.$proofPayload);

        $response->assertRedirect();
        $response->assertRedirect('/vault');
        Http::assertNothingSent();
        $this->assertAuthenticatedAs($existingUser);
        $response->assertSessionHas('simple_l1_identity.entity_l1_address', $existingEntityAddress);
        $this->assertDatabaseHas('simple_l1_identity_keys', [
            'user_id' => $existingUser->id,
            'entity_l1_address' => $existingEntityAddress,
            'key_l1_address' => data_get($proofResponse, 'proof.keyAddress'),
        ]);

        $this->get('/vault')->assertOk();
    }

    public function test_simple_l1_callback_rejects_registered_native_key_for_disallowed_host(): void
    {
        config(['simple_l1.accept_native_direct_proof' => true]);
        $signed = $this->signedNativeProofResponse([
            'type' => 'sl1e.login.proof.v1',
            'clientId' => config('simple_l1.client_id'),
            'redirectUri' => route('meanly.simple_l1.callback'),
            'requestHost' => 'meanly.test',
            'state' => 'expected-state',
            'nonce' => 'expected-nonce',
            'mode' => 'login',
            'displayName' => 'Native User',
            'issuedAt' => now()->toIso8601String(),
            'expiresAt' => now()->addMinutes(5)->toIso8601String(),
        ]);
        $proofResponse = $signed['proof_response'];
        $user = User::factory()->create([
            'entity_l1_address' => data_get($proofResponse, 'proof.entityAddress'),
            'identity_provider' => 'identity_wildflow',
        ]);
        \App\Models\SimpleL1IdentityKey::create([
            'user_id' => $user->id,
            'entity_l1_address' => data_get($proofResponse, 'proof.entityAddress'),
            'key_l1_address' => data_get($proofResponse, 'proof.keyAddress'),
            'key_type' => 'native_macos_p256',
            'public_key' => data_get($proofResponse, 'proof.keyPublicKey'),
            'public_key_hash' => hash('sha256', (string) data_get($proofResponse, 'proof.keyPublicKey')),
            'trust_level' => 'device_user_presence',
            'metadata' => [
                'allowed_relying_parties' => ['other.test'],
                'allowed_clients' => [],
            ],
        ]);

        $proofPayload = rtrim(strtr(base64_encode(json_encode($proofResponse, JSON_UNESCAPED_SLASHES)), '+/', '-_'), '=');
        Http::fake();
        \Illuminate\Support\Facades\Log::spy();

        $this->withSession([
            'simple_l1_connect.state' => 'expected-state',
            'simple_l1_connect.nonce' => 'expected-nonce',
            'simple_l1_connect.client_id' => config('simple_l1.client_id'),
            'simple_l1_connect.redirect_uri' => route('meanly.simple_l1.callback'),
            'simple_l1_connect.mode' => 'login',
            'simple_l1_connect.return_to' => '/store/products/meanly-sl1-connect',
        ])->get('/simple-l1/callback?state=expected-state&proof_response='.$proofPayload)
            ->assertForbidden();

        Http::assertNothingSent();
        $this->assertGuest();
        \Illuminate\Support\Facades\Log::shouldHaveReceived('warning')
            ->once()
            ->with('simple_l1.native_direct_proof_rejected', \Mockery::on(fn ($context) => data_get($context, 'verification_result.accepted') === false
                && data_get($context, 'verification_result.decision') === 'HOST_REJECTED'
                && data_get($context, 'verification_result.verification_steps.host_policy') === 'failed'
                && in_array('ROUTING_DECISION_MATCH', data_get($context, 'verification_result.diagnostic_signals', []), true)));
    }

    public function test_simple_l1_callback_rejects_proof_with_mismatched_nonce(): void
    {
        $l1Address = 'sl1e_'.str_repeat('d', 39);

        config([
            'simple_l1.identity_provider_url' => 'https://simplel1.online',
            'simple_l1.proof_introspection_path' => '/api/sl1e/proofs/introspect',
        ]);
        Http::fake([
            'https://simplel1.online/api/sl1e/proofs/introspect' => Http::response([
                'protocol' => 'simple-l1',
                'active' => true,
                'proof' => [
                    'type' => 'sl1e.login.proof.v1',
                    'clientId' => config('simple_l1.client_id'),
                    'redirectUri' => route('meanly.simple_l1.callback'),
                    'state' => 'expected-state',
                    'nonce' => 'wrong-nonce',
                    'mode' => 'login',
                    'entityAddress' => $l1Address,
                    'keyAddress' => 'sl1_'.str_repeat('e', 40),
                    'expiresAt' => now()->addMinutes(5)->toIso8601String(),
                ],
            ]),
        ]);

        $this->withSession([
            'simple_l1_connect.state' => 'expected-state',
            'simple_l1_connect.nonce' => 'expected-nonce',
            'simple_l1_connect.client_id' => config('simple_l1.client_id'),
            'simple_l1_connect.redirect_uri' => route('meanly.simple_l1.callback'),
            'simple_l1_connect.mode' => 'login',
            'simple_l1_connect.return_to' => '/store/products/meanly-sl1-connect',
        ])->post('/simple-l1/callback', [
            'state' => 'expected-state',
            'proof_token' => 'proof-token',
        ])
            ->assertForbidden();

        $this->assertGuest();
    }

    public function test_simple_l1_callback_rejects_wallet_already_owned_by_another_user(): void
    {
        $l1Address = 'sl1e_'.str_repeat('d', 39);
        $owner = User::factory()->create([
            'entity_l1_address' => $l1Address,
            'meta' => ['entity_l1_address' => $l1Address],
        ]);
        $current = User::factory()->create();
        $currentAddress = $current->sovereignIdentityAddress();
        \Spatie\LaravelPasskeys\Models\Passkey::factory()->create([
            'authenticatable_id' => $current->id,
        ]);

        config([
            'simple_l1.identity_provider_url' => 'https://simplel1.online',
            'simple_l1.proof_introspection_path' => '/api/sl1e/proofs/introspect',
        ]);
        Http::fake([
            'https://simplel1.online/api/sl1e/proofs/introspect' => Http::response([
                'protocol' => 'simple-l1',
                'active' => true,
                'proof' => [
                    'type' => 'sl1e.login.proof.v1',
                    'clientId' => config('simple_l1.client_id'),
                    'redirectUri' => route('meanly.simple_l1.callback'),
                    'state' => 'expected-state',
                    'nonce' => 'expected-nonce',
                    'mode' => 'login',
                    'entityAddress' => $l1Address,
                    'keyAddress' => 'sl1_'.str_repeat('e', 40),
                    'expiresAt' => now()->addMinutes(5)->toIso8601String(),
                ],
            ]),
        ]);

        $this->actingAs($current)
            ->withSession([
                'simple_l1_connect.state' => 'expected-state',
                'simple_l1_connect.nonce' => 'expected-nonce',
                'simple_l1_connect.client_id' => config('simple_l1.client_id'),
                'simple_l1_connect.redirect_uri' => route('meanly.simple_l1.callback'),
                'simple_l1_connect.mode' => 'login',
                'simple_l1_connect.return_to' => '/store/products/meanly-sl1-connect',
            ])->post('/simple-l1/callback', [
                'state' => 'expected-state',
                'proof_token' => 'proof-token',
            ])
            ->assertStatus(409);

        $this->assertSame($l1Address, $owner->refresh()->sovereignIdentityAddress());
        $this->assertSame($currentAddress, $current->refresh()->sovereignIdentityAddress());
    }

    public function test_simple_l1_status_hides_proof_token_and_raw_proof(): void
    {
        $this->withSession([
            'simple_l1_identity' => [
                'l1_address' => 'sl1e_'.str_repeat('d', 39),
                'entity_l1_address' => 'sl1e_'.str_repeat('d', 39),
                'key_l1_address' => 'sl1_'.str_repeat('e', 40),
                'proof_token' => 'secret-proof-token',
                'proof' => ['challenge' => 'secret-challenge'],
                'mode' => 'login',
                'protocol' => 'simple-l1',
                'connected_at' => now()->toIso8601String(),
            ],
        ])->getJson(route('meanly.simple_l1.status'))
            ->assertOk()
            ->assertJsonMissingPath('identity.proof_token')
            ->assertJsonMissingPath('identity.proof');
    }

    public function test_simple_l1_complete_requires_connected_session_identity(): void
    {
        $this->get(route('meanly.simple_l1.complete', ['next' => '/business/register']))
            ->assertForbidden();
    }

    public function test_simple_l1_complete_redirects_directly_inside_marketplace(): void
    {
        $this->withSession([
            'simple_l1_identity' => [
                'entity_l1_address' => 'sl1e_'.str_repeat('d', 39),
            ],
        ])->get(route('meanly.simple_l1.complete', ['next' => '/business/register']))
            ->assertRedirect('/business/register');
    }

    public function test_storefront_checkout_submits_simple_l1_intent_and_stores_projection(): void
    {
        $product = $this->seedStorefrontCheckoutProduct('MEANLY-SL1-INTENT', 'MEAN-SL1-INTENT-ZZ');
        $l1Address = 'sl1e_'.str_repeat('f', 39);
        $keyAddress = 'sl1_'.str_repeat('a', 40);

        config(['simple_l1.identity_provider_url' => 'https://api.wildflow.test']);
        Http::fake([
            'https://api.wildflow.test/api/simple-l1/intents' => fn ($request) => Http::response([
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
                    'payload_hash' => (string) $request['payload']['payload_hash'],
                    'decision' => 'allow',
                    'reason_codes' => ['grant.allow.matched'],
                ],
            ], 201),
        ]);

        \Illuminate\Support\Facades\Cache::put('simple_l1:proof_token:test-checkout-handle', 'proof-token', now()->addMinutes(10));

        $this->withSession([
            'simple_l1_identity' => [
                'l1_address' => $l1Address,
                'entity_l1_address' => $l1Address,
                'key_l1_address' => $keyAddress,
                'proof_token_hash' => hash('sha256', 'proof-token'),
                'proof_handle' => 'test-checkout-handle',
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
        $this->assertSame(data_get($order->info, 'checkout_payload_hash'), data_get($order->info, 'simple_l1.payload_hash'));
        $this->assertNull(data_get($order->info, 'simple_l1.receipt'));

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/api/simple-l1/intents')
            && $request['capability'] === 'marketplace.checkout.create'
            && $request['scope'] === 'marketplace:meanly'
            && $request['proof_token'] === 'proof-token'
            && $request['payload']['payload_hash'] === data_get($order->info, 'checkout_payload_hash'));
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

        \Illuminate\Support\Facades\Cache::put('simple_l1:proof_token:test-rejected-handle', 'proof-token', now()->addMinutes(10));

        $this->withSession([
            'simple_l1_identity' => [
                'l1_address' => $l1Address,
                'entity_l1_address' => $l1Address,
                'proof_token_hash' => hash('sha256', 'proof-token'),
                'proof_handle' => 'test-rejected-handle',
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

    public function test_wallet_checkout_options_are_retired_to_sl1_wallet(): void
    {
        $product = $this->seedStorefrontCheckoutProduct();
        $user = $this->checkoutUser();

        $this->actingAs($user)->postJson(route('meanly.storefront.checkout.wallet.options'), [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertStatus(410)
            ->assertJsonPath('message', __('runtime.payment.rubt_wallet'));
    }

    public function test_wallet_checkout_confirm_is_retired_and_does_not_consume_stock(): void
    {
        $product = $this->seedStorefrontCheckoutProduct();
        $user = $this->checkoutUser();

        $this->actingAs($user)->postJson(route('meanly.storefront.checkout.wallet.confirm'), [
            'pending_tx_id' => 'retired',
            'tx_hash' => str_repeat('a', 64),
            'assertion' => [],
        ])->assertStatus(410)
            ->assertJsonPath('message', __('runtime.payment.rubt_bank'));

        $this->assertFalse(ProductInventory::where('voucher', 'MEAN-EMAIL-TEST01-ZZ')->firstOrFail()->is_used);
        $this->assertDatabaseMissing('wallet_ledger_entries', [
            'user_id' => $user->id,
            'direction' => 'debit',
        ]);
    }

    public function test_wallet_checkout_no_longer_checks_local_rubt_balance(): void
    {
        $product = $this->seedStorefrontCheckoutProduct();
        $user = $this->checkoutUser();

        $this->actingAs($user)->postJson(route('meanly.storefront.checkout.wallet.options'), [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertStatus(410);

        $this->assertFalse(ProductInventory::where('voucher', 'MEAN-EMAIL-TEST01-ZZ')->firstOrFail()->is_used);
    }

    public function test_legacy_checkout_rejects_rubt_payment_method_without_passkey_proof(): void
    {
        $product = $this->seedStorefrontCheckoutProduct();
        $user = $this->checkoutUser();

        $this->actingAs($user)->postJson(route('meanly.storefront.checkout'), [
            'product_id' => $product->id,
            'quantity' => 1,
            'payment_method' => 'rubt',
        ])->assertStatus(422)->assertJsonValidationErrors('payment_method');

        $this->assertFalse(ProductInventory::where('voucher', 'MEAN-EMAIL-TEST01-ZZ')->firstOrFail()->is_used);
    }

    public function test_storefront_checkout_rejects_sbp_stub_without_creating_paid_order(): void
    {
        $product = $this->seedStorefrontCheckoutProduct();

        $this->postJson(route('meanly.storefront.checkout'), [
            'product_id' => $product->id,
            'quantity' => 1,
            'email' => 'sbp-buyer@example.test',
            'payment_method' => 'sbp',
        ])->assertStatus(422)->assertJsonValidationErrors('payment_method');

        $this->assertDatabaseMissing('orders', [
            'sales_channel' => 'meanly_storefront',
        ]);
        $this->assertFalse(ProductInventory::where('voucher', 'MEAN-EMAIL-TEST01-ZZ')->firstOrFail()->is_used);
    }

    public function test_wallet_checkout_no_longer_debits_or_fulfills_orders(): void
    {
        $product = $this->seedStorefrontCheckoutProduct();
        $user = $this->checkoutUser();

        $response = $this->actingAs($user)->postJson(route('meanly.storefront.checkout.wallet.confirm'), [
            'pending_tx_id' => 'retired',
            'tx_hash' => str_repeat('b', 64),
            'assertion' => $this->walletAssertionForTx($user, str_repeat('b', 64)),
        ]);

        $response->assertStatus(410)
            ->assertJsonPath('message', __('runtime.payment.rubt_bank'));

        $this->assertSame(0, Order::where('sales_channel', 'meanly_storefront')->count());
        $this->assertFalse(ProductInventory::where('voucher', 'MEAN-EMAIL-TEST01-ZZ')->firstOrFail()->is_used);
        $this->assertDatabaseMissing('wallet_ledger_entries', [
            'user_id' => $user->id,
            'direction' => 'debit',
            'entry_type' => 'BUYER_PURCHASE_DEBIT',
        ]);
    }

    public function test_order_safe_page_hides_code_until_opened(): void
    {
        $product = $this->seedStorefrontCheckoutProduct();
        $user = $this->checkoutUser();
        $order = $this->createCapturedSafeOrder($product, $user);
        $safeUrl = \Illuminate\Support\Facades\URL::signedRoute('meanly.storefront.orders.safe.show', [
            'order' => $order->uuid,
        ]);

        $this->get($safeUrl)
            ->assertOk()
            ->assertSee(__('storefront.safe.heading'), false)
            ->assertSee(__('storefront.safe.ready'), false)
            ->assertDontSee('MEAN-EMAIL-TEST01-ZZ', false);
    }

    public function test_vault_requires_fresh_sl1_proof_even_when_identity_is_connected(): void
    {
        $user = User::factory()->create([
            'first_name' => 'SL1E Connected',
            'meta' => [
                'simple_l1' => [
                    'identity_rule' => 'external_identity_provider',
                ],
            ],
        ]);
        $entityAddress = $user->sovereignIdentityAddress();

        $this->actingAs($user)
            ->withSession([
                'simple_l1_identity' => [
                    'entity_l1_address' => $entityAddress,
                    'l1_address' => $entityAddress,
                    'key_l1_address' => 'sl1_'.str_repeat('f', 40),
                ],
            ])
            ->get(route('cabinet.dashboard', [], false))
            ->assertOk()
            ->assertSee('Сейф закрыт', false)
            ->assertSee('Открыть сейф', false)
            ->assertDontSee('Создать SL1E Passkey', false);
    }

    public function test_order_safe_open_returns_code_for_authorized_buyer(): void
    {
        $product = $this->seedStorefrontCheckoutProduct();
        $user = $this->checkoutUser();
        $order = $this->createCapturedSafeOrder($product, $user);
        $openUrl = \Illuminate\Support\Facades\URL::signedRoute('meanly.storefront.orders.safe.open', [
            'order' => $order->uuid,
        ]);

        $this->postJson($openUrl)
            ->assertOk()
            ->assertJsonPath('status', 'local_code_ready')
            ->assertJsonPath('codes.0.code', 'MEAN-EMAIL-TEST01-ZZ');

        $this->assertNotEmpty(data_get($order->refresh()->info, 'order_safe.opened_at'));

        $openEvent = SovereignLedger::where('event_type', 'ORDER_SAFE_OPEN_INTENT')->where('entity_id', $order->id)->firstOrFail();
        $this->assertSame('order.safe.open', data_get($openEvent->payload, 'intent_type'));
        $this->assertSame(1, data_get($openEvent->payload, 'codes_count'));
    }

    public function test_cabinet_safe_open_requires_authenticated_owner(): void
    {
        $product = $this->seedStorefrontCheckoutProduct();
        $user = $this->checkoutUser();
        $order = $this->createCapturedSafeOrder($product, $user);
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
                ->never()
                ->andReturn($driver);
        });

        $response = $this->postJson(route('meanly.storefront.checkout'), [
            'product_id' => $fixture['product']->id,
            'quantity' => 1,
            'email' => 'buyer@example.test',
            'name' => 'Buyer',
        ]);

        $response->assertOk()
            ->assertJsonPath('safe_status', 'payment_pending')
            ->assertJsonPath('fulfillment_mode', 'instant')
            ->assertJsonPath('vouchers', []);

        $order = Order::where('sales_channel', 'meanly_storefront')->firstOrFail();
        $item = $order->items()->firstOrFail();
        $inventory = ProductInventory::where('voucher', 'LOCAL-ENTITLEMENT-DO-NOT-REVEAL')->firstOrFail();

        $this->assertNull($item->original_code);
        $this->assertNull(data_get($item->client_info, 'provider_redemption.status'));
        $this->assertSame('available', $inventory->status);
        $this->assertNull(data_get($item->client_info, 'local_entitlement.status'));
        $this->assertSame(0, $driver->createCalls);

        $openUrl = \Illuminate\Support\Facades\URL::signedRoute('meanly.storefront.orders.safe.open', [
            'order' => $order->uuid,
        ]);

        $this->postJson($openUrl)
            ->assertStatus(202)
            ->assertJsonPath('status', 'payment_pending');
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
                ->never()
                ->andReturn($driver);
        });

        $response = $this->postJson(route('meanly.storefront.checkout'), [
            'product_id' => $fixture['product']->id,
            'quantity' => 1,
            'email' => 'buyer@example.test',
            'name' => 'Buyer',
        ]);

        $response->assertOk()
            ->assertJsonPath('safe_status', 'payment_pending')
            ->assertJsonPath('vouchers', []);

        $order = Order::where('sales_channel', 'meanly_storefront')->firstOrFail();
        $item = $order->items()->firstOrFail();
        $inventory = ProductInventory::where('voucher', 'LOCAL-ENTITLEMENT-FAILED-SECRET')->firstOrFail();

        $this->assertNull($item->original_code);
        $this->assertNull(data_get($item->client_info, 'provider_redemption.status'));
        $this->assertSame('available', $inventory->status);
        $this->assertNull(data_get($item->client_info, 'local_entitlement.status'));

        $openUrl = \Illuminate\Support\Facades\URL::signedRoute('meanly.storefront.orders.safe.open', [
            'order' => $order->uuid,
        ]);

        $this->postJson($openUrl)
            ->assertStatus(202)
            ->assertJsonPath('status', 'payment_pending')
            ->assertJsonPath('codes', null);

        $this->assertSame(0, Ticket::where('order_id', $order->id)->count());
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
            ->assertJsonPath('safe_status', 'payment_pending')
            ->assertJsonPath('fulfillment_mode', 'instant');

        $order = Order::where('sales_channel', 'meanly_storefront')->firstOrFail();
        $item = $order->items()->firstOrFail();

        $this->assertSame('payment_pending', $item->purchase_status);
        $this->assertSame('instant', data_get($order->info, 'fulfillment_mode'));
        $this->assertFalse(ProductInventory::where('voucher', 'MEAN-EMAIL-TEST01-ZZ')->firstOrFail()->is_used);
    }

    public function test_wallet_checkout_options_retired_before_preorder_validation(): void
    {
        $product = $this->seedStorefrontCheckoutProduct();
        $user = $this->checkoutUser();

        $this->actingAs($user)->postJson(route('meanly.storefront.checkout.wallet.options'), [
            'product_id' => $product->id,
            'quantity' => 1,
            'fulfillment_mode' => 'preorder',
            'preorder_acknowledged' => true,
        ])->assertStatus(410);
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
            ->assertSee(__('catalog.index.soon_for_sale'), false)
            ->assertSee('Поставщик поддерживает предзаказ, но моментальная выдача сейчас недоступна.', false)
            ->assertSee('data-submit-checkout disabled', false);
    }

    public function test_wallet_checkout_options_retired_before_provider_availability_or_debit(): void
    {
        $fixture = $this->seedProviderBackedStorefrontProduct(preOrder: false);
        $this->mockPublicProviderAvailability(false);
        $user = $this->checkoutUser();

        $this->mock(ProviderHub::class, function ($mock) {
            $mock->shouldNotReceive('forProvider');
        });

        $this->actingAs($user)->postJson(route('meanly.storefront.checkout.wallet.options'), [
            'product_id' => $fixture['product']->id,
            'quantity' => 1,
        ])->assertStatus(410);

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
            ->assertJsonPath('safe_status', 'payment_pending')
            ->assertJsonPath('fulfillment_mode', 'instant')
            ->assertJsonPath('vouchers', []);

        $this->assertSame(0, $driver->createCalls);

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
        $order = $this->createCapturedSafeOrder($product, $user);
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
