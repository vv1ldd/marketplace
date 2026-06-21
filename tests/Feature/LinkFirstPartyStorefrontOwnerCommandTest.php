<?php

namespace Tests\Feature;

use App\Models\LegalEntity;
use App\Models\Shop;
use App\Models\User;
use App\Services\MeanlyFirstPartyStorefrontService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LinkFirstPartyStorefrontOwnerCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_links_first_party_storefront_to_sl1e_user(): void
    {
        $user = User::factory()->create([
            'username' => 'selim_dev',
            'username_key' => 'selim_dev',
            'entity_l1_address' => 'sl1e_9e695aba0d5a140d966f554e2381376aa86468b',
            'identity_provider' => 'simple_l1',
        ]);

        $entity = LegalEntity::query()->create([
            'name' => config('meanly_storefront.legal_entity.name'),
            'inn' => config('meanly_storefront.legal_entity.inn'),
            'is_active' => true,
            'status' => 'active',
        ]);

        Shop::query()->create([
            'legal_entity_id' => $entity->id,
            'name' => config('meanly_storefront.shop.name'),
            'domain' => config('meanly_storefront.shop.domain', 'meanly.test'),
            'voucher_prefix' => config('meanly_storefront.shop.voucher_prefix', 'MEAN'),
            'is_active' => true,
        ]);

        $this->artisan('meanly:link-storefront-owner', ['identity' => 'selim_dev'])
            ->assertSuccessful();

        $shop = app(MeanlyFirstPartyStorefrontService::class)->shop();

        $this->assertSame($user->id, $entity->fresh()->user_id);
        $this->assertSame($shop->id, $user->fresh()->shop_id);
        $this->assertTrue($user->managedLegalEntities()->whereKey($entity->id)->exists());
        $this->assertTrue($user->managedShops()->whereKey($shop->id)->exists());
    }
}
