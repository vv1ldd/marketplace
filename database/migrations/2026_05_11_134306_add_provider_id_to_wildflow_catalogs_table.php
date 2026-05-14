<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $indexes = collect(DB::select("SHOW INDEX FROM wildflow_catalogs"))->pluck('Key_name')->unique();

        Schema::table('wildflow_catalogs', function (Blueprint $table) use ($indexes) {
            if (!Schema::hasColumn('wildflow_catalogs', 'provider_id')) {
                $table->unsignedBigInteger('provider_id')->nullable()->after('id')->index();
            }

            // Drop old index if it exists
            if ($indexes->contains('wildflow_catalogs_service_sku_bidx_index')) {
                $table->dropIndex('wildflow_catalogs_service_sku_bidx_index');
            }
            
            // Add composite unique index for multi-provider support
            $table->unique(['provider_id', 'service_sku_bidx'], 'catalogs_provider_sku_unique');
        });

        // Backfill provider_id for existing Wildflow items
        $wildflowProviderId = DB::table('providers')->where('type', 'wildflow')->value('id');
        if ($wildflowProviderId) {
            DB::table('wildflow_catalogs')->whereNull('provider_id')->update(['provider_id' => $wildflowProviderId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wildflow_catalogs', function (Blueprint $table) {
            $table->dropUnique('catalogs_provider_sku_unique');
            $table->dropColumn('provider_id');
            $table->index('service_sku_bidx');
        });
    }
};
