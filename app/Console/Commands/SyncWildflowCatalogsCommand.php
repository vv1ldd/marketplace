<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Operator-facing alias for the Meanly unified catalog sync pipeline.
 *
 * @see SyncCatalogsCommand
 */
class SyncWildflowCatalogsCommand extends Command
{
    protected $signature = 'wildflow:sync-catalogs
        {provider? : ID or type of provider to sync}
        {--force : Force sync even if catalog hash has not changed}
        {--embedded : Use embedded Meanly provider catalog projection}
        {--pull-upstream : Pull fresh EZPin catalog directly}';

    protected $description = 'Sync Wildflow/EZPin catalogs into Meanly provider authority (alias for app:sync-catalogs).';

    public function handle(): int
    {
        $args = array_filter([
            'provider' => $this->argument('provider'),
            '--force' => $this->option('force') ? true : null,
            '--embedded' => $this->option('embedded') ? true : null,
            '--pull-upstream' => $this->option('pull-upstream') ? true : null,
        ], fn ($value) => $value !== null);

        $exitCode = Artisan::call('app:sync-catalogs', $args);
        $this->output->write(Artisan::output());

        return $exitCode;
    }
}
