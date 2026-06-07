<?php

namespace Tests\Feature;

use App\Models\LegalEntity;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerWorkspaceProjectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_partner_workspace_summary_exposes_authority_owned_projection(): void
    {
        $user = User::factory()->create();
        $entity = LegalEntity::create([
            'user_id' => $user->id,
            'name' => 'Meanly Seller Entity',
            'short_name' => 'Meanly Seller',
            'inn' => '770000000111',
            'status' => 'active',
            'is_active' => true,
            'available_balance' => 1000,
            'reserved_balance' => 50,
            'balance' => 1050,
        ]);

        $shop = new Shop([
            'name' => 'Seller RU Shop',
            'domain' => 'meanly.ru',
            'shop_region' => 'RU',
            'is_active' => true,
        ]);
        $shop->legal_entity_id = $entity->id;
        $shop->save();

        $this->withoutMiddleware()
            ->actingAs($user)
            ->getJson(route('partner.workspace.summary'))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('contract.name', 'PartnerWorkspaceProjection')
            ->assertJsonPath('contract.read_model_only', true)
            ->assertJsonPath('legal_entity.inn', '770000000111')
            ->assertJsonPath('capabilities.orders', true)
            ->assertJsonPath('authority_invariant.nextjs.may_render', true)
            ->assertJsonPath('authority_invariant.nextjs_must_not.evaluate_authority', true)
            ->assertJsonPath('authority_invariant.laravel_owns.0', 'auth')
            ->assertJsonStructure([
                'navigation' => [
                    '*' => ['key', 'label', 'href', 'enabled'],
                ],
                'sales_channels' => [
                    '*' => ['type', 'label', 'configured', 'ready', 'issues', 'shops', 'next_action'],
                ],
                'orders_summary',
                'catalog_summary',
                'finance_summary',
                'alerts',
                'module_endpoints',
                'actions',
                'legacy_url',
            ]);
    }

    public function test_partner_workspace_projection_reports_missing_inn_as_channel_alert(): void
    {
        $user = User::factory()->create();
        $entity = LegalEntity::create([
            'user_id' => $user->id,
            'name' => 'Seller Entity Without INN',
            'inn' => '',
            'status' => 'active',
            'is_active' => true,
        ]);

        $shop = new Shop([
            'name' => 'Seller Shop',
            'domain' => 'meanly.ru',
            'shop_region' => 'RU',
            'is_active' => true,
        ]);
        $shop->legal_entity_id = $entity->id;
        $shop->save();

        $response = $this->withoutMiddleware()
            ->actingAs($user)
            ->getJson(route('partner.workspace.summary'))
            ->assertOk();

        $this->assertContains(
            'legal_entity_inn_missing',
            collect($response->json('alerts'))->pluck('type')->all()
        );

        $yandex = collect($response->json('sales_channels'))->firstWhere('type', 'yandex_market');

        $this->assertSame(false, $yandex['ready']);
        $this->assertSame('legal_entity_required', $yandex['shops'][0]['issues'][0]['code']);
    }

    public function test_partner_legacy_route_remains_available_for_dashboard_fallback(): void
    {
        $user = User::factory()->create();
        LegalEntity::create([
            'user_id' => $user->id,
            'name' => 'Legacy Seller Entity',
            'inn' => '770000000222',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->withoutMiddleware()
            ->actingAs($user)
            ->get(route('partner.dashboard.legacy'))
            ->assertOk();
    }
}
