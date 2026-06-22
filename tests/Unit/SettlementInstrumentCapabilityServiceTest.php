<?php

namespace Tests\Unit;

use App\Models\IdentityBinding;
use App\Services\Settlement\SettlementInstrumentCapabilityService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SettlementInstrumentCapabilityServiceTest extends TestCase
{
    #[Test]
    public function managed_evm_binding_exposes_payment_routing_for_usdc(): void
    {
        config([
            'identity_payments.enabled' => true,
            'identity_payments.execute_enabled' => true,
        ]);

        $binding = new IdentityBinding([
            'binding_key' => 'polygon',
            'binding_source' => IdentityBinding::SOURCE_MANAGED,
            'verification_state' => IdentityBinding::STATE_VERIFIED,
            'metadata' => [
                'protocol' => 'evm',
                'network_label' => 'Polygon',
            ],
        ]);

        $matrix = app(SettlementInstrumentCapabilityService::class)->matrixForBinding($binding);

        $this->assertTrue($matrix['receive']['enabled']);
        $this->assertTrue($matrix['send']['enabled']);
        $this->assertTrue($matrix['payment_routing']['enabled']);
        $this->assertSame(['USDC'], $matrix['payment_routing']['assets']);
    }

    #[Test]
    public function external_bitcoin_binding_is_receive_only(): void
    {
        config([
            'identity_payments.enabled' => true,
            'identity_payments.execute_enabled' => true,
        ]);

        $binding = new IdentityBinding([
            'binding_key' => 'bitcoin',
            'binding_source' => IdentityBinding::SOURCE_EXTERNAL,
            'verification_state' => IdentityBinding::STATE_VERIFIED,
            'metadata' => [
                'protocol' => 'utxo',
                'network_label' => 'Bitcoin',
            ],
        ]);

        $matrix = app(SettlementInstrumentCapabilityService::class)->matrixForBinding($binding);

        $this->assertTrue($matrix['receive']['enabled']);
        $this->assertSame('BTC', $matrix['receive']['asset']);
        $this->assertFalse($matrix['send']['enabled']);
        $this->assertFalse($matrix['payment_routing']['enabled']);
    }

    #[Test]
    public function payment_routing_is_subset_of_receive_capability(): void
    {
        config([
            'identity_payments.enabled' => true,
            'identity_payments.execute_enabled' => true,
        ]);

        $binding = new IdentityBinding([
            'binding_key' => 'polygon',
            'binding_source' => IdentityBinding::SOURCE_MANAGED,
            'verification_state' => IdentityBinding::STATE_PENDING,
            'metadata' => [
                'protocol' => 'evm',
                'network_label' => 'Polygon',
            ],
        ]);

        $service = app(SettlementInstrumentCapabilityService::class);
        $matrix = $service->matrixForBinding($binding);

        $this->assertFalse($matrix['receive']['enabled']);
        $this->assertFalse($matrix['payment_routing']['enabled']);
        $this->assertNull($service->formatPaymentRoutingCapability($binding));
    }

    #[Test]
    public function solana_external_payment_routing_follows_capability_policy_version(): void
    {
        config([
            'identity_payments.enabled' => true,
            'identity_payments.execute_enabled' => true,
        ]);

        $binding = new IdentityBinding([
            'binding_key' => 'solana',
            'binding_source' => IdentityBinding::SOURCE_EXTERNAL,
            'verification_state' => IdentityBinding::STATE_VERIFIED,
            'metadata' => [
                'protocol' => 'solana',
                'network_label' => 'Solana',
            ],
        ]);

        $service = app(SettlementInstrumentCapabilityService::class);

        $v1 = $service->matrixForBinding($binding, 'v1');
        $v2 = $service->matrixForBinding($binding, 'v2');

        $this->assertFalse($v1['payment_routing']['enabled']);
        $this->assertTrue($v2['payment_routing']['enabled']);
        $this->assertSame('instrument-capability-policy:v2', $v2['payment_routing']['evaluated_by']);
    }
}
