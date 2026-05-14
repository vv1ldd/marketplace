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
        // 1. Add is_global_catalog_enabled to legal_entities
        Schema::table('legal_entities', function (Blueprint $table) {
            if (!Schema::hasColumn('legal_entities', 'is_global_catalog_enabled')) {
                $table->boolean('is_global_catalog_enabled')->default(false)->after('tariff_type');
            }
        });

        // 2. Copy data from shops to legal_entities
        $shops = DB::table('shops')->whereNotNull('legal_entity_id')->get();
        foreach ($shops as $shop) {
            $updates = [];
            
            if (isset($shop->tariff_type)) {
                $updates['tariff_type'] = $shop->tariff_type;
            }
            if (isset($shop->is_global_catalog_enabled)) {
                $updates['is_global_catalog_enabled'] = $shop->is_global_catalog_enabled;
            }
            if (isset($shop->allow_all_brands)) {
                $updates['allow_all_brands'] = $shop->allow_all_brands;
            }
            
            if (!empty($updates)) {
                DB::table('legal_entities')->where('id', $shop->legal_entity_id)->update($updates);
            }
        }

        // 3. Drop columns from shops
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn([
                'tariff_type',
                'is_global_catalog_enabled',
                'allow_all_brands'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->string('tariff_type')->nullable();
            $table->boolean('is_global_catalog_enabled')->default(false);
            $table->boolean('allow_all_brands')->default(false);
        });

        // Copy back if needed
        $legalEntities = DB::table('legal_entities')->get();
        foreach ($legalEntities as $le) {
            DB::table('shops')->where('legal_entity_id', $le->id)->update([
                'tariff_type' => $le->tariff_type,
                'is_global_catalog_enabled' => $le->is_global_catalog_enabled,
                'allow_all_brands' => $le->allow_all_brands,
            ]);
        }

        Schema::table('legal_entities', function (Blueprint $table) {
            $table->dropColumn('is_global_catalog_enabled');
        });
    }
};
