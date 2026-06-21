<?php

namespace Tests\Feature;

use App\Models\CanonicalProductIdentity;
use App\Models\SearchDemandRecommendation;
use App\Models\User;
use App\Services\CanonicalProductSearchProfileBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\LaravelPasskeys\Models\Passkey;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DecisionConsoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['session.domain' => null]);
        Role::firstOrCreate(['name' => User::ROLE_SOVEREIGN_VALIDATOR, 'guard_name' => 'web']);
    }

    public function test_ops_admin_can_view_decision_console_recommendations(): void
    {
        $admin = $this->opsAdmin();
        $recommendation = $this->recommendation([
            'type' => 'ADD_PRODUCT',
            'query' => 'spotify turkey',
            'normalized_query' => 'spotify turkey',
        ]);

        $this->actingAs($admin)
            ->get('https://meanly.test/ops?tab=decision-console')
            ->assertRedirect('/ops?tab=decision-console');
    }

    public function test_decision_console_deep_link_redirects_into_ops_center(): void
    {
        $admin = $this->opsAdmin('d');

        $this->actingAs($admin)
            ->get('https://meanly.test/ops/decision-console')
            ->assertRedirect('https://meanly.test/ops?tab=decision-console');
    }

    public function test_approve_and_reject_change_only_governance_state(): void
    {
        $admin = $this->opsAdmin('b');
        $profile = $this->searchProfile();
        $profileUpdatedAt = $profile->updated_at;

        $approve = $this->recommendation([
            'type' => 'ADD_ALIAS',
            'query' => 'плейстейшен',
            'normalized_query' => 'плейстейшен',
        ]);
        $reject = $this->recommendation([
            'type' => 'IMPROVE_SUPPLY',
            'query' => 'spotify turkey',
            'normalized_query' => 'spotify turkey',
        ]);

        $this->actingAs($admin)
            ->post("https://meanly.test/ops/decision-console/recommendations/{$approve->id}/approve")
            ->assertRedirect();

        $this->actingAs($admin)
            ->post("https://meanly.test/ops/decision-console/recommendations/{$reject->id}/reject")
            ->assertRedirect();

        $this->assertSame(SearchDemandRecommendation::STATUS_APPROVED, $approve->refresh()->status);
        $this->assertNotNull($approve->decided_at);
        $this->assertSame(SearchDemandRecommendation::STATUS_REJECTED, $reject->refresh()->status);
        $this->assertNotNull($reject->decided_at);

        $profile->refresh();
        $this->assertTrue($profileUpdatedAt->equalTo($profile->updated_at));
    }

    public function test_applied_recommendations_cannot_be_changed_from_console(): void
    {
        $admin = $this->opsAdmin('c');
        $recommendation = $this->recommendation([
            'status' => SearchDemandRecommendation::STATUS_APPLIED,
            'applied_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post("https://meanly.test/ops/decision-console/recommendations/{$recommendation->id}/reject")
            ->assertForbidden();

        $this->assertSame(SearchDemandRecommendation::STATUS_APPLIED, $recommendation->refresh()->status);
    }

    public function test_non_ops_user_cannot_access_decision_console(): void
    {
        $user = User::factory()->create();
        Passkey::factory()->create([
            'authenticatable_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->get('https://meanly.test/ops/decision-console')
            ->assertForbidden();
    }

    public function test_merchant_node_cannot_approve_add_product_even_if_posting_to_endpoint(): void
    {
        $partner = $this->opsUserWithRole(User::ROLE_MERCHANT_NODE, 'e');
        $recommendation = $this->recommendation([
            'type' => 'ADD_PRODUCT',
        ]);

        $this->actingAs($partner)
            ->post("https://meanly.test/ops/decision-console/recommendations/{$recommendation->id}/approve")
            ->assertForbidden();

        $this->assertSame(SearchDemandRecommendation::STATUS_PROPOSED, $recommendation->refresh()->status);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function recommendation(array $overrides = []): SearchDemandRecommendation
    {
        $payload = array_merge([
            'recommendation_hash' => hash('sha256', json_encode($overrides + ['seed' => uniqid('', true)])),
            'type' => 'ADD_PRODUCT',
            'query' => 'xbox usa',
            'normalized_query' => 'xbox usa',
            'insight_type' => 'COVERAGE_GAP',
            'expected_entity' => ['brands' => ['xbox'], 'regions' => ['us']],
            'impact_score' => 88.5,
            'confidence' => 71.0,
            'evidence' => ['demand_weight' => 120, 'signal_count' => 3],
            'status' => SearchDemandRecommendation::STATUS_PROPOSED,
        ], $overrides);

        return SearchDemandRecommendation::create($payload);
    }

    private function searchProfile()
    {
        $identity = CanonicalProductIdentity::create([
            'fingerprint' => hash('sha256', 'decision-console-xbox-us-25'),
            'identity_slug' => 'decision-console-xbox-us-25',
            'canonical_category' => 'console_payment_cards',
            'brand' => 'Xbox',
            'product_family' => 'Xbox Gift Card',
            'face_value' => 25,
            'face_value_currency' => 'USD',
            'region' => 'US',
            'platform' => 'global',
            'confidence' => 'high',
            'signals' => [],
            'provider_candidates_count' => 1,
            'seller_offers_count' => 0,
            'last_seen_at' => now(),
        ]);

        app(CanonicalProductSearchProfileBuilder::class)->rebuild($identity);

        return $identity->searchProfile()->firstOrFail();
    }

    private function opsAdmin(string $identityHex = 'a'): User
    {
        return $this->opsUserWithRole(User::ROLE_SOVEREIGN_VALIDATOR, $identityHex);
    }

    private function opsUserWithRole(string $role, string $identityHex): User
    {
        $admin = User::factory()->create([
            'meta' => ['entity_l1_address' => 'sl1e_'.str_repeat($identityHex, 39)],
        ]);
        $admin->assignRole($role);
        Passkey::factory()->create([
            'authenticatable_id' => $admin->id,
        ]);

        return $admin;
    }
}
