<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\Provider;
use App\Models\Shop;
use App\Services\MerchantProductPresentationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MerchantProductPresentationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_supply_payload_uses_english_category_and_source_currency(): void
    {
        $provider = Provider::query()->updateOrCreate(
            ['type' => 'wildflow'],
            ['name' => 'EZPin', 'is_active' => true],
        );

        $shop = Shop::query()->create([
            'name' => 'Meanly Store',
            'domain' => 'meanly.one',
            'voucher_prefix' => 'MEAN',
            'is_active' => true,
        ]);

        $product = Product::query()->create([
            'shop_id' => $shop->id,
            'provider_id' => $provider->id,
            'sku' => 'SKU-1',
            'name' => 'PlayStation Store 100 USD',
            'category' => 'Подарочные карты',
            'canonical_category' => 'gift_cards',
            'purchase_price' => 86,
            'purchase_currency' => 'USD',
            'price_rub' => 862554,
            'is_active' => true,
        ]);

        $service = app(MerchantProductPresentationService::class);

        $this->assertSame('Gift cards', $service->categoryLabel($product));
        $this->assertSame([
            'amount' => 86.0,
            'currency' => 'USD',
            'label' => '86.00 USD',
        ], $service->listPrice($product));
    }
}
