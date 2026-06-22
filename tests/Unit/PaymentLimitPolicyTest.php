<?php

namespace Tests\Unit;

use App\Models\IdentityBinding;
use App\Services\Settlement\PaymentLimitPolicyEvaluator;
use App\Services\Settlement\PaymentLimitPolicyRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentLimitPolicyTest extends TestCase
{
    use RefreshDatabase;
    #[Test]
    public function ruleset_hash_is_deterministic_for_the_same_version(): void
    {
        $registry = app(PaymentLimitPolicyRegistry::class);

        $this->assertSame($registry->rulesetHash('v1'), $registry->rulesetHash('v1'));
        $this->assertNotSame($registry->rulesetHash('v1'), $registry->rulesetHash('v2'));
    }

    #[Test]
    public function managed_evm_usdc_payment_is_evaluated_against_active_policy(): void
    {
        config(['payment_limits.default' => 'v1']);

        $binding = new IdentityBinding([
            'binding_source' => IdentityBinding::SOURCE_MANAGED,
            'verification_state' => IdentityBinding::STATE_VERIFIED,
            'metadata' => ['protocol' => 'evm'],
        ]);

        $decision = app(PaymentLimitPolicyEvaluator::class)->evaluate(
            'sl1e_'.str_repeat('a', 39),
            'USDC',
            '10',
            'polygon',
            $binding,
        );

        $this->assertTrue($decision['approved']);
        $this->assertSame('payment-limits:v1', $decision['policy_key']);
        $this->assertSame('10000', $decision['per_transaction_limit']);
        $this->assertSame('50000', $decision['daily_limit']);
        $this->assertStringStartsWith('sha256:', $decision['ruleset_hash']);
    }

    #[Test]
    public function per_transaction_limit_rejection_is_explicit(): void
    {
        config(['payment_limits.default' => 'v2']);

        $binding = new IdentityBinding([
            'binding_source' => IdentityBinding::SOURCE_MANAGED,
            'verification_state' => IdentityBinding::STATE_VERIFIED,
            'metadata' => ['protocol' => 'evm'],
        ]);

        $decision = app(PaymentLimitPolicyEvaluator::class)->evaluate(
            'sl1e_'.str_repeat('b', 39),
            'USDC',
            '5000',
            'polygon',
            $binding,
            'v2',
        );

        $this->assertFalse($decision['approved']);
        $this->assertSame('per_transaction_limit_exceeded', $decision['reason']);
    }
}
