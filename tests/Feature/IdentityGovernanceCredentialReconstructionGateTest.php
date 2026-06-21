<?php

namespace Tests\Feature;

use App\Models\IdentityGovernanceProjectionCache;
use App\Services\Identity\Governance\GovernanceEventTypes;
use App\Services\Identity\Governance\IdentityCredentialReducer;
use App\Services\Identity\Governance\IdentityGovernanceProjectionRebuilder;
use App\Services\Identity\Governance\IdentityGovernanceStreamAppender;
use App\Services\Identity\Governance\IdentityGovernanceStreamWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Gate: can verify-ready WebAuthn material be reconstructed from the stream alone?
 *
 * Target model (Variant A): credential store is a projection of credential.bound
 * payloads — not a parallel source of truth.
 */
class IdentityGovernanceCredentialReconstructionGateTest extends TestCase
{
    use RefreshDatabase;

    private const STREAM = 'sl1e_credential_reconstruction_gate';

    private const FACTOR_A = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';

    private const FACTOR_B = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';

    private const CREDENTIAL_A_ID = 'Y3JlZGVudGlhbC1h';

    private const CREDENTIAL_B_ID = 'Y3JlZGVudGlhbC1i';

    private const PUBLIC_KEY_A = 'cHVibGljLWtleS1h';

    private const PUBLIC_KEY_B = 'cHVibGljLWtleS1i';

    #[Test]
    public function verify_ready_credentials_reconstruct_from_stream_after_all_caches_destroyed(): void
    {
        $writer = app(IdentityGovernanceStreamWriter::class);
        $appender = app(IdentityGovernanceStreamAppender::class);

        $this->seedStreamWithWebAuthnMaterial($writer);

        $liveCredentials = IdentityCredentialReducer::fold($appender->loadEvents(self::STREAM));

        IdentityGovernanceProjectionCache::query()->delete();

        $events = $appender->loadEvents(self::STREAM);
        $reconstructed = IdentityCredentialReducer::fold($events);
        $governance = app(IdentityGovernanceProjectionRebuilder::class)->projectFromEvents($events);

        $this->assertTrue($liveCredentials->equals($reconstructed));
        $this->assertCount(1, $reconstructed->activeCredentials);
        $this->assertSame(self::FACTOR_B, $reconstructed->activeCredentials[0]->factorId);
        $this->assertSame(self::CREDENTIAL_B_ID, $reconstructed->activeCredentials[0]->credentialId);
        $this->assertSame(self::PUBLIC_KEY_B, $reconstructed->activeCredentials[0]->publicKey);
        $this->assertSame(['internal'], $reconstructed->activeCredentials[0]->transports);

        $allowCredentials = $reconstructed->toAllowCredentials();
        $this->assertCount(1, $allowCredentials);
        $this->assertSame('public-key', $allowCredentials[0]['type']);
        $this->assertSame(self::CREDENTIAL_B_ID, $allowCredentials[0]['id']);

        $this->assertNull($reconstructed->findByFactorId(self::FACTOR_A));
        $this->assertNotNull($reconstructed->findByFactorId(self::FACTOR_B));

        $this->assertSame([self::FACTOR_B], array_column($governance->governance->activeFactors, 'id'));
    }

    #[Test]
    public function credential_bound_without_webauthn_payload_is_governance_only_not_verify_ready(): void
    {
        $writer = app(IdentityGovernanceStreamWriter::class);
        $appender = app(IdentityGovernanceStreamAppender::class);

        $writer->append(
            streamId: self::STREAM,
            expectedVersion: 0,
            eventId: 'legacy-genesis',
            eventType: GovernanceEventTypes::IDENTITY_CREATED,
        );

        $writer->append(
            streamId: self::STREAM,
            expectedVersion: 1,
            eventId: 'legacy-bind',
            eventType: GovernanceEventTypes::CREDENTIAL_BOUND,
            payload: [
                'factor_id' => self::FACTOR_A,
                'class' => 'possession',
                'type' => 'passkey',
                'purpose' => 'daily',
            ],
        );

        $events = $appender->loadEvents(self::STREAM);
        $credentials = IdentityCredentialReducer::fold($events);
        $governance = app(IdentityGovernanceProjectionRebuilder::class)->projectFromEvents($events);

        $this->assertSame([], $credentials->activeCredentials);
        $this->assertSame([], $credentials->toAllowCredentials());
        $this->assertSame([self::FACTOR_A], array_column($governance->governance->activeFactors, 'id'));
    }

    private function seedStreamWithWebAuthnMaterial(IdentityGovernanceStreamWriter $writer): void
    {
        $writer->append(
            streamId: self::STREAM,
            expectedVersion: 0,
            eventId: 'cred-gate-genesis',
            eventType: GovernanceEventTypes::IDENTITY_CREATED,
        );

        $writer->append(
            streamId: self::STREAM,
            expectedVersion: 1,
            eventId: 'cred-gate-username',
            eventType: GovernanceEventTypes::IDENTITY_USERNAME_ASSIGNED,
            payload: ['username' => 'alice'],
        );

        $writer->append(
            streamId: self::STREAM,
            expectedVersion: 2,
            eventId: 'cred-gate-bind-a',
            eventType: GovernanceEventTypes::CREDENTIAL_BOUND,
            payload: $this->boundPayload(self::FACTOR_A, self::CREDENTIAL_A_ID, self::PUBLIC_KEY_A),
        );

        $writer->append(
            streamId: self::STREAM,
            expectedVersion: 3,
            eventId: 'cred-gate-bind-b',
            eventType: GovernanceEventTypes::CREDENTIAL_BOUND,
            payload: $this->boundPayload(self::FACTOR_B, self::CREDENTIAL_B_ID, self::PUBLIC_KEY_B),
        );

        $writer->append(
            streamId: self::STREAM,
            expectedVersion: 4,
            eventId: 'cred-gate-revoke-a',
            eventType: GovernanceEventTypes::CREDENTIAL_REVOKED,
            payload: ['factor_id' => self::FACTOR_A],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function boundPayload(string $factorId, string $credentialId, string $publicKey): array
    {
        return [
            'factor_id' => $factorId,
            'class' => 'possession',
            'type' => 'passkey',
            'purpose' => 'daily',
            'webauthn' => [
                'credential_id' => $credentialId,
                'public_key' => $publicKey,
                'sign_count' => 0,
                'aaguid' => '00000000-0000-0000-0000-000000000000',
                'transports' => ['internal'],
            ],
        ];
    }
}
