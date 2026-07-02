<?php

namespace Tests\Unit\Routing;

use App\Domain\Routing\ProviderStickySelector;
use App\Domain\Routing\RoutingPolicy;
use Tests\TestCase;

class RoutingPolicyTest extends TestCase
{
    public function test_sticky_slot_is_deterministic_for_same_inputs(): void
    {
        $policy = new RoutingPolicy('weighted', ['margin' => 0.4], 'v1');

        $first = $policy->calculateStickySlot('ent_fp_abc', 'buy:steam-wallet:20:USD:TR');
        $second = $policy->calculateStickySlot('ent_fp_abc', 'buy:steam-wallet:20:USD:TR');

        $this->assertSame($first, $second);
        $this->assertGreaterThanOrEqual(0, $first);
        $this->assertLessThanOrEqual(99, $first);
    }

    public function test_sticky_slot_changes_when_version_changes(): void
    {
        $policyV1 = new RoutingPolicy('weighted', ['margin' => 0.4], 'v1');
        $policyV2 = new RoutingPolicy('weighted', ['margin' => 0.4], 'v2');

        $slotV1 = $policyV1->calculateStickySlot('ent_fp_abc', 'buy:steam-wallet:20:USD:TR');
        $slotV2 = $policyV2->calculateStickySlot('ent_fp_abc', 'buy:steam-wallet:20:USD:TR');

        $this->assertNotSame($slotV1, $slotV2);
    }

    public function test_weighted_split_distributes_traffic_close_to_configured_ratio(): void
    {
        $policy = new RoutingPolicy(
            type: 'weighted',
            weights: ['margin' => 0.4, 'success_rate' => 0.3, 'latency' => 0.15, 'stock' => 0.15],
            version: 'v1',
            providerSplit: [
                ['provider_id' => 42, 'traffic_weight' => 70],
                ['provider_id' => 77, 'traffic_weight' => 30],
            ],
        );

        $selector = new ProviderStickySelector();
        $providerScores = collect([42 => 0.9, 77 => 0.8]);
        $counts = [42 => 0, 77 => 0];

        for ($index = 0; $index < 1000; $index++) {
            $providerId = $selector->selectProviderId(
                $policy,
                'entitlement-'.$index,
                'buy:steam-wallet:20:USD:TR',
                $providerScores,
            );

            $counts[$providerId]++;
        }

        $ratioPrimary = $counts[42] / 1000;
        $this->assertGreaterThanOrEqual(0.65, $ratioPrimary);
        $this->assertLessThanOrEqual(0.75, $ratioPrimary);
    }

    public function test_single_provider_always_receives_selected_slot(): void
    {
        $policy = RoutingPolicy::fromConfig();
        $selector = new ProviderStickySelector();

        $providerId = $selector->selectProviderId(
            $policy,
            'entitlement-single',
            'buy:steam-wallet:20:USD:TR',
            collect([42 => 0.55]),
        );

        $this->assertSame(42, $providerId);
    }
}
