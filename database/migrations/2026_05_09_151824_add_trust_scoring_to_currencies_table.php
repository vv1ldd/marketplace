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
        Schema::table('currencies', function (Blueprint $table) {
            $table->unsignedTinyInteger('trust_tier')->default(3)->after('liquidity_stress_index');
            $table->decimal('confidence_score', 5, 4)->default(0.5)->after('trust_tier');
            $table->json('telemetry_signals')->nullable()->after('exchange_coverage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('currencies', function (Blueprint $table) {
            $table->dropColumn(['trust_tier', 'confidence_score', 'telemetry_signals']);
        });
    }
};
