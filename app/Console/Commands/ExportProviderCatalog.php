<?php

namespace App\Console\Commands;

use App\Models\ProviderProduct;
use App\Models\Shop;
use App\Services\StandardizationService;
use Illuminate\Console\Command;

class ExportProviderCatalog extends Command
{
    protected $signature = 'app:export-provider-catalog
                            {--output=provider_catalog.json : Output file path}
                            {--provider= : Filter by provider type (wildflow, etc.)}
                            {--shop= : Shop ID to apply tariff pricing}
                            {--brand= : Filter by brand name}
                            {--pretty : Pretty-print JSON}';

    protected $description = 'Export all ProviderProducts in Meanly Golden Schema JSON';

    public function handle(StandardizationService $standardizer): int
    {
        $outputFile = $this->option('output');
        $shopId     = $this->option('shop');
        $provider   = $this->option('provider');
        $brand      = $this->option('brand');
        $pretty     = $this->option('pretty');

        $shop = $shopId ? Shop::find($shopId) : null;

        if ($shopId && ! $shop) {
            $this->error("Shop ID {$shopId} not found.");
            return 1;
        }

        $handle = fopen($outputFile, 'w');
        if (! $handle) {
            $this->error("Cannot open file: {$outputFile}");
            return 1;
        }

        $this->info("Exporting to: {$outputFile}" . ($shop ? " (Shop: {$shop->name}, tariff: {$shop->tariff_type})" : ' (no shop pricing)'));

        fwrite($handle, "[\n");

        $first = true;
        $count = 0;
        $flags = JSON_UNESCAPED_UNICODE | ($pretty ? JSON_PRETTY_PRINT : 0);

        $query = ProviderProduct::with(['provider', 'brand.catalogGroup', 'region'])
            ->where('is_active', true);

        if ($provider) {
            $query->whereHas('provider', fn ($q) => $q->where('type', $provider));
        }

        if ($brand) {
            $query->whereHas('brand', fn ($q) => $q->where('name', 'like', "%{$brand}%"));
        }

        $query->orderBy('provider_id')->orderBy('id')->chunk(500, function ($items) use (
            $handle, &$first, &$count, $standardizer, $shop, $flags
        ) {
            foreach ($items as $item) {
                if (! $first) {
                    fwrite($handle, ",\n");
                }

                $entry = $standardizer->standardizeProviderProduct($item, $shop);
                fwrite($handle, json_encode($entry, $flags));

                $first = false;
                $count++;
            }
            $this->output->write('.');
        });

        fwrite($handle, "\n]");
        fclose($handle);

        $this->newLine();
        $this->info("✅ Export completed: {$count} products → {$outputFile}");

        return 0;
    }
}
