<?php

namespace App\Console\Commands;

use App\Services\MeanlyOperationalAlertService;
use Illuminate\Console\Command;

class CheckMeanlyOperationalAlertsCommand extends Command
{
    protected $signature = 'meanly:check-alerts';

    protected $description = 'Evaluate Meanly operational alert rules for launch observability.';

    public function handle(MeanlyOperationalAlertService $alerts): int
    {
        $activeAlerts = $alerts->evaluate();

        if ($activeAlerts->isEmpty()) {
            $this->info('No active Meanly operational alerts.');

            return self::SUCCESS;
        }

        $this->warn("Active Meanly operational alerts: {$activeAlerts->count()}");

        foreach ($activeAlerts as $alert) {
            $this->line(sprintf(
                '[%s] %s — %s (%d)',
                strtoupper((string) $alert->severity),
                $alert->alert_key,
                $alert->title,
                (int) $alert->occurrence_count,
            ));
        }

        return self::SUCCESS;
    }
}
