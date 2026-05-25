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
            $table->decimal('opportunity_score', 5, 2)->default(0.00)->after('estimated_lost_gmv');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('demand_gaps', function (Blueprint $table) {
            $table->dropColumn('opportunity_score');
        });
    }
};
