<?php

namespace Tests\Unit;

use App\Services\Settlement\IdentityPaymentRoutingService;
use App\Services\Settlement\PaymentDecisionContextService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentDecisionContextServiceTest extends TestCase
{
    #[Test]
    public function ruleset_hash_is_deterministic_for_the_same_policy_version(): void
    {
        config(['capability_policies.default' => 'v1']);

        $service = app(PaymentDecisionContextService::class);

        $first = $service->rulesetHash('v1');
        $second = $service->rulesetHash('v1');

        $this->assertSame($first, $second);
        $this->assertStringStartsWith('sha256:', $first);
        $this->assertSame(71, strlen($first));
    }

    #[Test]
    public function ruleset_hash_changes_when_capability_policy_version_changes(): void
    {
        $service = app(PaymentDecisionContextService::class);

        $v1 = $service->rulesetHash('v1');
        $v2 = $service->rulesetHash('v2');

        $this->assertNotSame($v1, $v2);
    }

    #[Test]
    public function build_includes_policy_keys_and_evaluated_at(): void
    {
        config(['capability_policies.default' => 'v1']);

        $service = app(PaymentDecisionContextService::class);
        $evaluatedAt = '2026-06-22T12:00:00.000000Z';

        $context = $service->build('v1', $evaluatedAt);

        $this->assertSame(
            [
                'instrument-capability-policy:v1',
                IdentityPaymentRoutingService::POLICY_VERSION,
            ],
            $context['policy_keys'],
        );
        $this->assertSame($evaluatedAt, $context['evaluated_at']);
        $this->assertSame($service->rulesetHash('v1'), $context['ruleset_hash']);
    }
}
