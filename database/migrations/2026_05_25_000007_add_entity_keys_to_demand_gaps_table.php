<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demand_gaps', function (Blueprint $table) {
            $table->string('brand_entity_key')->nullable()->after('canonical_query');
            $table->string('region_entity_key')->nullable()->after('brand_entity_key');
            $table->string('category_entity_key')->nullable()->after('region_entity_key');

            $table->index('brand_entity_key');
            $table->index('region_entity_key');
            $table->index('category_entity_key');
        });
    }

    public function down(): void
    {
        Schema::table('demand_gaps', function (Blueprint $table) {
            $table->dropIndex(['brand_entity_key']);
            $table->dropIndex(['region_entity_key']);
            $table->dropIndex(['category_entity_key']);
            $table->dropColumn(['brand_entity_key', 'region_entity_key', 'category_entity_key']);
        });
    }
};
