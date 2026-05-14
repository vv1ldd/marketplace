<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('currency_telemetries', function (Blueprint $table) {
            $table->string('reporter_id')->nullable()->index()->after('currency_code');
            $table->float('reporter_reputation')->default(0.5)->after('confidence');
        });

        Schema::table('currencies', function (Blueprint $table) {
            $table->float('liquidity_stress_index')->default(0)->after('spread_percent');
            $table->integer('telemetry_count_48h')->default(0)->after('liquidity_stress_index');
        });
    }

    public function down(): void
    {
        Schema::table('currency_telemetries', function (Blueprint $table) {
            $table->dropColumn(['reporter_id', 'reporter_reputation']);
        });
        Schema::table('currencies', function (Blueprint $table) {
            $table->dropColumn(['liquidity_stress_index', 'telemetry_count_48h']);
        });
    }
};
