<?php

namespace App\Console\Commands;

use App\Services\IntentLiquidityGraphService;
use Illuminate\Console\Command;

class RebuildIntentLiquidityGraphCommand extends Command
{
    protected $signature = 'intent-liquidity:rebuild';

    protected $description = 'Rebuilds the unified intent liquidity graph from commerce entities and currency corridors';

    public function handle(IntentLiquidityGraphService $graph): int
    {
        $count = $graph->rebuild();

        $this->info("Intent liquidity graph rebuilt from {$count} source nodes.");

        return self::SUCCESS;
    }
}
