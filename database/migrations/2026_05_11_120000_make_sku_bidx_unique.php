<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Wildflow Catalogs: service_sku_bidx should be unique for upserts
        Schema::table('wildflow_catalogs', function (Blueprint $table) {
            // Drop existing index if it exists
            $this->dropIndexIfExists('wildflow_catalogs', 'wildflow_catalogs_service_sku_bidx_index');
            $table->unique('service_sku_bidx', 'wildflow_catalogs_service_sku_bidx_unique');
        });

        // 2. Provider Products: (provider_id, sku_bidx) should be unique
        Schema::table('provider_products', function (Blueprint $table) {
            $this->dropIndexIfExists('provider_products', 'provider_products_sku_bidx_index');
            $table->unique(['provider_id', 'sku_bidx'], 'provider_products_provider_sku_unique');
        });
    }

    public function down(): void
    {
        Schema::table('wildflow_catalogs', function (Blueprint $table) {
            $table->dropUnique('wildflow_catalogs_service_sku_bidx_unique');
            $table->index('service_sku_bidx');
        });

        Schema::table('provider_products', function (Blueprint $table) {
            $table->dropUnique('provider_products_provider_sku_unique');
            $table->index('sku_bidx');
        });
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        $exists = false;
        if (DB::getDriverName() === 'sqlite') {
            $exists = !collect(DB::select("PRAGMA index_list('{$table}')"))->where('name', $index)->isEmpty();
        } else {
            $conn = Schema::getConnection();
            $dbName = $conn->getDatabaseName();
            
            $exists = !empty(DB::select("
                SELECT 1 FROM information_schema.statistics 
                WHERE table_schema = ? 
                AND table_name = ? 
                AND index_name = ?
            ", [$dbName, $table, $index]));
        }

        if ($exists) {
            Schema::table($table, function (Blueprint $table) use ($index) {
                $table->dropIndex($index);
            });
        }
    }
};
