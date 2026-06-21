<?php

namespace App\Services\Identity\Governance;

final class IdentityGovernanceProjectionConvergenceChecker
{
    public function __construct(
        private readonly IdentityGovernanceStreamAppender $appender,
        private readonly IdentityGovernanceProjectionRebuilder $rebuilder,
        private readonly IdentityGovernanceProjectionCacheStore $projectionCache,
    ) {}

    /**
     * @return list<string>
     */
    public function streamIds(): array
    {
        return $this->appender->listStreamIds();
    }

    /**
     * @return list<string> empty when converged
     */
    public function violationsForStream(string $streamId): array
    {
        $events = $this->appender->loadEvents($streamId);

        if ($events === []) {
            return [];
        }

        $violations = [];
        $full = $this->rebuilder->projectFromEvents($events);
        $credentialA = IdentityCredentialReducer::fold($events);
        $credentialB = IdentityCredentialReducer::fold($events);

        if (! $credentialA->equals($credentialB)) {
            $violations[] = 'credential projection is not idempotent on full replay';
        }

        $cached = $this->projectionCache->read($streamId);
        if ($cached !== null && ! $cached->equals($full)) {
            $violations[] = 'projection cache diverges from full replay';
        }

        for ($splitAt = 1; $splitAt < count($events); $splitAt++) {
            $throughVersion = $events[$splitAt - 1]->sequence;
            $fromSnapshot = $this->rebuilder->replayGovernanceFromSnapshotAndTail(
                $streamId,
                $throughVersion,
            );

            if ($fromSnapshot->toArray() !== $full->governance->toArray()) {
                $violations[] = "governance snapshot+tail diverged at through_version {$throughVersion}";
            }
        }

        $registryTwice = IdentityRegistryReducer::fold($events);

        if ($registryTwice->toArray() !== $full->registry->toArray()) {
            $violations[] = 'registry projection is not idempotent on full replay';
        }

        return $violations;
    }

    /**
     * @return array<string, list<string>>
     */
    public function violationsForAllStreams(): array
    {
        $report = [];

        foreach ($this->streamIds() as $streamId) {
            $violations = $this->violationsForStream($streamId);
            if ($violations !== []) {
                $report[$streamId] = $violations;
            }
        }

        return $report;
    }
}
