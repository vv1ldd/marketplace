<?php

namespace Tests\Unit;

use App\Models\Brand;
use App\Models\ProviderBrandMapping;
use App\Services\CanonicalProductIdentityService;
use App\Services\MappingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MappingServiceBrandResolutionTest extends TestCase
{
    use RefreshDatabase;
    public function test_resolve_brand_prefers_playstation_over_gift_card_category_mapping(): void
    {
        $providerId = 1;
        $tjMaxx = Brand::firstOrCreate(['name' => 'TJ Maxx']);
        $playStation = Brand::firstOrCreate(['name' => 'PlayStation']);

        ProviderBrandMapping::updateOrCreate(
            ['provider_id' => $providerId, 'external_name' => 'Подарочные карты'],
            ['brand_id' => $tjMaxx->id],
        );

        $brandId = MappingService::resolveBrand(
            $providerId,
            'Подарочные карты',
            'WFC-PSN-100',
            'PlayStation Canada',
        );

        $this->assertSame($playStation->id, $brandId);
    }

    public function test_identity_brand_uses_master_lexicon_when_relation_is_tj_maxx(): void
    {
        $service = app(CanonicalProductIdentityService::class);
        $identity = $service->forProviderProduct(new \App\Models\ProviderProduct([
            'name' => 'PlayStation Canada',
            'canonical_category' => 'console_payment_cards',
            'retail_price' => 100,
            'currency' => 'USD',
        ])->setRelation('brand', new Brand(['name' => 'TJ Maxx'])));

        $this->assertSame('PlayStation', $identity['brand']);
    }
}
