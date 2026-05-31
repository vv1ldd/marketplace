<?php

namespace Tests\Feature;

use App\Models\SovereignLedger;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\LaravelPasskeys\Models\Passkey;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OpsPanelParityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['session.domain' => null]);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'auditor', 'guard_name' => 'web']);
    }

    public function test_ops_dashboard_contains_ledger_tribunal_tab(): void
    {
        $admin = $this->opsAdmin();

        $this->actingAs($admin)
            ->get('https://meanly.test/ops?tab=tribunal')
            ->assertOk()
            ->assertSee('Ledger Tribunal')
            ->assertSee('Ledger Integrity Validator')
            ->assertSee('Audit Oracle');
    }

    public function test_tribunal_chain_validation_is_available_under_ops(): void
    {
        $admin = $this->opsAdmin('b');

        SovereignLedger::create([
            'event_type' => 'test.event',
            'amount_base' => 100,
            'currency' => 'RUB',
            'payload' => ['test' => true],
            'previous_fingerprint' => null,
            'fingerprint' => hash('sha256', 'genesis'),
        ]);

        $this->actingAs($admin)
            ->postJson('https://meanly.test/ops/dashboard/tribunal/validate-chain')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('total_count', 1)
            ->assertJsonPath('valid_count', 1);
    }

    public function test_auditor_can_access_tribunal_validation_without_super_admin(): void
    {
        $auditor = $this->opsUserWithRole('auditor', 'c');

        $this->actingAs($auditor)
            ->postJson('https://meanly.test/ops/dashboard/tribunal/validate-chain')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    private function opsAdmin(string $identityHex = 'a'): User
    {
        return $this->opsUserWithRole('super_admin', $identityHex);
    }

    private function opsUserWithRole(string $role, string $identityHex): User
    {
        $user = User::factory()->create([
            'meta' => ['entity_l1_address' => 'sl1e_'.str_repeat($identityHex, 39)],
        ]);
        $user->assignRole($role);
        Passkey::factory()->create([
            'authenticatable_id' => $user->id,
        ]);

        return $user;
    }
}
