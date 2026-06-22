<?php

namespace Tests\Unit;

use App\Models\IdentityPaymentDispute;
use App\Services\Settlement\PaymentDisputeRegistry;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentDisputeRegistryTest extends TestCase
{
    #[Test]
    public function lifecycle_transitions_are_strictly_ordered(): void
    {
        $registry = app(PaymentDisputeRegistry::class);

        $this->assertContains(
            IdentityPaymentDispute::STATUS_EVIDENCE_REQUESTED,
            $registry->allowedTransitions(IdentityPaymentDispute::STATUS_OPENED),
        );
        $this->assertContains(
            IdentityPaymentDispute::STATUS_REVIEWED,
            $registry->allowedTransitions(IdentityPaymentDispute::STATUS_OPENED),
        );
        $this->assertSame([], $registry->allowedTransitions(IdentityPaymentDispute::STATUS_RESOLVED));
    }
}
