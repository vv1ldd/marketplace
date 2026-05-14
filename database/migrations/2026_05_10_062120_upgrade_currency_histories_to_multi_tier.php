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
        Schema::table('currency_histories', function (Blueprint $table) {
            $table->decimal('tradfi_rate', 20, 10)->nullable()->after('official_rate');
            $table->decimal('spot_rate', 20, 10)->nullable()->after('tradfi_rate');
            $table->renameColumn('p2p_bybit', 'p2p_rate');
            $table->decimal('liquidity_stress_index', 8, 4)->default(0)->after('spread_percent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('currency_histories', function (Blueprint $table) {
            $table->dropColumn(['tradfi_rate', 'spot_rate', 'liquidity_stress_index']);
            $table->renameColumn('p2p_rate', 'p2p_bybit');
        });
    }
};
