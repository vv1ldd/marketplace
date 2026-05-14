<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create a default global catalog if not exists
        $globalCatalogId = DB::table('catalogs')->where('type', 'global')->value('id');
        if (! $globalCatalogId) {
            $globalCatalogId = DB::table('catalogs')->insertGetId([
                'name' => 'Глобальный каталог',
                'type' => 'global',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('products', function (Blueprint $table) {
            // Add catalog_id
            $table->foreignId('catalog_id')->nullable()->constrained('catalogs')->nullOnDelete();
        });

        // 2. Assign all existing products to the global catalog
        DB::table('products')->update(['catalog_id' => $globalCatalogId]);

        Schema::table('products', function (Blueprint $table) {
            // Drop old unique index and add new one
            $table->dropUnique('products_sku_unique');
            $table->unique(['sku', 'catalog_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['sku', 'catalog_id']);
            $table->unique('sku', 'products_sku_unique');
            $table->dropForeign(['catalog_id']);
            $table->dropColumn('catalog_id');
        });
    }
};
