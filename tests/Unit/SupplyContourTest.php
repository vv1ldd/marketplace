<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\SupplyContour;
use Tests\TestCase;

class SupplyContourTest extends TestCase
{
    public function test_colocated_one_with_force_direct_supply_uses_kernel_http_catalog_but_is_not_remote_consumer(): void
    {
        config([
            'services.wildflow.kernel_url' => 'http://digital-goods-source:8080/api/v1',
            'services.wildflow.kernel_mode' => 'http',
            'services.wildflow.force_direct_supply' => true,
        ]);

        $this->assertTrue(SupplyContour::usesKernelHttpCatalog());
        $this->assertFalse(SupplyContour::isRemoteKernelConsumer());
        $this->assertTrue(SupplyContour::isDirectSupplyAuthority());
        $this->assertSame('direct_supply_authority', SupplyContour::kernelMode());
    }

    public function test_ru_remote_consumer_uses_kernel_http_and_is_remote(): void
    {
        config([
            'services.wildflow.kernel_url' => 'https://api.meanly.one/api/v1',
            'services.wildflow.kernel_mode' => 'http',
            'services.wildflow.force_direct_supply' => false,
        ]);

        $this->assertTrue(SupplyContour::usesKernelHttpCatalog());
        $this->assertTrue(SupplyContour::isRemoteKernelConsumer());
        $this->assertFalse(SupplyContour::isDirectSupplyAuthority());
        $this->assertSame('remote_kernel_consumer', SupplyContour::kernelMode());
    }
}
