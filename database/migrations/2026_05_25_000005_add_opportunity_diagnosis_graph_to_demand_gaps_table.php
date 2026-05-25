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
            $table->text('opportunity_diagnosis_graph')->nullable()->after('diagnosis_confidence');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('demand_gaps', function (Blueprint $table) {
            $table->dropColumn('opportunity_diagnosis_graph');
        });
    }
};
