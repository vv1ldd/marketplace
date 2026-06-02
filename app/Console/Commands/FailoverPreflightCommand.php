<?php

namespace App\Console\Commands;

use App\Services\Continuity\ContinuityReadinessService;
use Illuminate\Console\Command;

class FailoverPreflightCommand extends Command
{
    protected $signature = 'marketplace:failover:preflight
        {--target-region= : Target region that should receive traffic}
        {--min-confidence=80 : Minimum recovery confidence required}
        {--json : Output machine-readable JSON}';

    protected $description = 'Return a go/no-go failover preflight result from continuity readiness signals.';

    public function handle(ContinuityReadinessService $readiness): int
    {
        $report = $readiness->report();
        $targetRegion = (string) ($this->option('target-region') ?: config('mutation.region', 'local'));
        $minConfidence = (int) $this->option('min-confidence');
        $checks = [
            'continuity_not_unhealthy' => ($report['status'] ?? 'UNHEALTHY') !== 'UNHEALTHY',
            'recovery_confidence' => (int) ($report['recovery_confidence'] ?? 0) >= $minConfidence,
            'db_writable' => (bool) ($report['db_writable'] ?? false),
            'writer_epoch_known' => filled($report['writer_epoch'] ?? null),
            'writer_region_matches_target' => ($report['writer_region'] ?? null) === $targetRegion,
        ];
        $allowed = ! in_array(false, $checks, true);
        $payload = [
            'status' => $allowed ? 'GO' : 'NO_GO',
            'target_region' => $targetRegion,
            'checks' => $checks,
            'readiness' => [
                'continuity_status' => $report['continuity_status'] ?? null,
                'recovery_confidence' => $report['recovery_confidence'] ?? null,
                'current_region' => $report['current_region'] ?? null,
                'writer_region' => $report['writer_region'] ?? null,
                'writer_epoch' => $report['writer_epoch'] ?? null,
                'db_writable' => $report['db_writable'] ?? null,
                'failover_allowed' => $report['failover_allowed'] ?? null,
            ],
        ];

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            $this->line('Failover preflight: '.$payload['status']);
            $this->table(
                ['Check', 'Result'],
                collect($checks)->map(fn (bool $result, string $name): array => [$name, $result ? 'pass' : 'fail'])->values()->all(),
            );
        }

        return $allowed ? self::SUCCESS : self::FAILURE;
    }
}
