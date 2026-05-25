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
        Schema::table('demand_gaps', function (Blueprint $table) {
            $table->string('opportunity_diagnosis')->nullable()->after('opportunity_score');
            $table->float('diagnosis_confidence')->nullable()->after('opportunity_diagnosis');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('demand_gaps', function (Blueprint $table) {
            $table->dropColumn(['opportunity_diagnosis', 'diagnosis_confidence']);
        });
    }
};
