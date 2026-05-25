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
        if (Schema::hasColumn('currencies', 'trust_tier')) {
            return;
        }

        Schema::table('currencies', function (Blueprint $table) {
            $trustAfter = Schema::hasColumn('currencies', 'liquidity_stress_index') ? 'liquidity_stress_index' : 'id';
            $signalsAfter = Schema::hasColumn('currencies', 'exchange_coverage') ? 'exchange_coverage' : 'confidence_score';

            $table->unsignedTinyInteger('trust_tier')->default(3)->after($trustAfter);
            $table->decimal('confidence_score', 5, 4)->default(0.5)->after('trust_tier');
            $table->json('telemetry_signals')->nullable()->after($signalsAfter);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('currencies', 'trust_tier')) {
            return;
        }

        Schema::table('currencies', function (Blueprint $table) {
            $table->dropColumn(['trust_tier', 'confidence_score', 'telemetry_signals']);
        });
    }
};
