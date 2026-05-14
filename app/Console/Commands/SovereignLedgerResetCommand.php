<?php

namespace App\Console\Commands;

use App\Models\CurrencyTelemetryEvent;
use App\Models\SovereignLedger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SovereignLedgerResetCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sovereign:ledger-reset {--force : Force reset without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Safely clear the Sovereign Ledger and Currency Telemetry to restart the deterministic chain.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (!$this->option('force') && !$this->confirm('WARNING: This will permanently delete all deterministic proof chains. Proceed?')) {
            $this->warn('Operation cancelled.');
            return;
        }

        $this->info('Initializing Sovereign Reset Protocol...');

        // 1. Clear Sovereign Ledger
        $ledgerCount = SovereignLedger::count();
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        SovereignLedger::truncate();
        $this->comment("✓ SovereignLedger truncated (deleted $ledgerCount entries).");

        // 2. Clear Currency Telemetry (since it links to ledger events)
        $telemetryCount = CurrencyTelemetryEvent::count();
        CurrencyTelemetryEvent::truncate();
        $this->comment("✓ CurrencyTelemetryEvent truncated (deleted $telemetryCount entries).");
        
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->info('Sovereign Ledger has been successfully reset. The next event will be the new Genesis block.');
    }
}
