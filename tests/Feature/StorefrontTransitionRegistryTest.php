<?php

namespace Tests\Feature;

use App\Services\StorefrontTransitionRegistry;
use Tests\TestCase;

class StorefrontTransitionRegistryTest extends TestCase
{
    public function test_ctg_v0_declares_canonical_storefront_transition_ids(): void
    {
        $registry = app(StorefrontTransitionRegistry::class);

        $this->assertSame('ctg-storefront-v0', StorefrontTransitionRegistry::VERSION);
        $expected = [
            StorefrontTransitionRegistry::CHECKOUT_ALLOWED,
            StorefrontTransitionRegistry::CHECKOUT_BLOCKED,
            StorefrontTransitionRegistry::PAYMENT_PENDING,
            StorefrontTransitionRegistry::OPEN_SAFE,
            StorefrontTransitionRegistry::WAIT_FOR_BACKEND_STATE,
            StorefrontTransitionRegistry::FORBIDDEN,
            StorefrontTransitionRegistry::IGNORED_CLIENT_OVERRIDE,
        ];
        $actual = $registry->ids();
        sort($expected);
        sort($actual);

        $this->assertSame($expected, $actual);

        foreach ($registry->transitions() as $id => $transition) {
            $this->assertSame($id, $transition['emits']);
            $this->assertNotEmpty($transition['source']);
            $this->assertNotEmpty($transition['inputs']);
        }
    }
}
