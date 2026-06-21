<?php

namespace App\Console\Commands;

use App\Services\Identity\Governance\IdentityGovernanceProjectionConvergenceChecker;
use Illuminate\Console\Command;

final class IdentityGovernanceCheckConvergenceCommand extends Command
{
    protected $signature = 'identity-governance:check-convergence {stream_id? : Optional sl1e stream id}';

    protected $description = 'Invariant 10 — verify projection convergence (full replay vs snapshot+tail vs cache)';

    public function handle(IdentityGovernanceProjectionConvergenceChecker $checker): int
    {
        $streamId = $this->argument('stream_id');

        if (is_string($streamId) && $streamId !== '') {
            $violations = $checker->violationsForStream(strtolower($streamId));

            if ($violations === []) {
                $this->info("Converged: {$streamId}");

                return self::SUCCESS;
            }

            $this->error("Convergence violations for {$streamId}:");
            foreach ($violations as $violation) {
                $this->line(" - {$violation}");
            }

            return self::FAILURE;
        }

        $report = $checker->violationsForAllStreams();

        if ($report === []) {
            $this->info('All identity governance streams converged.');

            return self::SUCCESS;
        }

        foreach ($report as $id => $violations) {
            $this->error("{$id}:");
            foreach ($violations as $violation) {
                $this->line(" - {$violation}");
            }
        }

        return self::FAILURE;
    }
}
