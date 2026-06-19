<?php

namespace Tests\Feature;

use App\Models\IdentityGovernanceStreamEvent;
use App\Services\Identity\Governance\GovernanceEventTypes;
use App\Services\Identity\Governance\IdentityGovernanceStreamAppender;
use App\Services\Identity\Governance\IdentityGovernanceStreamEventId;
use App\Services\Identity\Governance\IdentityGovernanceStreamEventIdException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Guards against CI SQLite vs production MySQL storage divergence on event_id.
 * Producers use deterministic idempotency keys, not database UUIDs.
 */
class IdentityGovernanceStreamEventIdStorageTest extends TestCase
{
    use RefreshDatabase;

    private const STREAM = 'sl1e_event_id_storage_contract';

    #[Test]
    public function vault_producer_event_ids_persist_and_idempotently_replay(): void
    {
        $appender = app(IdentityGovernanceStreamAppender::class);
        $creationId = 'vault-create:user:9001';

        $first = $appender->append(
            streamId: self::STREAM,
            expectedVersion: 0,
            eventId: $creationId.':identity.created',
            eventType: GovernanceEventTypes::IDENTITY_CREATED,
        );

        $second = $appender->append(
            streamId: self::STREAM,
            expectedVersion: 1,
            eventId: $creationId.':identity.username_assigned',
            eventType: GovernanceEventTypes::IDENTITY_USERNAME_ASSIGNED,
            payload: ['username' => 'sl1e_storage_test'],
        );

        $third = $appender->append(
            streamId: self::STREAM,
            expectedVersion: 2,
            eventId: $creationId.':credential.bound',
            eventType: GovernanceEventTypes::CREDENTIAL_BOUND,
            payload: ['factor_id' => '550e8400-e29b-41d4-a716-446655440001'],
        );

        $retry = $appender->append(
            streamId: self::STREAM,
            expectedVersion: 3,
            eventId: $creationId.':credential.bound',
            eventType: GovernanceEventTypes::CREDENTIAL_BOUND,
            payload: ['factor_id' => '550e8400-e29b-41d4-a716-446655440001'],
        );

        $this->assertFalse($first->idempotentReplay);
        $this->assertFalse($second->idempotentReplay);
        $this->assertFalse($third->idempotentReplay);
        $this->assertTrue($retry->idempotentReplay);
        $this->assertSame(3, IdentityGovernanceStreamEvent::query()->where('stream_id', self::STREAM)->count());

        $stored = IdentityGovernanceStreamEvent::query()
            ->where('stream_id', self::STREAM)
            ->orderBy('version')
            ->pluck('event_id')
            ->all();

        $this->assertSame([
            'vault-create:user:9001:identity.created',
            'vault-create:user:9001:identity.username_assigned',
            'vault-create:user:9001:credential.bound',
        ], $stored);
    }

    #[Test]
    public function append_boundary_rejects_oversized_event_id_before_database(): void
    {
        $this->expectException(IdentityGovernanceStreamEventIdException::class);

        app(IdentityGovernanceStreamAppender::class)->append(
            streamId: self::STREAM,
            expectedVersion: 0,
            eventId: str_repeat('x', IdentityGovernanceStreamEventId::MAX_LENGTH + 1),
            eventType: GovernanceEventTypes::IDENTITY_CREATED,
        );
    }
}
