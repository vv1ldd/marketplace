<?php

namespace App\Console\Commands;

use App\Domain\Routing\ExecutionRecordMetricsProvider;
use App\Domain\Routing\RoutingCircuitBreaker;
use App\Domain\Routing\RoutingPolicy;
use App\Models\Architecture\ExecutionRecord;
use App\Models\Provider;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class ReportRoutingProviderSignalsCommand extends Command
{
    protected $signature = 'routing:report-signals
                            {--fresh : Bypass cached provider signal snapshots}
                            {--json : Output machine-readable JSON}';

    protected $description = 'Report 7-day execution_record runtime signals per provider for routing baseline tuning.';

    public function handle(
        ExecutionRecordMetricsProvider $metrics,
        RoutingCircuitBreaker $circuitBreaker,
    ): int {
        $windowDays = max(1, min(30, (int) config('routing.metrics.window_days', 7)));
        $since = now()->subDays($windowDays);
        $policy = RoutingPolicy::fromConfig();

        $providerIds = ExecutionRecord::query()
            ->where('created_at', '>=', $since)
            ->whereNotNull('provider_id')
            ->distinct()
            ->orderBy('provider_id')
            ->pluck('provider_id');

        if ($providerIds->isEmpty()) {
            $this->warn("No execution_records in the last {$windowDays} day(s).");

            return self::SUCCESS;
        }

        $providers = Provider::query()
            ->whereIn('id', $providerIds)
            ->get(['id', 'name', 'type'])
            ->keyBy('id');

        $rows = $providerIds
            ->map(function (int $providerId) use ($metrics, $circuitBreaker, $policy, $providers): array {
                $report = $metrics->detailedReport($providerId, fresh: (bool) $this->option('fresh'));
                $provider = $providers->get($providerId);

                return array_merge($report, [
                    'provider_name' => (string) ($provider?->name ?: 'unknown'),
                    'provider_type' => (string) ($provider?->type ?: 'unknown'),
                    'circuit_status' => $circuitBreaker->status($providerId),
                    'circuit_tripped' => $circuitBreaker->isTripped($providerId, $policy),
                ]);
            })
            ->sortByDesc(fn (array $row): float => (float) ($row['success_rate'] ?? -1))
            ->values();

        if ($this->option('json')) {
            $this->line(json_encode([
                'generated_at' => now()->toIso8601String(),
                'window_days' => $windowDays,
                'providers' => $rows,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info("Routing runtime signals (last {$windowDays} days, terminal execution states)");
        $this->table(
            ['ID', 'Provider', 'Type', 'N', 'Issued', 'Failed', 'Manual', 'Success', 'p50 ms', 'p95 ms', 'Max ms', 'CB'],
            $rows->map(fn (array $row): array => [
                $row['provider_id'],
                $row['provider_name'],
                $row['provider_type'],
                $row['sample_size'],
                $row['issued'],
                $row['failed'],
                $row['manual'],
                $row['has_data'] ? number_format((float) $row['success_rate'] * 100, 1).'%' : '—',
                $row['p50_latency_ms'] ?? '—',
                $row['p95_latency_ms'] ?? '—',
                $row['max_latency_ms'] ?? '—',
                $row['circuit_tripped'] ? 'OPEN' : strtoupper((string) $row['circuit_status']),
            ])->all(),
        );

        $this->printAnomalyHints($rows);

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function printAnomalyHints(Collection $rows): void
    {
        $maxCeiling = max(1000, (int) config('routing.metrics.max_latency_ms', 30000));

        $highLatency = $rows->filter(
            fn (array $row): bool => ($row['max_latency_ms'] ?? 0) > $maxCeiling,
        );
        if ($highLatency->isNotEmpty()) {
            $this->newLine();
            $this->warn('Latency anomalies (max above normalization ceiling):');
            foreach ($highLatency as $row) {
                $this->line(sprintf(
                    '  provider %d (%s): max=%dms, p50=%s',
                    $row['provider_id'],
                    $row['provider_name'],
                    $row['max_latency_ms'],
                    $row['p50_latency_ms'] ?? '—',
                ));
            }
        }

        $lowSuccess = $rows->filter(
            fn (array $row): bool => $row['has_data']
                && $row['sample_size'] >= 5
                && (float) $row['success_rate'] < 0.9,
        );
        if ($lowSuccess->isNotEmpty()) {
            $this->newLine();
            $this->warn('Low success rate (sample >= 5, success < 90%):');
            foreach ($lowSuccess as $row) {
                $this->line(sprintf(
                    '  provider %d (%s): success=%.1f%% (%d/%d issued)',
                    $row['provider_id'],
                    $row['provider_name'],
                    (float) $row['success_rate'] * 100,
                    $row['issued'],
                    $row['sample_size'],
                ));
            }
        }
    }
}
