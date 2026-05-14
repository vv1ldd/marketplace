<?php

namespace App\Console\Commands;

use App\Models\LegalEntity;
use App\Models\ProviderProduct;
use App\Services\StandardizationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ExportSellerCatalog extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:export-seller-catalog {legal_entity_id} {--output=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a personalized catalog feed for a specific Legal Entity (Seller)';

    /**
     * Execute the console command.
     */
    public function handle(StandardizationService $standardizer)
    {
        $entityId = $this->argument('legal_entity_id');
        $legalEntity = LegalEntity::with('shops')->find($entityId);

        if (!$legalEntity) {
            $this->error("Legal Entity with ID {$entityId} not found.");
            return 1;
        }

        $outputFile = $this->option('output') ?? "catalog_le_{$entityId}.json";
        $handle = fopen(storage_path("app/public/{$outputFile}"), 'w');
        
        if (!$handle) {
            $this->error("Cannot open file: " . storage_path("app/public/{$outputFile}"));
            return 1;
        }

        $this->info("Generating personalized catalog for: {$legalEntity->name} (Jurisdiction: " . ($legalEntity->country_id ?? 'Global') . ")...");

        fwrite($handle, "[\n");

        $first = true;
        $count = 0;
        $skipped = 0;

        // Use a representative shop for price calculation (or null to use Entity defaults)
        $shop = $legalEntity->shops->first();

        ProviderProduct::where('is_active', true)->chunk(500, function($products) use ($handle, &$first, &$count, &$skipped, $standardizer, $legalEntity, $shop) {
            foreach ($products as $product) {
                // 🛡️ Apply Jurisdiction and Permission Filters
                if (!$legalEntity->canSellProduct($product)) {
                    $skipped++;
                    continue;
                }

                if (!$first) {
                    fwrite($handle, ",\n");
                }
                
                // 🔑 Standardize with Personalized Pricing
                $entry = $standardizer->standardizeProviderProduct($product, $shop);

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
        $this->line("Legal Entity: " . $legalEntity->name);
        $this->line("Items Exported: $count");
        $this->line("Items Filtered (Blocked): $skipped");
        $this->line("File: storage/app/public/$outputFile");
        $this->line("Public URL: " . url("storage/$outputFile"));

        return 0;
    }
}
