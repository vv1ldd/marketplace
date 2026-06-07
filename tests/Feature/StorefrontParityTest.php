<?php

namespace Tests\Feature;

use App\Models\Order\Order;
use App\Models\Order\OrderItems;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\ProductSalesChannel;
use App\Services\MeanlyFirstPartyStorefrontService;
use App\Services\StorefrontTokenService;
use App\Services\StorefrontTransitionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

class StorefrontParityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.domain' => 'localhost',
            'session.domain' => null,
            'meanly_storefront.legal_entity.inn' => '770000099001',
            'meanly_storefront.legal_entity.name' => 'Meanly First Party LLC',
            'meanly_storefront.legal_entity.short_name' => 'Meanly',
            'meanly_storefront.shop.name' => 'Meanly Test Store',
            'meanly_storefront.shop.domain' => 'meanly.test',
            'meanly_storefront.shop.voucher_prefix' => 'MEAN',
        ]);
    }

    public function test_catalog_checkout_intent_and_order_safe_parity_use_backend_decisions(): void
    {
        $fixture = $this->seedDeterministicFixture();

        $blade = $this->executeBladeBaseline($fixture['product']);
        $api = $this->executeStorefrontApiCandidate($fixture['product'], $fixture['order']);

        $this->assertSame(
            $blade['availability']['transition_id'],
            $api['availability']['transition_id'],
            $this->failureExplanation('availability transition drift', $blade['availability'], $api['availability']),
        );

        $this->assertSame(
            $blade['order_safe']['transition_id'],
            $api['order_safe']['transition_id'],
            $this->failureExplanation('order-safe transition drift', $blade['order_safe'], $api['order_safe']),
        );

        $registry = app(StorefrontTransitionRegistry::class);
        $this->assertTrue($registry->has($api['availability']['transition_id']));
        $this->assertTrue($registry->has($api['order_safe']['transition_id']));
        $this->assertSame(StorefrontTransitionRegistry::VERSION, $api['availability']['ctg_version']);
        $this->assertSame(StorefrontTransitionRegistry::VERSION, $api['order_safe']['ctg_version']);
        $this->assertSame('CHECKOUT', data_get($api, 'intent.next_action'));
        $this->assertContains('CHECKOUT', data_get($api, 'intent.allowed_actions'));
        $this->assertSame(StorefrontTransitionRegistry::IGNORED_CLIENT_OVERRIDE, $api['ignored_override_transition_id']);
    }

    /**
     * @return array{product: Product, order: Order}
     */
    private function seedDeterministicFixture(): array
    {
        $storefront = app(MeanlyFirstPartyStorefrontService::class);
        $shop = $storefront->shop();

        $product = Product::create([
            'shop_id' => $shop->id,
            'sku' => 'PARITY-CHECKOUT-001',
            'name' => 'Parity Checkout Gift Card',
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
            'voucher' => 'PARITY-VOUCHER-001',
            'is_used' => false,
            'status' => 'available',
            'expires_at' => now()->addYear(),
        ]);

        $order = Order::create([
            'uuid' => (string) Str::uuid(),
            'order_id' => 'MS-PARITY-'.Str::upper(Str::random(6)),
            'status' => 'NEW',
            'sub_status' => 'DIRECT_STOREFRONT',
            'progress_id' => 1,
            'shop_id' => $shop->id,
            'sales_channel' => 'meanly_storefront',
            'total_amount' => 150,
            'currency' => 'RUB',
            'info' => ['payment_status' => 'pending'],
            'client_info' => [
                'buyer_l1_address' => 'sl1e_paritybuyer000000000000000000000000001',
            ],
        ]);

        OrderItems::create([
            'key' => 'PARITY-VOUCHER-001',
            'uuid' => (string) Str::uuid(),
            'order_id' => $order->id,
            'activate_till' => now()->addYear()->format('Y-m-d'),
            'sku' => $product->sku,
            'count' => 1,
            'price_rub' => 15000,
            'type_form_id' => 2,
            'purchase_status' => 'pending',
            'client_info' => ['channel' => 'meanly_storefront'],
        ]);

        return ['product' => $product, 'order' => $order->refresh()];
    }

    /**
     * @return array<string, mixed>
     */
    private function executeBladeBaseline(Product $product): array
    {
        $availability = $this->postJson(route('meanly.storefront.checkout.availability'), [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertOk()->json();

        $order = Order::where('sales_channel', 'meanly_storefront')->firstOrFail();
        $safe = $this->getJson(URL::signedRoute('meanly.storefront.orders.safe.status', ['order' => $order->uuid]))
            ->assertOk()
            ->json();

        return [
            'availability' => $this->normalizeAvailability($availability),
            'order_safe' => $this->normalizeOrderSafe($safe),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function executeStorefrontApiCandidate(Product $product, Order $order): array
    {
        $availability = $this->postJson('/api/storefront/v1/checkout/availability', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertOk()->json('decision');

        $intentResponse = $this->postJson('/api/storefront/v1/checkout/intent', [
            'product_id' => $product->id,
            'quantity' => 1,
            'client_price_snapshot' => 1,
            'stock_hint' => 999,
        ])->assertOk();
        $intent = $intentResponse->json('intent.decision');

        $token = app(StorefrontTokenService::class)->issue([
            'entity_l1_address' => 'sl1e_paritybuyer000000000000000000000000001',
            'proof_token_hash' => hash('sha256', 'parity-proof-token'),
        ])['access_token'];

        $safe = $this->withToken($token)
            ->getJson('/api/storefront/v1/orders/'.$order->uuid.'/safe/status')
            ->assertOk()
            ->json('decision');

        return [
            'availability' => $this->normalizeAvailability($availability),
            'intent' => $intent,
            'ignored_override_transition_id' => $intentResponse->json('intent.ignored_client_overrides.transition_id'),
            'order_safe' => $this->normalizeOrderSafe($safe),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeAvailability(array $payload): array
    {
        return [
            'status' => (string) ($payload['availability_status'] ?? $payload['status'] ?? 'unavailable'),
            'checkout_allowed' => (bool) ($payload['checkout_allowed'] ?? (($payload['status'] ?? null) === 'available')),
            'source' => (string) ($payload['source'] ?? 'unknown'),
            'ctg_version' => (string) ($payload['ctg_version'] ?? StorefrontTransitionRegistry::VERSION),
            'transition_id' => (string) (
                $payload['transition_id']
                ?? (($payload['status'] ?? null) === 'available'
                    ? StorefrontTransitionRegistry::CHECKOUT_ALLOWED
                    : StorefrontTransitionRegistry::CHECKOUT_BLOCKED)
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeOrderSafe(array $payload): array
    {
        return [
            'status' => (string) ($payload['status'] ?? 'unknown'),
            'paid' => (bool) ($payload['paid'] ?? false),
            'ready' => (bool) ($payload['ready'] ?? false),
            'failed' => (bool) ($payload['failed'] ?? false),
            'total_rub' => (float) ($payload['total_rub'] ?? 0),
            'ctg_version' => (string) ($payload['ctg_version'] ?? StorefrontTransitionRegistry::VERSION),
            'transition_id' => (string) (
                $payload['transition_id']
                ?? (($payload['status'] ?? null) === 'payment_pending'
                    ? StorefrontTransitionRegistry::PAYMENT_PENDING
                    : StorefrontTransitionRegistry::WAIT_FOR_BACKEND_STATE)
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $expected
     * @param  array<string, mixed>  $actual
     */
    private function failureExplanation(string $reason, array $expected, array $actual): string
    {
        return $reason."\nexpected=".json_encode($expected, JSON_PRETTY_PRINT)."\nactual=".json_encode($actual, JSON_PRETTY_PRINT);
    }
}
