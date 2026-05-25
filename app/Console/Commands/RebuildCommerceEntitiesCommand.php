<?php

namespace App\Console\Commands;

use App\Services\CommerceEntityGraphService;
use Illuminate\Console\Command;

class RebuildCommerceEntitiesCommand extends Command
{
    protected $signature = 'commerce:rebuild-entities';

    protected $description = 'Rebuild commerce entities, links, and materialized metrics from canonical identities';

    public function handle(CommerceEntityGraphService $graph): int
    {
        $this->info('Rebuilding commerce entity graph...');

        $count = $graph->rebuild();

        $this->info("Rebuilt {$count} commerce entity node(s).");

        return self::SUCCESS;
    }
}
