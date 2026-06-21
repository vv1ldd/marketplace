<?php

namespace Tests\Feature;

use App\Models\IdentityGovernanceProjectionCache;
use App\Models\IdentityGovernanceStreamEvent;
use App\Models\User;
use App\Services\Identity\Governance\IdentityGovernanceStreamReadModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Identity recovery contract — first instance of a platform-wide guarantee class.
 *
 * Platform obligation (one sentence):
 *   Identity is not a user record or a proof; it is the platform's commitment to
 *   restore the same canonical entity after loss of derived state. Recovery contracts
 *   are executable proof that the commitment holds.
 *
 * Canonical entity (sl1e) is a continuity anchor, not user data.
 *
 * Maturity ladder:
 *   Layer            Question                          Evidence              Guarantee
 *   Claimed          What does the user assert?        transport claim       Accept
 *   Proven           What can we prove?                cryptographic proof   Verify
 *   Durable          What can we restore?              replayable history    Recover
 *   Validated Durable  How do we know Recover works?   recovery contract     Prove Recover
 *
 * Model evolution: stored → verified → preserved → continuously proven.
 *
 * Three platform artifacts:
 *   1. Identity Maturity Model (Claimed → Proven → Durable → Validated Durable) — language
 *   2. Platform Obligation — verified identities recover after derived-state loss — promise
 *   3. Identity Recovery Contract (this test) — create/wipe/replay/same anchor — proof
 *
 * Layer → implementation: Claimed transport | Proven proof validation | Durable history
 *   | Validated Durable recovery contract.
 *
 * Project status: Architecture closed | Implementation Phase B open.
 *
 * Phase A established identity correctness. Phase B establishes identity continuity.
 * Phase C may reduce implementation complexity, but does not alter the identity contract.
 *
 * PR success criterion (contract level, not mechanism):
 *   Wildflow reaches Durable — verified by this gate flipping negative → positive witness.
 *   Not: producer connected, stream events emitted, replay code exists (implementation evidence).
 *   Yes: create → destroy derived state → replay → same canonical sl1e, no re-authentication.
 *
 * Phase B path: SimpleL1ConnectController → producer → stream → replay → Durable ✓
 * Target: Claimed ✓ Proven ✓ Durable ✓ Validated Durable ✓ (first full authorize-path ladder).
 *
 * Reference: IdentityGovernanceFirstRealProducerTest.
 */
final class WildflowIdentityDurabilityGateTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function wildflow_register_verify_is_proof_anchored_and_filters_transport_username(): void
    {
        config(['identity_governance.stream_enabled' => true]);

        $entityAddress = 'sl1e_'.str_repeat('a', 39);
        $transportUsername = 'safe_9e95da0a';

        $user = $this->completeWildflowRegisterVerify($entityAddress, $transportUsername);

        $this->assertSame($entityAddress, strtolower((string) $user->entity_l1_address));
        $this->assertSame('identity_wildflow', $user->identity_provider);
        $this->assertNotSame($transportUsername, $user->username);
        $this->assertSame('sl1e_'.substr($entityAddress, -6), $user->username);
        $this->assertAuthenticatedAs($user);
    }

    #[Test]
    public function wildflow_identity_durability_gate_documents_missing_governance_stream(): void
    {
        config(['identity_governance.stream_enabled' => true]);

        $entityAddress = 'sl1e_'.str_repeat('b', 39);
        $transportUsername = 'safe_abcd1234';

        $this->completeWildflowRegisterVerify($entityAddress, $transportUsername);

        $streamEventCount = IdentityGovernanceStreamEvent::query()
            ->where('stream_id', $entityAddress)
            ->count();

        // Phase B open: wildflow verify does not yet persist identity into governance stream.
        $this->assertSame(
            0,
            $streamEventCount,
            'Phase B open: wildflow verify must write governance stream before durability is proven.',
        );

        IdentityGovernanceProjectionCache::query()->delete();
        User::query()->delete();

        $this->assertNull(User::findByEntityL1Address($entityAddress));
        $this->assertFalse(app(IdentityGovernanceStreamReadModel::class)->streamExists($entityAddress));
    }

    private function completeWildflowRegisterVerify(string $entityAddress, string $transportUsername): User
    {
        config([
            'simple_l1.identity_provider_url' => 'https://meanly.test',
            'simple_l1.runtime_url' => 'https://meanly.test',
            'simple_l1.proof_introspection_path' => '/api/sl1e/proofs/introspect',
        ]);

        Http::fake([
            'https://meanly.test/api/sl1e/proofs/introspect' => Http::response([
                'success' => true,
                'active' => true,
                'proof_token' => 'proof-token-durability-gate',
                'proof' => [
                    'type' => 'sl1e.register.proof.v1',
                    'clientId' => config('simple_l1.client_id'),
                    'redirectUri' => route('meanly.simple_l1.callback').'?popup=1',
                    'state' => 'durability-state',
                    'nonce' => 'durability-nonce',
                    'mode' => 'register',
                    'entityAddress' => $entityAddress,
                    'keyAddress' => 'sl1_'.str_repeat('c', 40),
                    'username' => $transportUsername,
                    'expiresAt' => now()->addMinutes(5)->toIso8601String(),
                ],
                'identity' => [
                    'username' => $transportUsername,
                ],
            ]),
        ]);

        $this->withSession([
            'simple_l1_connect.state' => 'durability-state',
            'simple_l1_connect.nonce' => 'durability-nonce',
            'simple_l1_connect.client_id' => config('simple_l1.client_id'),
            'simple_l1_connect.redirect_uri' => route('meanly.simple_l1.callback').'?popup=1',
            'simple_l1_connect.mode' => 'login',
            'simple_l1_connect.flow' => 'connect',
            'simple_l1_connect.return_to' => '/vault',
            'simple_l1_connect.popup' => true,
        ])->get('/simple-l1/callback?popup=1&mode=register&state=durability-state&proof_token=proof-token-durability-gate')
            ->assertOk()
            ->assertSessionHas('simple_l1_identity.entity_l1_address', $entityAddress)
            ->assertSessionHas('simple_l1_identity.mode', 'register');

        $user = User::findByEntityL1Address($entityAddress);
        $this->assertInstanceOf(User::class, $user);

        return $user;
    }
}
