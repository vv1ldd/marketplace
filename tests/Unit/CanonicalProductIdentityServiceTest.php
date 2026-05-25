<?php

namespace Tests\Unit;

use App\Models\Brand;
use App\Models\MappingCountry;
use App\Models\Product;
use App\Models\ProviderProduct;
use App\Services\CanonicalProductIdentityService;
use Tests\TestCase;

class CanonicalProductIdentityServiceTest extends TestCase
{
    public function test_russian_marketing_title_matches_provider_identity(): void
    {
        $brand = new Brand(['name' => 'Playstation']);
        $region = new MappingCountry([
            'code' => 'US',
            'name_en' => 'United States',
        ]);

        $providerProduct = new ProviderProduct([
            'name' => 'PlayStation US',
            'canonical_category' => 'console_payment_cards',
            'retail_price' => 100,
            'currency' => 'USD',
        ]);
        $providerProduct->setRelation('brand', $brand);
        $providerProduct->setRelation('region', $region);

        $sellerProduct = new Product([
            'sku' => 'PLAYSTATION-100-USD-US-GC-C473591',
            'name' => '✅ Подарочная карта Playstation 100 USD (США) ✨ Мгновенная доставка',
            'canonical_category' => 'console_payment_cards',
            'purchase_price' => 100,
            'purchase_currency' => 'USD',
        ]);
        $sellerProduct->setRelation('brand', $brand);

        $service = app(CanonicalProductIdentityService::class);
        $providerIdentity = $service->forProviderProduct($providerProduct);
        $sellerIdentity = $service->forProduct($sellerProduct);

        $this->assertSame('playstation', $sellerIdentity['product_family']);
        $this->assertSame($providerIdentity['fingerprint'], $sellerIdentity['fingerprint']);
        $this->assertSame($providerIdentity['identity_slug'], $sellerIdentity['identity_slug']);
    }
}
