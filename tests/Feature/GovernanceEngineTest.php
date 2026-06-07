<?php

namespace Tests\Feature;

use App\Models\SearchDemandRecommendation;
use App\Models\User;
use App\Services\GovernanceEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GovernanceEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([User::ROLE_SOVEREIGN_VALIDATOR, User::ROLE_LEDGER_AUDITOR, User::ROLE_MERCHANT_NODE] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_sovereign_validator_can_approve_semantic_recommendations(): void
    {
        $admin = $this->userWithRole(User::ROLE_SOVEREIGN_VALIDATOR, 'a');
        $recommendation = $this->recommendation('ADD_ALIAS');

        $decision = app(GovernanceEngine::class)->canTransition($admin, $recommendation, SearchDemandRecommendation::STATUS_APPROVED);

        $this->assertTrue($decision->allowed);
    }

    public function test_ledger_auditor_cannot_approve_decision_console_recommendations(): void
    {
        $auditor = $this->userWithRole(User::ROLE_LEDGER_AUDITOR, 'b');
        $recommendation = $this->recommendation('ADD_PRODUCT');

        $decision = app(GovernanceEngine::class)->canTransition($auditor, $recommendation, SearchDemandRecommendation::STATUS_APPROVED);

        $this->assertFalse($decision->allowed);
        $this->assertSame('ROLE_REQUIRED: '.User::ROLE_SOVEREIGN_VALIDATOR, $decision->reason);
    }

    public function test_partner_can_approve_supply_actions_but_not_add_product(): void
    {
        $partner = $this->userWithRole(User::ROLE_MERCHANT_NODE, 'c');

        $supplyDecision = app(GovernanceEngine::class)->canTransition($partner, $this->recommendation('IMPROVE_SUPPLY'), SearchDemandRecommendation::STATUS_APPROVED);
        $productDecision = app(GovernanceEngine::class)->canTransition($partner, $this->recommendation('ADD_PRODUCT'), SearchDemandRecommendation::STATUS_APPROVED);

        $this->assertTrue($supplyDecision->allowed);
        $this->assertFalse($productDecision->allowed);
        $this->assertSame('ROLE_REQUIRED: '.User::ROLE_SOVEREIGN_VALIDATOR, $productDecision->reason);
    }

    public function test_applied_recommendation_cannot_transition_back(): void
    {
        $admin = $this->userWithRole(User::ROLE_SOVEREIGN_VALIDATOR, 'd');
        $recommendation = $this->recommendation('ADD_ALIAS', SearchDemandRecommendation::STATUS_APPLIED);

        $decision = app(GovernanceEngine::class)->canTransition($admin, $recommendation, SearchDemandRecommendation::STATUS_REJECTED);

        $this->assertFalse($decision->allowed);
        $this->assertSame('INVALID_STATE_TRANSITION', $decision->reason);
    }

    public function test_dual_control_policy_denies_until_implemented(): void
    {
        $admin = $this->userWithRole(User::ROLE_SOVEREIGN_VALIDATOR, 'e');
        $recommendation = $this->recommendation('APPLY_REBUILD');

        $decision = app(GovernanceEngine::class)->canTransition($admin, $recommendation, SearchDemandRecommendation::STATUS_APPROVED);

        $this->assertFalse($decision->allowed);
        $this->assertSame('DUAL_CONTROL_PENDING', $decision->reason);
    }

    private function userWithRole(string $role, string $identityHex): User
    {
        $user = User::factory()->create([
            'meta' => ['entity_l1_address' => 'sl1e_'.str_repeat($identityHex, 39)],
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function recommendation(string $type, string $status = SearchDemandRecommendation::STATUS_PROPOSED): SearchDemandRecommendation
    {
        return SearchDemandRecommendation::create([
            'recommendation_hash' => hash('sha256', $type.$status.uniqid('', true)),
            'type' => $type,
            'query' => 'xbox usa',
            'normalized_query' => 'xbox usa',
            'insight_type' => 'COVERAGE_GAP',
            'expected_entity' => [],
            'impact_score' => 10,
            'confidence' => 10,
            'evidence' => [],
            'status' => $status,
        ]);
    }
}
