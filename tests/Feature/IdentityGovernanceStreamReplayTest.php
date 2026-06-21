<?php

namespace Tests\Feature;

use App\Services\Identity\Governance\GovernanceEventTypes;
use App\Services\Identity\Governance\IdentityGovernanceProjectionRebuilder;
use App\Services\Identity\Governance\IdentityGovernanceStreamAppender;
use App\Services\Identity\Governance\IdentityGovernanceStreamWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IdentityGovernanceStreamReplayTest extends TestCase
{
    use RefreshDatabase;

    private const STREAM = 'sl1e_replay_milestone';

    private IdentityGovernanceStreamWriter $writer;

    private IdentityGovernanceProjectionRebuilder $rebuilder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->writer = app(IdentityGovernanceStreamWriter::class);
        $this->rebuilder = app(IdentityGovernanceProjectionRebuilder::class);
    }

    #[Test]
    public function strong_read_is_consistent_immediately_after_append(): void
    {
        $write = $this->writer->append(
            streamId: self::STREAM,
            expectedVersion: 0,
            eventId: 'event-genesis-replay',
            eventType: GovernanceEventTypes::IDENTITY_CREATED,
        );

        $read = $this->writer->read(self::STREAM);

        $this->assertTrue($read->equals($write->projection));
        $this->assertTrue($read->registry->exists);
    }

    #[Test]
    public function projection_replay_invariant_on_persisted_stream(): void
    {
        $this->seedStreamWithMultipleEvents();

        $events = app(IdentityGovernanceStreamAppender::class)->loadEvents(self::STREAM);
        $full = $this->rebuilder->projectFromEvents($events);

        for ($splitAt = 1; $splitAt < count($events); $splitAt++) {
            $throughVersion = $events[$splitAt - 1]->sequence;
            $fromSnapshot = $this->rebuilder->replayGovernanceFromSnapshotAndTail(
                self::STREAM,
                $throughVersion,
            );

            $this->assertSame(
                $full->governance->toArray(),
                $fromSnapshot->toArray(),
                "Governance snapshot replay diverged at through_version {$throughVersion}",
            );
        }

        $registryTwice = $this->rebuilder->replayFull(self::STREAM);

        $this->assertTrue($registryTwice->equals($full));
    }

    #[Test]
    public function restart_replay_produces_identical_projections(): void
    {
        $this->seedStreamWithMultipleEvents();

        $beforeRestart = $this->writer->read(self::STREAM);
        $afterRestart = $this->writer->replayAfterRestart(self::STREAM);

        $this->assertTrue($beforeRestart->equals($afterRestart));

        $readAfter = $this->writer->read(self::STREAM);
        $this->assertTrue($afterRestart->equals($readAfter));
    }

    #[Test]
    public function create_append_kill_cache_restart_replay_milestone(): void
    {
        $firstWrite = $this->writer->append(
            streamId: self::STREAM,
            expectedVersion: 0,
            eventId: 'milestone-genesis',
            eventType: GovernanceEventTypes::IDENTITY_CREATED,
        );

        $secondWrite = $this->writer->append(
            streamId: self::STREAM,
            expectedVersion: 1,
            eventId: 'milestone-username',
            eventType: GovernanceEventTypes::IDENTITY_USERNAME_ASSIGNED,
            payload: ['username' => 'alice'],
        );

        $thirdWrite = $this->writer->append(
            streamId: self::STREAM,
            expectedVersion: 2,
            eventId: 'milestone-bind',
            eventType: GovernanceEventTypes::CREDENTIAL_BOUND,
            payload: [
                'factor_id' => '11111111-1111-1111-1111-111111111111',
                'class' => 'knowledge',
                'type' => 'recovery_code',
            ],
        );

        $liveProjection = $thirdWrite->projection;

        app(\App\Services\Identity\Governance\IdentityGovernanceProjectionCacheStore::class)
            ->forget(self::STREAM);

        $replayed = $this->writer->replayAfterRestart(self::STREAM);

        $this->assertTrue($liveProjection->equals($replayed));
        $this->assertSame('alice', $replayed->registry->username);
        $this->assertSame('silver', $replayed->governance->protectionTier);
        $this->assertSame(3, $replayed->throughVersion);
        $this->assertFalse($firstWrite->append->idempotentReplay);
    }

    private function seedStreamWithMultipleEvents(): void
    {
        $this->writer->append(
            streamId: self::STREAM,
            expectedVersion: 0,
            eventId: 'seed-genesis',
            eventType: GovernanceEventTypes::IDENTITY_CREATED,
        );

        $this->writer->append(
            streamId: self::STREAM,
            expectedVersion: 1,
            eventId: 'seed-username',
            eventType: GovernanceEventTypes::IDENTITY_USERNAME_ASSIGNED,
            payload: ['username' => 'alice'],
        );

        $this->writer->append(
            streamId: self::STREAM,
            expectedVersion: 2,
            eventId: 'seed-policy',
            eventType: GovernanceEventTypes::POLICY_DECLARED,
            payload: [
                'version' => 1,
                'rule' => 'all',
                'required_factor_classes' => ['knowledge', 'possession'],
                'minimum_independent_dimensions' => 2,
            ],
        );

        $this->writer->append(
            streamId: self::STREAM,
            expectedVersion: 3,
            eventId: 'seed-bind-knowledge',
            eventType: GovernanceEventTypes::CREDENTIAL_BOUND,
            payload: [
                'factor_id' => '11111111-1111-1111-1111-111111111111',
                'class' => 'knowledge',
                'type' => 'recovery_code',
            ],
        );

        $this->writer->append(
            streamId: self::STREAM,
            expectedVersion: 4,
            eventId: 'seed-bind-possession-a',
            eventType: GovernanceEventTypes::CREDENTIAL_BOUND,
            payload: [
                'factor_id' => '22222222-2222-2222-2222-222222222222',
                'class' => 'possession',
                'type' => 'passkey',
                'purpose' => 'recovery',
                'metadata' => [
                    'device_id' => 'iphone',
                    'ecosystem' => 'apple',
                    'custody' => 'self',
                ],
            ],
        );

        $this->writer->append(
            streamId: self::STREAM,
            expectedVersion: 5,
            eventId: 'seed-bind-possession-b',
            eventType: GovernanceEventTypes::CREDENTIAL_BOUND,
            payload: [
                'factor_id' => '33333333-3333-3333-3333-333333333333',
                'class' => 'possession',
                'type' => 'passkey',
                'purpose' => 'recovery',
                'metadata' => [
                    'device_id' => 'pixel',
                    'ecosystem' => 'android',
                    'custody' => 'self',
                ],
            ],
        );
    }
}
