<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = (new \App\Models\ProductInventory())->getTable();

        // 1. Add blind index column if missing
        if (!Schema::hasColumn($tableName, 'sku_bidx')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('sku_bidx', 64)->nullable()->after('sku')->index();
            });
        }

        // 2. Drop old SKU index via raw SQL (required before TEXT conversion)
        $indexes = DB::select("SHOW INDEX FROM `{$tableName}` WHERE Key_name = 'product_inventory_sku_index'");
        if (!empty($indexes)) {
            DB::statement("ALTER TABLE `{$tableName}` DROP INDEX `product_inventory_sku_index`");
        }

        // 3. Convert sku to TEXT so it can hold vault:local:... tokens
        DB::statement("ALTER TABLE `{$tableName}` MODIFY `sku` TEXT NOT NULL");

        // 4. Encrypt existing plaintext SKUs
        $vault = app(\App\Services\VaultTransitService::class);

        DB::table($tableName)->chunkById(500, function ($rows) use ($vault, $tableName) {
            foreach ($rows as $row) {
                if ($row->sku && !str_starts_with($row->sku, 'vault:')) {
                    DB::table($tableName)->where('id', $row->id)->update([
                        'sku'      => $vault->encrypt($row->sku),
                        'sku_bidx' => $vault->computeBlindIndex($row->sku),
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
    }
};
