<?php

namespace Tests\Feature;

use App\Services\Identity\Governance\GovernanceEventTypes;
use App\Services\Identity\Governance\GovernanceReducer;
use App\Services\Identity\Governance\IdentityGovernanceStreamAppender;
use App\Services\Identity\Governance\IdentityGovernanceStreamConcurrencyException;
use App\Services\Identity\Governance\IdentityGovernanceStreamIdempotencyConflictException;
use App\Services\Identity\Governance\IdentityRegistryReducer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IdentityGovernanceStreamAppendTest extends TestCase
{
    use RefreshDatabase;

    private const STREAM = 'sl1e_append_contract_test';

    private IdentityGovernanceStreamAppender $appender;

    protected function setUp(): void
    {
        parent::setUp();

        $this->appender = app(IdentityGovernanceStreamAppender::class);
    }

    #[Test]
    public function append_requires_expected_version(): void
    {
        $this->appendGenesis('event-genesis-1');

        $this->expectException(IdentityGovernanceStreamConcurrencyException::class);

        $this->appender->append(
            streamId: self::STREAM,
            expectedVersion: 0,
            eventId: 'event-username-stale',
            eventType: GovernanceEventTypes::IDENTITY_USERNAME_ASSIGNED,
            payload: ['username' => 'alice'],
        );
    }

    #[Test]
    public function same_event_id_is_idempotent_after_success(): void
    {
        $first = $this->appendGenesis('event-genesis-2');
        $this->assertFalse($first->idempotentReplay);

        $retry = $this->appender->append(
            streamId: self::STREAM,
            expectedVersion: 99,
            eventId: 'event-genesis-2',
            eventType: GovernanceEventTypes::IDENTITY_CREATED,
        );

        $this->assertTrue($retry->idempotentReplay);
        $this->assertSame(1, $retry->version);
        $this->assertSame(1, $this->appender->headVersion(self::STREAM));
    }

    #[Test]
    public function concurrent_writers_cannot_both_advance_stream(): void
    {
        $this->appendGenesis('event-genesis-3');

        $winner = $this->appender->append(
            streamId: self::STREAM,
            expectedVersion: 1,
            eventId: 'event-bind-a',
            eventType: GovernanceEventTypes::CREDENTIAL_BOUND,
            payload: [
                'factor_id' => '11111111-1111-1111-1111-111111111111',
                'class' => 'knowledge',
                'type' => 'recovery_code',
            ],
        );

        $this->assertSame(2, $winner->version);

        $this->expectException(IdentityGovernanceStreamConcurrencyException::class);

        $this->appender->append(
            streamId: self::STREAM,
            expectedVersion: 1,
            eventId: 'event-bind-b',
            eventType: GovernanceEventTypes::CREDENTIAL_BOUND,
            payload: [
                'factor_id' => '22222222-2222-2222-2222-222222222222',
                'class' => 'possession',
                'type' => 'passkey',
                'purpose' => 'daily',
            ],
        );
    }

    #[Test]
    public function same_event_id_with_different_payload_is_rejected(): void
    {
        $this->appendGenesis('event-genesis-4');

        $this->appender->append(
            streamId: self::STREAM,
            expectedVersion: 1,
            eventId: 'event-bind-conflict',
            eventType: GovernanceEventTypes::CREDENTIAL_BOUND,
            payload: [
                'factor_id' => '11111111-1111-1111-1111-111111111111',
                'class' => 'knowledge',
                'type' => 'recovery_code',
            ],
        );

        $this->expectException(IdentityGovernanceStreamIdempotencyConflictException::class);

        $this->appender->append(
            streamId: self::STREAM,
            expectedVersion: 2,
            eventId: 'event-bind-conflict',
            eventType: GovernanceEventTypes::CREDENTIAL_BOUND,
            payload: [
                'factor_id' => '99999999-9999-9999-9999-999999999999',
                'class' => 'knowledge',
                'type' => 'recovery_code',
            ],
        );
    }

    #[Test]
    public function persisted_stream_replays_to_dual_projections(): void
    {
        $this->appendGenesis('event-genesis-5');

        $this->appender->append(
            streamId: self::STREAM,
            expectedVersion: 1,
            eventId: 'event-username-5',
            eventType: GovernanceEventTypes::IDENTITY_USERNAME_ASSIGNED,
            payload: ['username' => 'alice'],
        );

        $events = $this->appender->loadEvents(self::STREAM);

        $registry = IdentityRegistryReducer::fold($events);
        $governance = GovernanceReducer::fold($events);

        $this->assertTrue($registry->exists);
        $this->assertSame('alice', $registry->username);
        $this->assertSame(self::STREAM, $governance->entity);
    }

    #[Test]
    public function two_concurrent_writers_corrupt_question_answer_is_no(): void
    {
        $this->appendGenesis('event-genesis-6');

        $firstAttempt = $this->appender->append(
            streamId: self::STREAM,
            expectedVersion: 1,
            eventId: 'event-race-a',
            eventType: GovernanceEventTypes::IDENTITY_USERNAME_ASSIGNED,
            payload: ['username' => 'alice'],
        );

        try {
            $this->appender->append(
                streamId: self::STREAM,
                expectedVersion: 1,
                eventId: 'event-race-b',
                eventType: GovernanceEventTypes::IDENTITY_USERNAME_ASSIGNED,
                payload: ['username' => 'bob'],
            );
            $this->fail('Expected concurrency exception for stale expected version.');
        } catch (IdentityGovernanceStreamConcurrencyException) {
            // expected stale writer failure
        }

        $this->assertSame(2, $this->appender->headVersion(self::STREAM));
        $this->assertSame(2, $firstAttempt->version);

        $events = $this->appender->loadEvents(self::STREAM);
        $this->assertCount(2, $events);
        $this->assertSame('alice', IdentityRegistryReducer::fold($events)->username);
    }

    private function appendGenesis(string $eventId): \App\Services\Identity\Governance\IdentityGovernanceStreamAppendResult
    {
        return $this->appender->append(
            streamId: self::STREAM,
            expectedVersion: 0,
            eventId: $eventId,
            eventType: GovernanceEventTypes::IDENTITY_CREATED,
        );
    }
}
