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
        if (Schema::hasColumn('wildflow_catalogs', 'region_id')) {
            return;
        }

        Schema::table('wildflow_catalogs', function (Blueprint $table) {
            $after = Schema::hasColumn('wildflow_catalogs', 'brand_id') ? 'brand_id' : 'id';

            // Production can already have mapping_countries.id as legacy INT.
            // Keep the application relation but avoid an incompatible FK during deploy.
            $table->unsignedBigInteger('region_id')->nullable()->after($after)->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('wildflow_catalogs', 'region_id')) {
            return;
        }

        Schema::table('wildflow_catalogs', function (Blueprint $table) {
            $table->dropColumn('region_id');
        });
    }
};
