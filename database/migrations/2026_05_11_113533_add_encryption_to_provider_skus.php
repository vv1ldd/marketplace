<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function getTableIndexes(string $table): \Illuminate\Support\Collection
    {
        if (\Illuminate\Support\Facades\DB::getDriverName() === 'sqlite') {
            return collect(\Illuminate\Support\Facades\DB::select("PRAGMA index_list('{$table}')"))->pluck('name')->unique();
        }
        return collect(\Illuminate\Support\Facades\DB::select("SHOW INDEX FROM `{$table}`"))->pluck('Key_name')->unique();
    }

    public function up(): void
    {
        // 1. Products table
        $indexes = $this->getTableIndexes('products');
        Schema::table('products', function (Blueprint $table) use ($indexes) {
            if ($indexes->contains('products_wildflow_catalog_sku_index')) {
                $table->dropIndex('products_wildflow_catalog_sku_index');
            }
            
            $table->text('wildflow_catalog_sku')->nullable()->change();
            
            if (!Schema::hasColumn('products', 'wildflow_catalog_sku_bidx')) {
                $table->string('wildflow_catalog_sku_bidx', 64)->nullable()->after('wildflow_catalog_sku')->index();
            }
            
            if (!Schema::hasColumn('products', 'fazer_catalog_sku')) {
                $table->text('fazer_catalog_sku')->nullable()->after('wildflow_catalog_sku_bidx');
            }
            
            if (!Schema::hasColumn('products', 'fazer_catalog_sku_bidx')) {
                $table->string('fazer_catalog_sku_bidx', 64)->nullable()->after('fazer_catalog_sku')->index();
            }
        });

        // 2. Wildflow Catalogs table
        $indexes = $this->getTableIndexes('wildflow_catalogs');
        Schema::table('wildflow_catalogs', function (Blueprint $table) use ($indexes) {
            if ($indexes->contains('wildflow_catalogs_service_sku_unique')) {
                $table->dropUnique('wildflow_catalogs_service_sku_unique');
            }
            
            $table->text('service_sku')->change(); // service_sku is NOT NULL
            
            if (!Schema::hasColumn('wildflow_catalogs', 'service_sku_bidx')) {
                $table->string('service_sku_bidx', 64)->nullable()->after('service_sku')->index();
            }
        });

        // 3. Provider Products table
        $indexes = $this->getTableIndexes('provider_products');
        Schema::table('provider_products', function (Blueprint $table) use ($indexes) {
            // Ensure provider_id has an index before dropping the unique one that covers it
            if (!$indexes->contains('provider_products_provider_id_index')) {
                $table->index('provider_id', 'provider_products_provider_id_index');
            }

            if ($indexes->contains('provider_products_sku_index')) {
                $table->dropIndex('provider_products_sku_index');
            }
            if ($indexes->contains('provider_products_market_sku_index')) {
                $table->dropIndex('provider_products_market_sku_index');
            }
            if ($indexes->contains('provider_products_provider_id_sku_unique')) {
                $table->dropUnique('provider_products_provider_id_sku_unique');
            }
            
            $table->text('sku')->change(); // sku is NOT NULL
            
            if (!Schema::hasColumn('provider_products', 'sku_bidx')) {
                $table->string('sku_bidx', 64)->nullable()->after('sku')->index();
            }
            
            $table->text('market_sku')->nullable()->change(); // market_sku is Nullable
            
            if (!Schema::hasColumn('provider_products', 'market_sku_bidx')) {
                $table->string('market_sku_bidx', 64)->nullable()->after('market_sku')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['wildflow_catalog_sku_bidx', 'fazer_catalog_sku', 'fazer_catalog_sku_bidx']);
            $table->string('wildflow_catalog_sku', 255)->nullable()->change();
            $table->index('wildflow_catalog_sku', 'products_wildflow_catalog_sku_index');
        });

        Schema::table('wildflow_catalogs', function (Blueprint $table) {
            $table->dropColumn(['service_sku_bidx']);
            $table->string('service_sku', 255)->change();
            $table->unique('service_sku', 'wildflow_catalogs_service_sku_unique');
        });

        Schema::table('provider_products', function (Blueprint $table) {
            $table->dropColumn(['sku_bidx', 'market_sku_bidx']);
            $table->string('sku', 255)->change();
            $table->string('market_sku', 255)->nullable()->change();
            
            $table->index('sku', 'provider_products_sku_index');
            $table->index('market_sku', 'provider_products_market_sku_index');
            $table->unique(['provider_id', 'sku'], 'provider_products_provider_id_sku_unique');
            
            if ($this->getTableIndexes('provider_products')->contains('provider_products_provider_id_index')) {
                $table->dropIndex('provider_products_provider_id_index');
            }
        });
    }
};
