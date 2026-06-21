<?php

namespace App\Services\Identity\Governance;

final class IdentityGovernanceProjectionRebuilder
{
    public function __construct(
        private readonly IdentityGovernanceStreamAppender $appender,
    ) {}

    /**
     * @param  list<GovernanceEvent>  $events
     */
    public function projectFromEvents(array $events): IdentityGovernanceDualProjection
    {
        if ($events === []) {
            throw new \InvalidArgumentException('Cannot project from an empty event list.');
        }

        $throughVersion = $events[array_key_last($events)]->sequence;

        return new IdentityGovernanceDualProjection(
            registry: IdentityRegistryReducer::fold($events),
            governance: GovernanceReducer::fold($events),
            throughVersion: $throughVersion,
        );
    }

    public function replayFull(string $streamId): IdentityGovernanceDualProjection
    {
        return $this->projectFromEvents($this->appender->loadEvents($streamId));
    }

    public function replayGovernanceFromSnapshotAndTail(
        string $streamId,
        int $throughVersion,
    ): GovernanceProjection {
        $events = $this->appender->loadEvents($streamId);

        if ($events === []) {
            throw new \InvalidArgumentException("Stream {$streamId} has no events.");
        }

        $prefix = [];
        $tail = [];

        foreach ($events as $event) {
            if ($event->sequence <= $throughVersion) {
                $prefix[] = $event;

                continue;
            }

            $tail[] = $event;
        }

        if ($prefix === []) {
            throw new \InvalidArgumentException('Snapshot split produced an empty prefix.');
        }

        $snapshot = GovernanceReducer::foldToSnapshot($prefix);

        return GovernanceReducer::foldFromSnapshot($snapshot, $tail);
    }
}
