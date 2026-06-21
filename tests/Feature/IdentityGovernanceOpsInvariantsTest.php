<?php

namespace Tests\Feature;

use App\Models\IdentityGovernanceStreamEvent;
use App\Services\Identity\Governance\GovernanceEventPayloadNormalizer;
use App\Services\Identity\Governance\GovernanceEventTypes;
use App\Services\Identity\Governance\IdentityGovernanceCredentialCounterStore;
use App\Services\Identity\Governance\IdentityGovernanceProjectionConvergenceChecker;
use App\Services\Identity\Governance\IdentityGovernanceStreamAppender;
use App\Services\Identity\Governance\IdentityGovernanceStreamAssertionVerifier;
use App\Services\Identity\Governance\IdentityGovernanceStreamAuthorizeService;
use App\Services\Identity\Governance\IdentityGovernanceStreamWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IdentityGovernanceOpsInvariantsTest extends TestCase
{
    use RefreshDatabase;

    private const STREAM = 'sl1e_ops_invariants';

    #[Test]
    public function invariant_10_projection_convergence_has_no_violations_on_realistic_stream(): void
    {
        $this->seedStream(app(IdentityGovernanceStreamWriter::class));

        $violations = app(IdentityGovernanceProjectionConvergenceChecker::class)
            ->violationsForStream(self::STREAM);

        $this->assertSame([], $violations);
    }

    #[Test]
    public function invariant_11_authorize_does_not_mutate_identity_stream(): void
    {
        config(['app.domain' => 'localhost']);
        config(['passkeys.relying_party.id' => 'localhost']);
        config(['identity_governance.stream_enabled' => true]);
        config(['identity_governance.stream_authorize_enabled' => true]);

        $writer = app(IdentityGovernanceStreamWriter::class);
        $this->seedStreamWithWebAuthn($writer);

        $this->mock(IdentityGovernanceStreamAssertionVerifier::class, function ($mock): void {
            $mock->shouldReceive('verify')->once()->andReturn(true);
        });

        $authorize = app(IdentityGovernanceStreamAuthorizeService::class);
        $options = $authorize->issueAuthenticationOptions(self::STREAM);

        $eventsBefore = IdentityGovernanceStreamEvent::query()->where('stream_id', self::STREAM)->count();
        $headBefore = app(IdentityGovernanceStreamAppender::class)->headVersion(self::STREAM);

        $authorize->verifyAuthentication(
            $options['flowId'],
            [
                'id' => 'dummy',
                'rawId' => $options['options']['allowCredentials'][0]['id'],
                'type' => 'public-key',
                'response' => [
                    'clientDataJSON' => 'e30',
                    'authenticatorData' => 'e30',
                    'signature' => 'e30',
                ],
            ],
        );

        $this->assertSame($eventsBefore, IdentityGovernanceStreamEvent::query()->where('stream_id', self::STREAM)->count());
        $this->assertSame($headBefore, app(IdentityGovernanceStreamAppender::class)->headVersion(self::STREAM));
    }

    #[Test]
    public function invariant_12_legacy_payload_without_schema_version_normalizes_to_v1(): void
    {
        $legacy = [
            'factor_id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            'class' => 'possession',
            'type' => 'passkey',
        ];

        $normalized = GovernanceEventPayloadNormalizer::normalize(
            GovernanceEventTypes::CREDENTIAL_BOUND,
            $legacy,
        );

        $this->assertSame($legacy, $normalized);
    }

    #[Test]
    public function invariant_12_new_appends_stamp_schema_version(): void
    {
        $writer = app(IdentityGovernanceStreamWriter::class);

        $writer->append(
            streamId: self::STREAM,
            expectedVersion: 0,
            eventId: 'schema-genesis',
            eventType: GovernanceEventTypes::IDENTITY_CREATED,
        );

        $writer->append(
            streamId: self::STREAM,
            expectedVersion: 1,
            eventId: 'schema-username',
            eventType: GovernanceEventTypes::IDENTITY_USERNAME_ASSIGNED,
            payload: ['username' => 'alice'],
        );

        $row = IdentityGovernanceStreamEvent::query()
            ->where('event_id', 'schema-username')
            ->firstOrFail();

        $this->assertSame(1, $row->payload['schema_version'] ?? null);
    }

    private function seedStream(IdentityGovernanceStreamWriter $writer): void
    {
        $writer->append(self::STREAM, 0, 'ops-genesis', GovernanceEventTypes::IDENTITY_CREATED);
        $writer->append(self::STREAM, 1, 'ops-username', GovernanceEventTypes::IDENTITY_USERNAME_ASSIGNED, ['username' => 'alice']);
        $writer->append(self::STREAM, 2, 'ops-policy', GovernanceEventTypes::POLICY_DECLARED, [
            'rule' => 'all',
            'required_factor_classes' => ['possession'],
            'minimum_independent_dimensions' => 1,
        ]);
        $writer->append(self::STREAM, 3, 'ops-bind-a', GovernanceEventTypes::CREDENTIAL_BOUND, [
            'factor_id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            'class' => 'possession',
            'type' => 'passkey',
            'purpose' => 'daily',
        ]);
        $writer->append(self::STREAM, 4, 'ops-bind-b', GovernanceEventTypes::CREDENTIAL_BOUND, [
            'factor_id' => 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
            'class' => 'possession',
            'type' => 'passkey',
            'purpose' => 'daily',
            'webauthn' => [
                'credential_id' => base64_encode('credential-b'),
                'public_key' => base64_encode('public-key-b'),
                'sign_count' => 0,
            ],
        ]);
        $writer->append(self::STREAM, 5, 'ops-revoke-a', GovernanceEventTypes::CREDENTIAL_REVOKED, [
            'factor_id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
        ]);
    }

    private function seedStreamWithWebAuthn(IdentityGovernanceStreamWriter $writer): void
    {
        $writer->append(self::STREAM, 0, 'auth-ops-genesis', GovernanceEventTypes::IDENTITY_CREATED);
        $writer->append(self::STREAM, 1, 'auth-ops-bind', GovernanceEventTypes::CREDENTIAL_BOUND, [
            'factor_id' => 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
            'class' => 'possession',
            'type' => 'passkey',
            'purpose' => 'daily',
            'webauthn' => [
                'credential_id' => base64_encode('credential-b'),
                'public_key' => base64_encode('public-key-b'),
                'sign_count' => 0,
                'transports' => ['internal'],
            ],
        ]);
    }
}
