<?php

namespace Tests\Unit;

use App\Support\StorefrontRegionalSl1e;
use Tests\TestCase;

class StorefrontRegionalSl1eTest extends TestCase
{
    public function test_ru_storefront_uses_meanly_ru_client_identity(): void
    {
        $regional = StorefrontRegionalSl1e::forHost('meanly.ru');

        $this->assertSame('ru', $regional->marketKey);
        $this->assertSame('meanly.ru', $regional->storefrontHost);
        $this->assertSame('meanly.ru', $regional->clientId);
        $this->assertSame('Meanly', $regional->clientName);
    }

    public function test_global_storefront_uses_meanly_one_client_identity(): void
    {
        $regional = StorefrontRegionalSl1e::forHost('meanly.one');

        $this->assertSame('global', $regional->marketKey);
        $this->assertSame('meanly.one', $regional->storefrontHost);
        $this->assertSame('meanly.one', $regional->clientId);
        $this->assertSame('Meanly One', $regional->clientName);
    }

    public function test_cross_region_hosts_do_not_match(): void
    {
        $ru = StorefrontRegionalSl1e::forHost('meanly.ru');

        $this->assertTrue($ru->matchesHost('www.meanly.ru'));
        $this->assertFalse($ru->matchesHost('meanly.one'));
    }
}
