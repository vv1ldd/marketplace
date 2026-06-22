<?php

namespace Tests\Unit;

use App\Models\IdentityBinding;
use App\Services\Settlement\PaymentFeePolicyRegistry;
use App\Services\Settlement\PaymentFeeQuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentFeePolicyTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function ruleset_hash_is_deterministic_for_the_same_version(): void
    {
        $registry = app(PaymentFeePolicyRegistry::class);

        $this->assertSame($registry->rulesetHash('v1'), $registry->rulesetHash('v1'));
        $this->assertNotSame($registry->rulesetHash('v1'), $registry->rulesetHash('v2'));
    }

    #[Test]
    public function managed_evm_usdc_fee_quote_uses_active_policy_bps(): void
    {
        config(['payment_fees.default' => 'v1']);

        $binding = new IdentityBinding([
            'binding_source' => IdentityBinding::SOURCE_MANAGED,
            'verification_state' => IdentityBinding::STATE_VERIFIED,
            'metadata' => ['protocol' => 'evm'],
        ]);

        $decision = app(PaymentFeeQuoteService::class)->quote(
            'sl1e_'.str_repeat('a', 39),
            '@selim_dev',
            'USDC',
            '10',
            'polygon',
            $binding,
        );

        $this->assertTrue($decision['applicable']);
        $this->assertSame('payment-fees:v1', $decision['policy_key']);
        $this->assertSame(50, $decision['fee_bps']);
        $this->assertSame('0.05', $decision['fee_amount']);
        $this->assertSame('selim_dev', $decision['payer']);
        $this->assertStringStartsWith('sha256:', $decision['ruleset_hash']);
    }

    #[Test]
    public function fee_amount_changes_with_policy_version(): void
    {
        $binding = new IdentityBinding([
            'binding_source' => IdentityBinding::SOURCE_MANAGED,
            'verification_state' => IdentityBinding::STATE_VERIFIED,
            'metadata' => ['protocol' => 'evm'],
        ]);

        $service = app(PaymentFeeQuoteService::class);
        $identity = 'sl1e_'.str_repeat('b', 39);

        $v1 = $service->quote($identity, '@alice', 'USDC', '10', 'polygon', $binding, 'v1');
        $v2 = $service->quote($identity, '@alice', 'USDC', '10', 'polygon', $binding, 'v2');

        $this->assertSame('0.05', $v1['fee_amount']);
        $this->assertSame('0.1', $v2['fee_amount']);
    }
}
