<?php

namespace Tests\Unit;

use App\Services\CanonicalStorefrontHomepageService;
use Tests\TestCase;

class GroupProductSlugTest extends TestCase
{
    public function test_group_product_slug_uses_brand_and_kind(): void
    {
        /** @var CanonicalStorefrontHomepageService $homepage */
        $homepage = app(CanonicalStorefrontHomepageService::class);

        $this->assertSame('amazon-gift-cards', $homepage->groupProductSlug('Amazon', 'gift-cards'));
    }

    public function test_parse_group_product_slug_recognizes_kind_suffix(): void
    {
        /** @var CanonicalStorefrontHomepageService $homepage */
        $homepage = app(CanonicalStorefrontHomepageService::class);

        $this->assertSame(
            ['brandSlug' => 'amazon', 'kindSlug' => 'gift-cards'],
            $homepage->parseGroupProductSlug('amazon-gift-cards'),
        );
        $this->assertSame(
            ['brandSlug' => 'hello-fresh', 'kindSlug' => 'subscriptions'],
            $homepage->parseGroupProductSlug('hello-fresh-subscriptions'),
        );
    }
}
