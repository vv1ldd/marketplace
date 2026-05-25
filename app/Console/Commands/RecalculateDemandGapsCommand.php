<?php

namespace App\Console\Commands;

use App\Services\DemandGapEngineService;
use App\Services\OpportunityLifecycleService;
use Illuminate\Console\Command;

class RecalculateDemandGapsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'demand:calculate-gaps {--auto-cases : Auto-open opportunity cases for high-score gaps} {--threshold=80 : Opportunity score threshold for auto cases}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate supply and demand gaps using advanced search metrics';

    /**
     * Execute the console command.
     */
    public function handle(DemandGapEngineService $engine, OpportunityLifecycleService $lifecycle): void
    {
        $this->info('Calculating supply and demand gaps...');

        $engine->recalculateGaps();

        $this->info('Demand gaps recalculated successfully!');

        if ($this->option('auto-cases')) {
            $threshold = (float) $this->option('threshold');
            $cases = $lifecycle->autoOpenCases($threshold);

            $this->info("Auto-created {$cases->count()} opportunity case(s) for score >= {$threshold}.");
        }
    }
}
