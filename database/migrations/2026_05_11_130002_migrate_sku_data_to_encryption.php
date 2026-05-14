<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $vault = app(\App\Services\VaultTransitService::class);

        // 1. Wildflow Catalogs
        DB::table('wildflow_catalogs')->chunkById(500, function ($items) use ($vault) {
            foreach ($items as $item) {
                if ($item->service_sku && !str_starts_with($item->service_sku, 'vault:')) {
                    DB::table('wildflow_catalogs')->where('id', $item->id)->update([
                        'service_sku' => $vault->encrypt($item->service_sku),
                        'service_sku_bidx' => $vault->computeBlindIndex($item->service_sku),
                    ]);
                }
            }
        });

        // 2. Provider Products
        DB::table('provider_products')->chunkById(500, function ($items) use ($vault) {
            foreach ($items as $item) {
                $updates = [];
                if ($item->sku && !str_starts_with($item->sku, 'vault:')) {
                    $updates['sku'] = $vault->encrypt($item->sku);
                    $updates['sku_bidx'] = $vault->computeBlindIndex($item->sku);
                }
                if ($item->market_sku && !str_starts_with($item->market_sku, 'vault:')) {
                    $updates['market_sku'] = $vault->encrypt($item->market_sku);
                    $updates['market_sku_bidx'] = $vault->computeBlindIndex($item->market_sku);
                }
                if (!empty($updates)) {
                    DB::table('provider_products')->where('id', $item->id)->update($updates);
                }
            }
        });

        // 3. Products
        DB::table('products')->chunkById(500, function ($items) use ($vault) {
            foreach ($items as $item) {
                $updates = [];
                if ($item->wildflow_catalog_sku && !str_starts_with($item->wildflow_catalog_sku, 'vault:')) {
                    $updates['wildflow_catalog_sku'] = $vault->encrypt($item->wildflow_catalog_sku);
                    $updates['wildflow_catalog_sku_bidx'] = $vault->computeBlindIndex($item->wildflow_catalog_sku);
                }
                if ($item->fazer_catalog_sku && !str_starts_with($item->fazer_catalog_sku, 'vault:')) {
                    $updates['fazer_catalog_sku'] = $vault->encrypt($item->fazer_catalog_sku);
                    $updates['fazer_catalog_sku_bidx'] = $vault->computeBlindIndex($item->fazer_catalog_sku);
                }
                if (!empty($updates)) {
                    DB::table('products')->where('id', $item->id)->update($updates);
                }
            }
        });
    }

    public function down(): void
    {
        // No automated down for data migration as it's destructive/complex
    }
};
