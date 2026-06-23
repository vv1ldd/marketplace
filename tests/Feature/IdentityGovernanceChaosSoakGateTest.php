<?php

namespace Tests\Feature;

use App\Models\IdentityGovernanceProjectionCache;
use App\Models\IdentityGovernanceStreamEvent;
use App\Services\Identity\Governance\GovernanceEventTypes;
use App\Services\Identity\Governance\IdentityGovernanceProjectionConvergenceChecker;
use App\Services\Identity\Governance\IdentityGovernanceReplayBudgetChecker;
use App\Services\Identity\Governance\IdentityGovernanceStreamAppender;
use App\Services\Identity\Governance\IdentityGovernanceStreamAssertionVerifier;
use App\Services\Identity\Governance\IdentityGovernanceStreamAuthorizeService;
use App\Services\Identity\Governance\IdentityGovernanceStreamConcurrencyException;
use App\Services\Identity\Governance\IdentityGovernanceStreamWriter;
use App\Services\Identity\Governance\Sl1eAuthorizeRequestContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Structural chaos gate (CI) — precursor to 24h production soak.
 */
class IdentityGovernanceChaosSoakGateTest extends TestCase
{
    use RefreshDatabase;

    private const STREAM = 'sl1e_chaos_soak_gate';

    #[Test]
    public function chaos_kill_cache_restart_concurrent_append_replay_authorize(): void
    {
        config([
            'app.domain' => 'localhost',
            'passkeys.relying_party.id' => 'localhost',
            'identity_governance.stream_enabled' => true,
            'identity_governance.stream_authorize_enabled' => true,
            'identity_governance.replay_budget.max_ms_per_1k_events' => 50_000,
        ]);

        $writer = app(IdentityGovernanceStreamWriter::class);
        $appender = app(IdentityGovernanceStreamAppender::class);

        $this->seedStream($writer);

        IdentityGovernanceProjectionCache::query()->delete();
        $restarted = $writer->replayAfterRestart(self::STREAM);
        $this->assertTrue($restarted->registry->exists);

        $head = $appender->headVersion(self::STREAM);
        $this->expectException(IdentityGovernanceStreamConcurrencyException::class);
        $appender->append(
            streamId: self::STREAM,
            expectedVersion: max(0, $head - 2),
            eventId: 'chaos-stale-writer',
            eventType: GovernanceEventTypes::IDENTITY_USERNAME_ASSIGNED,
            payload: ['username' => 'stale'],
        );

        $convergence = app(IdentityGovernanceProjectionConvergenceChecker::class)
            ->violationsForStream(self::STREAM);
        $this->assertSame([], $convergence);

        $budget = app(IdentityGovernanceReplayBudgetChecker::class)->measureStream(self::STREAM);
        $this->assertTrue($budget->withinBudget(), implode('; ', $budget->violations));

        $this->mock(IdentityGovernanceStreamAssertionVerifier::class, function ($mock): void {
            $mock->shouldReceive('verify')->once()->andReturn(true);
        });

        $authorize = app(IdentityGovernanceStreamAuthorizeService::class);
        $context = $this->authorizeContext();
        $options = $authorize->issueAuthenticationOptions($context, self::STREAM);
        $headBefore = $appender->headVersion(self::STREAM);

        $authorize->verifyAuthentication($context, $options['flowId'], [
            'id' => $options['options']['allowCredentials'][0]['id'],
            'rawId' => $options['options']['allowCredentials'][0]['id'],
            'type' => 'public-key',
            'response' => [
                'clientDataJSON' => 'e30',
                'authenticatorData' => 'e30',
                'signature' => 'e30',
            ],
        ]);

        $this->assertSame($headBefore, $appender->headVersion(self::STREAM));
    }

    #[Test]
    public function chaos_crash_after_append_before_projection_is_healed_by_replay(): void
    {
        $writer = app(IdentityGovernanceStreamWriter::class);
        $appender = app(IdentityGovernanceStreamAppender::class);
        $checker = app(IdentityGovernanceProjectionConvergenceChecker::class);

        $this->seedStream($writer);
        $liveBeforeCrash = $writer->read(self::STREAM);

        // Simulate crash after event persisted but before projection cache caught up
        // (misconfigured raw appender, partial failure, or cold cache after append).
        $appender->append(
            streamId: self::STREAM,
            expectedVersion: 3,
            eventId: 'chaos-post-crash-policy',
            eventType: GovernanceEventTypes::POLICY_DECLARED,
            payload: [
                'rule' => 'all',
                'required_factor_classes' => ['possession'],
                'minimum_independent_dimensions' => 1,
            ],
        );

        $this->assertSame(4, $appender->headVersion(self::STREAM));
        $this->assertNotEmpty($checker->violationsForStream(self::STREAM));

        $restarted = $writer->replayAfterRestart(self::STREAM);
        $this->assertSame([], $checker->violationsForStream(self::STREAM));
        $this->assertSame('alice', $restarted->registry->username);
        $this->assertNotNull($restarted->governance->currentPolicy);
        $this->assertGreaterThan($liveBeforeCrash->throughVersion, $restarted->throughVersion);
    }

    #[Test]
    public function chaos_duplicate_delivery_retry_keeps_event_count_plus_one(): void
    {
        $appender = app(IdentityGovernanceStreamAppender::class);

        $appender->append(
            streamId: self::STREAM,
            expectedVersion: 0,
            eventId: 'chaos-dedupe-genesis',
            eventType: GovernanceEventTypes::IDENTITY_CREATED,
        );

        $first = $appender->append(
            streamId: self::STREAM,
            expectedVersion: 1,
            eventId: 'chaos-dedupe-bind',
            eventType: GovernanceEventTypes::CREDENTIAL_BOUND,
            payload: [
                'factor_id' => 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
                'class' => 'possession',
                'type' => 'passkey',
            ],
        );

        $retry = $appender->append(
            streamId: self::STREAM,
            expectedVersion: 1,
            eventId: 'chaos-dedupe-bind',
            eventType: GovernanceEventTypes::CREDENTIAL_BOUND,
            payload: [
                'factor_id' => 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
                'class' => 'possession',
                'type' => 'passkey',
            ],
        );

        $this->assertFalse($first->idempotentReplay);
        $this->assertTrue($retry->idempotentReplay);
        $this->assertSame(2, IdentityGovernanceStreamEvent::query()->where('stream_id', self::STREAM)->count());
        $this->assertSame(2, $appender->headVersion(self::STREAM));
    }

    #[Test]
    public function invariant_13_replay_budget_reports_metrics_for_stream(): void
    {
        config(['identity_governance.replay_budget.max_ms_per_1k_events' => 50_000]);

        $writer = app(IdentityGovernanceStreamWriter::class);
        $writer->append(self::STREAM, 0, 'budget-genesis', GovernanceEventTypes::IDENTITY_CREATED);
        $writer->append(self::STREAM, 1, 'budget-bind', GovernanceEventTypes::CREDENTIAL_BOUND, [
            'factor_id' => 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
            'class' => 'possession',
            'type' => 'passkey',
            'webauthn' => [
                'credential_id' => base64_encode('cred'),
                'public_key' => base64_encode('pk'),
                'sign_count' => 0,
            ],
        ]);

        $report = app(IdentityGovernanceReplayBudgetChecker::class)->measureStream(self::STREAM);

        $this->assertSame(2, $report->eventCount);
        $this->assertGreaterThan(0, $report->fullReplayMs);
        $this->assertTrue($report->withinBudget());
    }

    private function seedStream(IdentityGovernanceStreamWriter $writer): void
    {
        $writer->append(self::STREAM, 0, 'chaos-genesis', GovernanceEventTypes::IDENTITY_CREATED);
        $writer->append(self::STREAM, 1, 'chaos-username', GovernanceEventTypes::IDENTITY_USERNAME_ASSIGNED, [
            'username' => 'alice',
        ]);
        $writer->append(self::STREAM, 2, 'chaos-bind', GovernanceEventTypes::CREDENTIAL_BOUND, [
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

    private function authorizeContext(): Sl1eAuthorizeRequestContext
    {
        return new Sl1eAuthorizeRequestContext(
            clientId: 'meanly.test',
            clientName: 'Meanly',
            redirectUri: 'https://meanly.test/simple-l1/callback',
            state: 'chaos-state',
            nonce: 'chaos-nonce',
            mode: 'login',
            scope: 'openid sl1e',
        );
    }
}
