<?php

namespace Tests\Feature;

use App\Models\LegalEntity;
use App\Models\Warehouse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OpsModerationQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['session.domain' => null]);

        Role::firstOrCreate(['name' => User::ROLE_SOVEREIGN_VALIDATOR, 'guard_name' => 'web']);
    }

    public function test_ops_partners_data_exposes_status_without_legacy_access_links(): void
    {
        $admin = $this->opsAdmin('admin@admin.com', 'a');

        $entity = LegalEntity::create([
            'name' => 'Active Entity',
            'short_name' => 'Active Entity',
            'inn' => '770000000001',
            'email' => 'active-entity@example.test',
            'status' => 'active',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->getJson('http://meanly.test/ops/dashboard/partners/data');

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $entity->id);
        $response->assertJsonPath('data.0.name', 'Active Entity');
        $response->assertJsonPath('data.0.status_label', 'Активна');
        $this->assertNull($response->json('data.0.approve_url'));
    }

    public function test_ops_partners_data_filters_and_approves_moderation_queue(): void
    {
        $admin = $this->opsAdmin('moderator@admin.com', 'b');

        $pending = LegalEntity::create([
            'name' => 'Pending Moderation Entity',
            'short_name' => 'Pending Entity',
            'inn' => '770000000101',
            'email' => 'pending-entity@example.test',
            'status' => 'pending_moderation',
            'is_active' => false,
        ]);

        LegalEntity::create([
            'name' => 'Already Active Entity',
            'short_name' => 'Active Entity',
            'inn' => '770000000102',
            'email' => 'active-entity@example.test',
            'status' => 'active',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->getJson('http://meanly.test/ops/dashboard/partners/data?status=pending_moderation');

        $response->assertOk();
        $response->assertJsonPath('total', 1);
        $response->assertJsonPath('data.0.id', $pending->id);
        $response->assertJsonPath('data.0.status_label', 'На модерации');
        $this->assertIsString($response->json('data.0.approve_url'));

        $approve = $this->actingAs($admin)->postJson($response->json('data.0.approve_url'));

        $approve->assertOk();
        $approve->assertJsonPath('status', 'active');
        $approve->assertJsonPath('status_label', 'Активна');

        $pending->refresh();
        $this->assertTrue($pending->is_active);
        $this->assertSame('active', $pending->status);
        $this->assertSame('approved', $pending->agreement_metadata['moderation_decision']);

        $distributionShop = $pending->shops()->where('is_distribution_center', true)->first();
        $this->assertNotNull($distributionShop);
        $this->assertSame('Центр дистрибуции', $distributionShop->name);

        $this->assertDatabaseHas('warehouses', [
            'shop_id' => $distributionShop->id,
            'name' => 'Мастер-склад',
            'type' => 'master',
            'is_main' => true,
            'channel' => null,
        ]);
    }

    public function test_distribution_center_uses_one_master_warehouse_per_seller(): void
    {
        $entity = LegalEntity::create([
            'name' => 'Distribution Entity',
            'short_name' => 'Distribution',
            'inn' => '770000000201',
            'email' => 'distribution@example.test',
            'status' => 'active',
            'is_active' => true,
        ]);

        $service = app(\App\Services\SellerDistributionCenterService::class);
        $first = $service->ensureForLegalEntity($entity);
        $second = $service->ensureForLegalEntity($entity->refresh());

        $this->assertSame($first['shop']->id, $second['shop']->id);
        $this->assertSame($first['warehouse']->id, $second['warehouse']->id);
        $this->assertSame(1, $entity->shops()->where('is_distribution_center', true)->count());
        $this->assertSame(
            1,
            Warehouse::query()
                ->master()
                ->whereHas('shop', fn ($query) => $query->where('legal_entity_id', $entity->id))
                ->count()
        );
    }

    private function opsAdmin(string $email, string $identityHex): User
    {
        $admin = User::factory()->create([
            'email' => $email,
            'meta' => ['entity_l1_address' => 'sl1e_'.str_repeat($identityHex, 39)],
        ]);
        $admin->assignRole(User::ROLE_SOVEREIGN_VALIDATOR);
        \Spatie\LaravelPasskeys\Models\Passkey::factory()->create([
            'authenticatable_id' => $admin->id,
        ]);

        return $admin;
    }
}
