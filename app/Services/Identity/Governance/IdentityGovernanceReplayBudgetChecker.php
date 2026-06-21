<?php

namespace App\Services\Identity\Governance;

final class IdentityGovernanceReplayBudgetChecker
{
    public function __construct(
        private readonly IdentityGovernanceStreamAppender $appender,
        private readonly IdentityGovernanceProjectionRebuilder $rebuilder,
    ) {}

    public function measureStream(string $streamId): IdentityGovernanceReplayBudgetReport
    {
        $streamId = strtolower($streamId);
        $events = $this->appender->loadEvents($streamId);
        $eventCount = count($events);

        if ($eventCount === 0) {
            return new IdentityGovernanceReplayBudgetReport(
                streamId: $streamId,
                eventCount: 0,
                fullReplayMs: 0.0,
                credentialReplayMs: 0.0,
                snapshotTailMs: 0.0,
                msPer1kEvents: 0.0,
                violations: [],
            );
        }

        $fullStarted = hrtime(true);
        $this->rebuilder->projectFromEvents($events);
        $fullReplayMs = (hrtime(true) - $fullStarted) / 1_000_000;

        $credentialStarted = hrtime(true);
        IdentityCredentialReducer::fold($events);
        $credentialReplayMs = (hrtime(true) - $credentialStarted) / 1_000_000;

        $throughVersion = $events[max(0, $eventCount - 2)]->sequence;
        $snapshotStarted = hrtime(true);
        $this->rebuilder->replayGovernanceFromSnapshotAndTail($streamId, $throughVersion);
        $snapshotTailMs = (hrtime(true) - $snapshotStarted) / 1_000_000;

        $msPer1kEvents = ($fullReplayMs / max($eventCount, 1)) * 1000;

        return new IdentityGovernanceReplayBudgetReport(
            streamId: $streamId,
            eventCount: $eventCount,
            fullReplayMs: $fullReplayMs,
            credentialReplayMs: $credentialReplayMs,
            snapshotTailMs: $snapshotTailMs,
            msPer1kEvents: $msPer1kEvents,
            violations: $this->violations($eventCount, $fullReplayMs, $msPer1kEvents),
        );
    }

    /**
     * @return list<IdentityGovernanceReplayBudgetReport>
     */
    public function measureAllStreams(): array
    {
        $reports = [];

        foreach ($this->appender->listStreamIds() as $streamId) {
            $reports[] = $this->measureStream($streamId);
        }

        return $reports;
    }

    /**
     * @return list<string>
     */
    private function violations(int $eventCount, float $fullReplayMs, float $msPer1kEvents): array
    {
        $violations = [];
        $maxMsPer1k = (int) config('identity_governance.replay_budget.max_ms_per_1k_events', 500);
        $warnEventCount = (int) config('identity_governance.replay_budget.warn_stream_event_count', 10_000);
        $maxFullReplayMs = (int) config('identity_governance.replay_budget.max_full_replay_ms', 30_000);

        if ($msPer1kEvents > $maxMsPer1k) {
            $violations[] = "replay ms/1k events {$msPer1kEvents} exceeds budget {$maxMsPer1k}";
        }

        if ($fullReplayMs > $maxFullReplayMs) {
            $violations[] = "full replay {$fullReplayMs}ms exceeds max {$maxFullReplayMs}ms";
        }

        if ($eventCount >= $warnEventCount) {
            $violations[] = "stream event count {$eventCount} reached warn threshold {$warnEventCount}";
        }

        return $violations;
    }
}
