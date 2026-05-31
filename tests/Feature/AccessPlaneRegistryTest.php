<?php

namespace Tests\Feature;

use App\Models\LegalEntity;
use App\Models\User;
use App\Services\AccessPlaneRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AccessPlaneRegistryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['session.domain' => null]);
        foreach (['super_admin', 'auditor', 'b2b_partner'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_guest_only_has_public_storefront_available(): void
    {
        $planes = app(AccessPlaneRegistry::class)->forUser(null)->keyBy('key');

        $this->assertTrue($planes['storefront']->available);
        $this->assertFalse($planes['vault']->available);
        $this->assertSame('Authentication required', $planes['vault']->reason);
    }

    public function test_partner_plane_requires_role_and_active_legal_entity(): void
    {
        $partner = $this->userWithIdentity('a');
        $partner->assignRole('b2b_partner');

        $planes = app(AccessPlaneRegistry::class)->forUser($partner)->keyBy('key');
        $this->assertFalse($planes['partner']->available);
        $this->assertSame('Active legal entity required', $planes['partner']->reason);

        LegalEntity::create([
            'user_id' => $partner->id,
            'name' => 'Partner Entity',
            'short_name' => 'Partner',
            'inn' => '770000000301',
            'email' => 'partner@example.test',
            'status' => 'active',
            'is_active' => true,
        ]);

        $planes = app(AccessPlaneRegistry::class)->forUser($partner->refresh())->keyBy('key');
        $this->assertTrue($planes['partner']->available);
    }

    public function test_ops_and_decision_planes_require_super_admin_sovereign_identity(): void
    {
        $admin = $this->userWithIdentity('b');
        $admin->assignRole('super_admin');

        $planes = app(AccessPlaneRegistry::class)->forUser($admin)->keyBy('key');

        $this->assertTrue($planes['ops']->available);
        $this->assertTrue($planes['decision_console']->available);
        $this->assertTrue($planes['tribunal']->available);
    }

    public function test_auditor_gets_tribunal_but_not_decision_console(): void
    {
        $auditor = $this->userWithIdentity('c');
        $auditor->assignRole('auditor');

        $planes = app(AccessPlaneRegistry::class)->forUser($auditor)->keyBy('key');

        $this->assertTrue($planes['tribunal']->available);
        $this->assertFalse($planes['decision_console']->available);
        $this->assertSame('Required role: super_admin', $planes['decision_console']->reason);
    }

    private function userWithIdentity(string $identityHex): User
    {
        return User::factory()->create([
            'meta' => ['entity_l1_address' => 'sl1e_'.str_repeat($identityHex, 39)],
        ]);
    }
}
