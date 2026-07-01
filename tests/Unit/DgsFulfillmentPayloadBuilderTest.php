<?php

namespace Tests\Unit;

use App\Services\Dgs\DgsFulfillmentPayloadBuilder;
use Tests\TestCase;

class DgsFulfillmentPayloadBuilderTest extends TestCase
{
    public function test_builds_series5_issue_contract_from_driver_meta(): void
    {
        $builder = new DgsFulfillmentPayloadBuilder();

        $payload = $builder->build(
            serviceSku: '4402',
            reference: '48872be1-b981-4770-84d3-887cbe449100',
            price: 5.0,
            quantity: 1,
            meta: [
                'user_l1_address' => 'SL1:ID:BUYER-ABC',
                'order_uuid' => 'order-uuid-001',
                'sku_bidx' => 'WF-SBX-4402',
                'ezpin_sku' => 4402,
                'email' => 'buyer@example.test',
            ]
        );

        $this->assertSame('order-uuid-001', $payload['order_id']);
        $this->assertSame('48872be1-b981-4770-84d3-887cbe449100', $payload['idempotency_key']);
        $this->assertSame('license_key', $payload['strategy']);
        $this->assertSame('sl1:id:buyer-abc', $payload['buyer_address']);
        $this->assertSame('prod_wf-sbx-4402', $payload['product_id']);
        $this->assertSame(4402, $payload['metadata']['ezpin_sku']);
    }

    public function test_requires_buyer_address_for_node_fulfillment(): void
    {
        $this->expectException(\RuntimeException::class);

        (new DgsFulfillmentPayloadBuilder())->build('4402', 'ref-1', 1.0, 1, []);
    }
}
