<?php

namespace App\Console\Commands;

use App\Services\Identity\Governance\IdentityGovernanceReplayBudgetChecker;
use Illuminate\Console\Command;

final class IdentityGovernanceReplayBudgetCommand extends Command
{
    protected $signature = 'identity-governance:replay-budget {stream_id? : Optional sl1e stream id}';

    protected $description = 'Invariant 13 — measure replay latency and compare to ops budget';

    public function handle(IdentityGovernanceReplayBudgetChecker $checker): int
    {
        $streamId = $this->argument('stream_id');

        if (is_string($streamId) && $streamId !== '') {
            return $this->renderOne($checker->measureStream(strtolower($streamId)));
        }

        $reports = $checker->measureAllStreams();

        if ($reports === []) {
            $this->info('No identity governance streams found.');

            return self::SUCCESS;
        }

        $failed = false;

        foreach ($reports as $report) {
            if ($this->renderOne($report) !== self::SUCCESS) {
                $failed = true;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function renderOne(\App\Services\Identity\Governance\IdentityGovernanceReplayBudgetReport $report): int
    {
        $data = $report->toArray();

        $this->line(sprintf(
            '%s events=%d full=%sms credential=%sms snapshot+tail=%sms ms/1k=%s',
            $data['stream_id'],
            $data['event_count'],
            $data['full_replay_ms'],
            $data['credential_replay_ms'],
            $data['snapshot_tail_ms'],
            $data['ms_per_1k_events'],
        ));

        if ($report->withinBudget()) {
            return self::SUCCESS;
        }

        foreach ($report->violations as $violation) {
            $this->error(' - '.$violation);
        }

        return self::FAILURE;
    }
}
