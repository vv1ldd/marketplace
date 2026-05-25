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
        Schema::table('catalog_search_logs', function (Blueprint $table) {
            $table->integer('views_count')->default(0)->after('results_count');
            $table->integer('carts_count')->default(0)->after('views_count');
        });

        Schema::table('demand_gaps', function (Blueprint $table) {
            $table->integer('views_count')->default(0)->after('search_volume');
            $table->integer('carts_count')->default(0)->after('views_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('catalog_search_logs', function (Blueprint $table) {
            $table->dropColumn(['views_count', 'carts_count']);
        });

        Schema::table('demand_gaps', function (Blueprint $table) {
            $table->dropColumn(['views_count', 'carts_count']);
        });
    }
};
