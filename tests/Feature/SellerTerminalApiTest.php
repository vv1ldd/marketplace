<?php

namespace Tests\Feature;

use App\Models\LegalEntity;
use App\Models\Order\Order;
use App\Models\Order\OrderItems;
use App\Models\Provider;
use App\Models\ProviderProduct;
use App\Models\SellerTerminal;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class SellerTerminalApiTest extends TestCase
{
    use RefreshDatabase;

    private LegalEntity $legalEntity;
    private SellerTerminal $terminal;
    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.wildflow.kernel_url' => 'https://wildflow.test/api/v1']);

        Http::fake([
            '*/partners/*' => Http::response(['data' => ['balance' => 42.5]], 200),
        ]);

        Provider::updateOrCreate(
            ['type' => 'wildflow'],
            [
                'name' => 'Wildflow',
                'is_active' => true,
                'credentials' => [
                    'api_key' => 'testing-token',
                ],
            ]
        );

        $this->legalEntity = LegalEntity::create([
            'name' => 'Seller Terminal LLC',
            'short_name' => 'Terminal LLC',
            'inn' => '1234567890',
            'kpp' => '123456789',
            'ogrn' => '1234567890123',
            'legal_address' => 'Terminal Street 1',
            'postal_address' => 'Terminal Street 1',
            'bank_name' => 'Terminal Bank',
            'bank_bic' => '123456789',
            'bank_account' => '12345678901234567890',
            'bank_correspondent_account' => '12345678901234567890',
            'director_name' => 'Terminal Director',
            'available_balance' => 1234.56,
            'reserved_balance' => 0,
            'currency' => 'RUB',
            'allow_all_brands' => true,
            'is_active' => true,
        ]);

        $this->shop = new Shop([
            'name' => 'Terminal Shop',
            'domain' => 'terminal-shop.test',
            'voucher_prefix' => 'SL',
            'is_active' => true,
        ]);
        $this->shop->legal_entity_id = $this->legalEntity->id;
        $this->shop->save();

        $this->terminal = SellerTerminal::create([
            'legal_entity_id' => $this->legalEntity->id,
            'terminal_id' => 'SL-TEST-TERMINAL',
            'terminal_pin' => '123456',
            'is_active' => true,
        ]);
    }

    public function test_seller_terminal_requires_credentials(): void
    {
        $this->getJson('/api/seller/balance')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'TERMINAL_CREDENTIALS_MISSING');
    }

    public function test_seller_terminal_balance_uses_authenticated_legal_entity(): void
    {
        $this->withTerminalHeaders()
            ->getJson('/api/seller/balance')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('local_balance.amount', 1234.56)
            ->assertJsonPath('local_balance.currency', 'RUB')
            ->assertJsonPath('kernel_balance.amount', 42.5);
    }

    public function test_seller_terminal_catalog_returns_allowed_products(): void
    {
        $provider = Provider::where('type', 'wildflow')->firstOrFail();

        ProviderProduct::create([
            'provider_id' => $provider->id,
            'sku' => 'WF-TEST-SKU',
            'market_sku' => 'WF-TEST-SKU',
            'name' => 'Terminal Test Product',
            'category' => 'Gift Card',
            'purchase_price' => 100,
            'retail_price' => 150,
            'currency' => 'RUB',
            'is_active' => true,
            'data' => [],
        ]);

        $this->withTerminalHeaders()
            ->getJson('/api/seller/catalog')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('products.0.sku', 'WF-TEST-SKU')
            ->assertJsonPath('products.0.provider.type', 'wildflow');
    }

    public function test_seller_terminal_create_order_success(): void
    {
        $provider = Provider::where('type', 'wildflow')->firstOrFail();

        $product = ProviderProduct::create([
            'provider_id' => $provider->id,
            'sku' => 'WF-TEST-SKU',
            'market_sku' => 'WF-TEST-SKU',
            'name' => 'Terminal Test Product',
            'category' => 'Gift Card',
            'purchase_price' => 100,
            'retail_price' => 150,
            'currency' => 'RUB',
            'is_active' => true,
            'data' => [],
        ]);

        // Fake the Kernel HTTP requests for the checkout and JIT flow
        Http::fake([
            '*/providers/*/check-availability/*' => Http::response([
                'availability' => ['availability' => true]
            ], 200),
            '*/partners/grant-credit' => Http::response([
                'success' => true,
                'reservation_id' => 123
            ], 200),
            '*/providers/*/order' => Http::response([
                'order' => ['referenceCode' => 'WF-EXT-ORDER-ID']
            ], 200),
            '*/providers/*/orders/*/normalized-cards' => Http::response([
                'cards' => [
                    ['pinCode' => 'SECRET_ACT_CODE_999']
                ]
            ], 200),
        ]);

        $response = $this->withTerminalHeaders()
            ->postJson('/api/seller/order', [
                'sku' => 'WF-TEST-SKU',
                'quantity' => 1,
                'destination' => 'customer@test.com'
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('codes.0', 'SECRET_ACT_CODE_999');

        // Check local balance is correctly decremented by retail_price (150 RUB)
        $this->assertEquals(1234.56 - 150.00, $this->legalEntity->fresh()->available_balance);
        $this->assertEquals(0, $this->legalEntity->fresh()->reserved_balance);
    }

    public function test_seller_terminal_create_order_is_idempotent_by_client_reference(): void
    {
        $provider = Provider::where('type', 'wildflow')->firstOrFail();

        ProviderProduct::create([
            'provider_id' => $provider->id,
            'sku' => 'WF-IDEMPOTENT-SKU',
            'market_sku' => 'WF-IDEMPOTENT-SKU',
            'name' => 'Terminal Idempotent Product',
            'category' => 'Gift Card',
            'purchase_price' => 100,
            'retail_price' => 150,
            'currency' => 'RUB',
            'is_active' => true,
            'data' => [],
        ]);

        Http::fake([
            '*/providers/*/check-availability/*' => Http::response([
                'availability' => ['availability' => true]
            ], 200),
            '*/partners/grant-credit' => Http::response([
                'success' => true,
                'reservation_id' => 123
            ], 200),
            '*/providers/*/order' => Http::response([
                'order' => ['referenceCode' => 'WF-IDEMPOTENT-EXT']
            ], 200),
            '*/providers/*/orders/*/normalized-cards' => Http::response([
                'cards' => [
                    ['pinCode' => 'IDEMPOTENT_CODE_777']
                ]
            ], 200),
        ]);

        $payload = [
            'sku' => 'WF-IDEMPOTENT-SKU',
            'quantity' => 1,
            'destination' => 'customer@test.com',
            'client_reference' => 'seller-terminal-order-777',
        ];

        $first = $this->withTerminalHeaders()
            ->postJson('/api/seller/order', $payload)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 'completed');

        $balanceAfterFirst = $this->legalEntity->fresh()->available_balance;

        $this->withTerminalHeaders()
            ->postJson('/api/seller/order', $payload)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('idempotent', true)
            ->assertJsonPath('transaction_ref', $first->json('transaction_ref'))
            ->assertJsonPath('codes.0', 'IDEMPOTENT_CODE_777');

        $this->assertSame((float)$balanceAfterFirst, (float)$this->legalEntity->fresh()->available_balance);
        $this->assertSame(1, Order::where('shop_id', $this->shop->id)->count());
        $this->assertSame(1, OrderItems::count());
    }

    public function test_daily_budget_counts_existing_seller_orders(): void
    {
        $this->terminal->update(['daily_limit' => 100]);

        Order::create([
            'order_id' => 'SL-ORD-' . strtoupper(Str::random(8)),
            'uuid' => Str::uuid()->toString(),
            'status' => 'PROCESSING',
            'shop_id' => $this->shop->id,
            'total_amount' => 90,
            'currency' => 'RUB',
        ]);

        $this->assertTrue($this->terminal->fresh()->hasRemainingDailyBudget(10));
        $this->assertFalse($this->terminal->fresh()->hasRemainingDailyBudget(11));
    }

    private function withTerminalHeaders(): self
    {
        return $this->withHeaders([
            'X-Terminal-Id' => 'SL-TEST-TERMINAL',
            'X-Terminal-Pin' => '123456',
        ]);
    }
}
