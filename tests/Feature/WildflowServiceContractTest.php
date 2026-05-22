<?php

namespace Tests\Feature;

use App\Models\Provider;
use App\Services\WildflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WildflowServiceContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_order_sends_required_identity_headers_signature_and_preserves_zero_like_values(): void
    {
        config(['services.wildflow.verify_tls' => false]);

        $provider = Provider::updateOrCreate(
            ['type' => 'wildflow'],
            [
                'name' => 'Wildflow Contract',
                'is_active' => true,
                'credentials' => [
                    'base_url' => 'https://api.wildflow.test/api/v1/',
                    'api_key' => 'wf-token',
                    'client_id' => 'wf-client',
                    'financial_secret' => 'wf-secret',
                ],
            ]
        );

        Http::fake([
            'https://api.wildflow.test/api/v1/providers/ezpin/order' => Http::response([
                'order' => ['referenceCode' => 'SL1-CONTRACT-ORDER'],
            ], 200),
        ]);

        $service = new WildflowService(providerModel: $provider);
        $service->createOrder(
            service_sku: 'WF-ZERO-SKU',
            order_item_id: 'SL1-CONTRACT-ORDER',
            price: 0.0,
            quantity: 1,
            pre_order: false,
            destination: '',
            provider: 'ezpin',
            terminalId: '0',
            sellerId: 'seller-1',
            sellerName: 'Seller One',
        );

        Http::assertSent(function (Request $request) {
            $timestamp = $this->firstHeader($request, 'X-Financial-Timestamp');
            $signature = $this->firstHeader($request, 'X-Financial-Signature');
            $payload = $request->data();

            return str_ends_with($request->url(), '/api/v1/providers/ezpin/order')
                && $this->firstHeader($request, 'X-Auth-Token') === 'wf-token'
                && $this->firstHeader($request, 'X-Client-Id') === 'wf-client'
                && is_numeric($timestamp)
                && hash_equals(hash_hmac('sha256', $timestamp.'.'.$request->body(), 'wf-secret'), $signature)
                && array_key_exists('price', $payload)
                && (float) $payload['price'] === 0.0
                && array_key_exists('pre_order', $payload)
                && $payload['pre_order'] === false
                && array_key_exists('destination', $payload)
                && $payload['destination'] === ''
                && $payload['terminal_id'] === '0';
        });
    }

    private function firstHeader(Request $request, string $name): ?string
    {
        $value = $request->header($name);

        return is_array($value) ? ($value[0] ?? null) : $value;
    }
}
