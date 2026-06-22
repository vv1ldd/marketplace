<?php

namespace Tests\Unit;

use App\Models\IdentityBinding;
use App\Services\Settlement\CapabilityPolicyRegistry;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CapabilityPolicyRegistryTest extends TestCase
{
    #[Test]
    public function v1_allows_managed_evm_only(): void
    {
        config(['identity_payments.enabled' => true]);

        $registry = app(CapabilityPolicyRegistry::class);
        $polygon = new IdentityBinding([
            'binding_key' => 'polygon',
            'binding_source' => IdentityBinding::SOURCE_MANAGED,
            'verification_state' => IdentityBinding::STATE_VERIFIED,
            'metadata' => ['protocol' => 'evm'],
        ]);
        $solana = new IdentityBinding([
            'binding_key' => 'solana',
            'binding_source' => IdentityBinding::SOURCE_EXTERNAL,
            'verification_state' => IdentityBinding::STATE_VERIFIED,
            'metadata' => ['protocol' => 'solana'],
        ]);

        $this->assertSame(['USDC'], $registry->paymentRoutingAssetsForBinding($polygon, 'v1'));
        $this->assertSame([], $registry->paymentRoutingAssetsForBinding($solana, 'v1'));
    }

    #[Test]
    public function v2_extends_payment_routing_without_mutating_v1(): void
    {
        config(['identity_payments.enabled' => true]);

        $registry = app(CapabilityPolicyRegistry::class);
        $solana = new IdentityBinding([
            'binding_key' => 'solana',
            'binding_source' => IdentityBinding::SOURCE_EXTERNAL,
            'verification_state' => IdentityBinding::STATE_VERIFIED,
            'metadata' => ['protocol' => 'solana'],
        ]);

        $this->assertSame([], $registry->paymentRoutingAssetsForBinding($solana, 'v1'));
        $this->assertSame(['USDC'], $registry->paymentRoutingAssetsForBinding($solana, 'v2'));
        $this->assertSame('instrument-capability-policy:v2', $registry->versionLabel('v2'));
    }
}
