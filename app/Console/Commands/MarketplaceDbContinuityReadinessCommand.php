<?php

namespace App\Console\Commands;

use App\Services\Continuity\ContinuityReadinessService;
use Illuminate\Console\Command;

class MarketplaceDbContinuityReadinessCommand extends Command
{
    protected $signature = 'marketplace:db-continuity-readiness {--json : Output machine-readable JSON}';

    protected $description = 'Check marketplace continuity readiness, recovery confidence, writer authority, ledger continuity, anchors, and projections.';

    public function handle(ContinuityReadinessService $readiness): int
    {
        $report = $readiness->report();

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return $report['status'] === 'UNHEALTHY' ? self::FAILURE : self::SUCCESS;
        }

        $this->info('MARKETPLACE DB CONTINUITY READINESS');
        $this->line('------------------------------------');
        $this->line('Status: '.$report['status']);
        $this->line('Recovery Confidence: '.$report['recovery_confidence'].'%');
        $this->newLine();

        $this->table(
            ['Domain', 'Status', 'Detail'],
            collect($report['checks'])
                ->map(fn (array $check): array => [
                    $check['name'],
                    strtoupper($check['status']),
                    $check['detail'],
                ])
                ->all(),
        );

        return $report['status'] === 'UNHEALTHY' ? self::FAILURE : self::SUCCESS;
    }
}
