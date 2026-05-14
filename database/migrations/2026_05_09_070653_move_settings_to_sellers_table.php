<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add settings to legal_entities
        Schema::table('legal_entities', function (Blueprint $table) {
            if (!Schema::hasColumn('legal_entities', 'tariff_type')) {
                $table->string('tariff_type')->default('retail')->after('reserved_balance');
            }
            if (!Schema::hasColumn('legal_entities', 'markup_percent')) {
                $table->decimal('markup_percent', 5, 2)->default(0)->after('tariff_type');
            }
            if (!Schema::hasColumn('legal_entities', 'allowed_categories')) {
                $table->json('allowed_categories')->nullable()->after('markup_percent');
            }
            if (!Schema::hasColumn('legal_entities', 'allow_all_brands')) {
                $table->boolean('allow_all_brands')->default(true)->after('allowed_categories');
            }
        });

        // 2. Data Migration: Copy settings from the first shop of each LegalEntity
        $legalEntities = DB::table('legal_entities')->get();
        foreach ($legalEntities as $entity) {
            $shop = DB::table('shops')
                ->where('legal_entity_id', $entity->id)
                ->orderBy('id', 'asc')
                ->first();

            if ($shop) {
                DB::table('legal_entities')->where('id', $entity->id)->update([
                    'tariff_type'       => $shop->tariff_type ?? 'retail',
                    'markup_percent'    => $shop->markup_percent ?? 0,
                    'allowed_categories'=> $shop->allowed_categories,
                    'allow_all_brands'  => $shop->allow_all_brands ?? true,
                ]);
            }
        }
        
        // Note: We keep columns in 'shops' for now to prevent breaking code, 
        // but we should mark them as deprecated or remove them in a future migration.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('legal_entities', function (Blueprint $table) {
            $table->dropColumn(['tariff_type', 'markup_percent', 'allowed_categories', 'allow_all_brands']);
        });
    }
};
