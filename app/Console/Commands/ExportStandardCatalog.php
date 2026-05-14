<?php

namespace App\Console\Commands;

use App\Models\WildflowCatalog;
use App\Services\StandardizationService;
use Illuminate\Console\Command;

class ExportStandardCatalog extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:export-catalog {--output=meanly_standard_catalog.json}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export catalog items to Meanly Golden Schema JSON format';

    /**
     * Execute the console command.
     */
    public function handle(StandardizationService $standardizer)
    {
        $outputFile = $this->option('output');
        $handle = fopen($outputFile, 'w');
        
        if (!$handle) {
            $this->error("Cannot open file: $outputFile");
            return 1;
        }

        fwrite($handle, "[\n");

        $first = true;
        $count = 0;

        $this->info("Starting export to $outputFile...");

        WildflowCatalog::with(['brand', 'region'])->chunk(500, function($items) use ($handle, &$first, &$count, $standardizer) {
            foreach ($items as $item) {
                if (!$first) {
                    fwrite($handle, ",\n");
                }
                
                $entry = $standardizer->standardizeCatalogItem($item);

                fwrite($handle, json_encode($entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $first = false;
                $count++;
            }
            
            $this->output->write('.');
        });

        fwrite($handle, "\n]");
        fclose($handle);

        $this->newLine();
        $this->info("EXPORT COMPLETED:");
        $this->line("-------------------");
        $this->line("Total items: $count");
        $this->line("File: $outputFile");

        return 0;
    }
}
