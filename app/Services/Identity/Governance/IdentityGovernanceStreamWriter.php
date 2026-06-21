<?php

namespace App\Services\Identity\Governance;

use Illuminate\Support\Facades\DB;

/**
 * Append + synchronous projection rebuild (strong read consistency contract).
 */
final class IdentityGovernanceStreamWriter
{
    public function __construct(
        private readonly IdentityGovernanceStreamAppender $appender,
        private readonly IdentityGovernanceProjectionRebuilder $rebuilder,
        private readonly IdentityGovernanceProjectionCacheStore $projectionCache,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function append(
        string $streamId,
        int $expectedVersion,
        string $eventId,
        string $eventType,
        array $payload = [],
    ): IdentityGovernanceStreamWriteResult {
        return DB::transaction(function () use ($streamId, $expectedVersion, $eventId, $eventType, $payload): IdentityGovernanceStreamWriteResult {
            $appendResult = $this->appender->append(
                streamId: $streamId,
                expectedVersion: $expectedVersion,
                eventId: $eventId,
                eventType: $eventType,
                payload: $payload,
            );

            $projection = $this->rebuilder->replayFull($streamId);
            $this->projectionCache->write($projection);

            return new IdentityGovernanceStreamWriteResult(
                append: $appendResult,
                projection: $projection,
            );
        });
    }

    /**
     * Strong read: projection cache updated synchronously on every append.
     * On cache miss (cold start / restart), rebuild from stream.
     */
    public function read(string $streamId): IdentityGovernanceDualProjection
    {
        return $this->projectionCache->read($streamId)
            ?? $this->rebuilder->replayFull($streamId);
    }

    /**
     * Simulate process restart: drop cached projections, rebuild only from stream.
     */
    public function replayAfterRestart(string $streamId): IdentityGovernanceDualProjection
    {
        $this->projectionCache->forget($streamId);

        $projection = $this->rebuilder->replayFull($streamId);
        $this->projectionCache->write($projection);

        return $projection;
    }
}
