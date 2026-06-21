<?php

namespace App\Services\Identity\Governance;

final class IdentityGovernanceReplayBudgetReport
{
    /**
     * @param  list<string>  $violations
     */
    public function __construct(
        public readonly string $streamId,
        public readonly int $eventCount,
        public readonly float $fullReplayMs,
        public readonly float $credentialReplayMs,
        public readonly float $snapshotTailMs,
        public readonly float $msPer1kEvents,
        public readonly array $violations,
    ) {}

    public function withinBudget(): bool
    {
        return $this->violations === [];
    }

    /**
     * @return array<string, int|float|list<string>>
     */
    public function toArray(): array
    {
        return [
            'stream_id' => $this->streamId,
            'event_count' => $this->eventCount,
            'full_replay_ms' => round($this->fullReplayMs, 3),
            'credential_replay_ms' => round($this->credentialReplayMs, 3),
            'snapshot_tail_ms' => round($this->snapshotTailMs, 3),
            'ms_per_1k_events' => round($this->msPer1kEvents, 3),
            'within_budget' => $this->withinBudget(),
            'violations' => $this->violations,
        ];
    }
}
